<?php

namespace App\Services;

use App\Enums\LoanOperationalState;
use App\Models\Loan;
use App\Models\LoanOperationalSetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LoanOperationalStateService
{
    public const string DEFAULT_SUSPENSION_MESSAGE = 'El servicio de préstamos se encuentra temporalmente fuera de servicio por mantenimiento o revisión administrativa. Por favor, intente más tarde.';

    /**
     * Retrieve the current persistent operational setting, or an in-memory default if none exists yet.
     */
    public function current(?Carbon $date = null): LoanOperationalSetting
    {
        $setting = LoanOperationalSetting::query()->with('updatedBy')->first();

        if ($setting !== null) {
            return $setting;
        }

        $referenceDate = $date ? $date->copy() : now();

        return new LoanOperationalSetting([
            'state' => LoanOperationalState::Activo,
            'target_fiscal_year' => $referenceDate->year,
            'suspension_message' => null,
        ]);
    }

    /**
     * Resolve the effective operational state at the given moment.
     * Automatically transitions CierreAnio to Activo once the target fiscal year begins.
     */
    public function effectiveState(?Carbon $date = null): LoanOperationalState
    {
        $referenceDate = $date ? $date->copy() : now();
        $setting = $this->current($referenceDate);
        $storedState = $setting->state ?? LoanOperationalState::Activo;

        if ($storedState === LoanOperationalState::CierreAnio
            && $setting->target_fiscal_year !== null
            && $referenceDate->year >= $setting->target_fiscal_year) {
            return LoanOperationalState::Activo;
        }

        return $storedState;
    }

    /**
     * Resolve the target fiscal year for new loan operations.
     */
    public function effectiveFiscalYear(?Carbon $date = null): int
    {
        $referenceDate = $date ? $date->copy() : now();
        $targetYear = $this->current($referenceDate)->target_fiscal_year;

        if ($targetYear === null) {
            return $referenceDate->year;
        }

        return max((int) $targetYear, $referenceDate->year);
    }

    /**
     * Determine whether the loan system is currently suspended.
     */
    public function isTemporarilyOutOfService(?Carbon $date = null): bool
    {
        return $this->effectiveState($date) === LoanOperationalState::TemporalmenteFueraDeServicio;
    }

    /**
     * Resolve the public suspension notice (configured message or institutional default).
     */
    public function publicSuspensionMessage(): string
    {
        $message = $this->current()->suspension_message;

        return filled($message) ? trim((string) $message) : self::DEFAULT_SUSPENSION_MESSAGE;
    }

    /**
     * Update the operational state with authorization, audit metadata, and audit log.
     *
     * @throws AuthorizationException
     */
    public function updateState(
        User $actor,
        LoanOperationalState $state,
        ?string $suspensionMessage = null,
        ?int $targetFiscalYear = null,
    ): LoanOperationalSetting {
        if (! Gate::forUser($actor)->allows('manageOperationalState', Loan::class)) {
            throw new AuthorizationException('No tienes autorización para modificar el estado operativo de préstamos.');
        }

        return DB::transaction(function () use ($actor, $state, $suspensionMessage, $targetFiscalYear): LoanOperationalSetting {
            $setting = LoanOperationalSetting::query()->lockForUpdate()->first();

            if ($setting === null) {
                $setting = new LoanOperationalSetting;
            }

            $previousState = $setting->exists ? $setting->state?->value : null;
            $previousYear = $setting->exists ? $setting->target_fiscal_year : null;

            $resolvedFiscalYear = $targetFiscalYear ?? match ($state) {
                LoanOperationalState::CierreAnio => now()->year + 1,
                LoanOperationalState::Activo => now()->year,
                LoanOperationalState::TemporalmenteFueraDeServicio => $setting->exists && $setting->target_fiscal_year
                    ? max((int) $setting->target_fiscal_year, now()->year)
                    : now()->year,
            };

            $resolvedMessage = $state === LoanOperationalState::TemporalmenteFueraDeServicio && filled($suspensionMessage)
                ? trim($suspensionMessage)
                : null;

            $now = now();

            $setting->fill([
                'state' => $state,
                'target_fiscal_year' => $resolvedFiscalYear,
                'suspension_message' => $resolvedMessage,
                'updated_by' => $actor->id,
                'state_changed_at' => $now,
            ]);
            $setting->save();

            DB::afterCommit(function () use ($actor, $setting, $previousState, $previousYear): void {
                AuditService::log('loan_operational_state_updated', $actor->id, $setting->id, [
                    'from_state' => $previousState,
                    'to_state' => $setting->state->value,
                    'from_fiscal_year' => $previousYear,
                    'to_fiscal_year' => $setting->target_fiscal_year,
                    'has_custom_message' => filled($setting->suspension_message),
                ]);
            });

            $setting->load('updatedBy');

            return $setting;
        });
    }
}
