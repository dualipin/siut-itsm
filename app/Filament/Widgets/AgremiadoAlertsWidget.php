<?php

namespace App\Filament\Widgets;

use App\Enums\DocumentStatus;
use App\Enums\LoanOperationalState;
use App\Enums\LoanStatus;
use App\Enums\UserDocumentType;
use App\Filament\Resources\Loans\LoanResource;
use App\Models\AnnualPetition;
use App\Models\Loan;
use App\Models\PetitionRequest;
use App\Models\User;
use App\Services\LoanOperationalStateService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class AgremiadoAlertsWidget extends Widget
{
    protected string $view = 'filament.widgets.agremiado-alerts-widget';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user?->isAgremiado()) {
            return false;
        }

        return static::getAlertsForRequest($user) !== [];
    }

    /**
     * @return array{
     *     alerts: list<array{
     *         key: string,
     *         color: string,
     *         icon: Heroicon,
     *         heading: string,
     *         description: string,
     *         actionLabel: ?string,
     *         actionUrl: ?string,
     *         newTab: bool,
     *     }>,
     * }
     */
    protected function getViewData(): array
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user?->isAgremiado()) {
            return ['alerts' => []];
        }

        return [
            'alerts' => static::getAlertsForRequest($user),
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     color: string,
     *     icon: Heroicon,
     *     heading: string,
     *     description: string,
     *     actionLabel: ?string,
     *     actionUrl: ?string,
     *     newTab: bool,
     * }>
     */
    protected static function getAlertsForRequest(User $user): array
    {
        $cacheKey = "agremiado_alerts_{$user->id}";
        $attributes = request()->attributes;

        if (! $attributes->has($cacheKey)) {
            $attributes->set($cacheKey, static::resolveAlertsFor($user));
        }

        /**
         * @var list<array{
         *     key: string,
         *     color: string,
         *     icon: Heroicon,
         *     heading: string,
         *     description: string,
         *     actionLabel: ?string,
         *     actionUrl: ?string,
         *     newTab: bool,
         * }> $alerts
         */
        $alerts = $attributes->get($cacheKey);

        return $alerts;
    }

    /**
     * Evaluate all contextual priority alerts for the given agremiado.
     *
     * @return list<array{
     *     key: string,
     *     color: string,
     *     icon: Heroicon,
     *     heading: string,
     *     description: string,
     *     actionLabel: ?string,
     *     actionUrl: ?string,
     *     newTab: bool,
     * }>
     */
    public static function resolveAlertsFor(User $user): array
    {
        $alerts = [];

        $user->loadMissing('media');

        $invalidCount = 0;
        $missingCount = 0;

        foreach (UserDocumentType::cases() as $documentType) {
            $status = $user->getDocumentStatus($documentType);

            if ($status === DocumentStatus::Invalid) {
                $invalidCount++;
            } elseif ($status === 'sin_subir') {
                $missingCount++;
            }
        }

        $profileUrl = route('filament.portal.auth.profile');

        if ($invalidCount > 0) {
            $alerts[] = [
                'key' => 'documents-invalid',
                'color' => 'danger',
                'icon' => Heroicon::OutlinedExclamationTriangle,
                'heading' => $invalidCount === 1
                    ? 'Tienes 1 documento rechazado en tu expediente'
                    : "Tienes {$invalidCount} documentos rechazados en tu expediente",
                'description' => 'Revisa las observaciones y vuelve a subir el archivo correspondiente en Mi Perfil.',
                'actionLabel' => 'Corregir en Mi Perfil',
                'actionUrl' => $profileUrl,
                'newTab' => false,
            ];
        }

        if ($missingCount > 0) {
            $alerts[] = [
                'key' => 'documents-missing',
                'color' => 'warning',
                'icon' => Heroicon::OutlinedDocumentArrowUp,
                'heading' => $missingCount === 1
                    ? 'Te falta 1 documento obligatorio por subir'
                    : "Te faltan {$missingCount} documentos obligatorios por subir",
                'description' => 'Completa tu expediente digital de afiliación en Mi Perfil para mantener tus trámites al día.',
                'actionLabel' => 'Ir a Mi Perfil',
                'actionUrl' => $profileUrl,
                'newTab' => false,
            ];
        }

        $stateService = app(LoanOperationalStateService::class);
        $effectiveState = $stateService->effectiveState();

        if ($effectiveState === LoanOperationalState::TemporalmenteFueraDeServicio) {
            $alerts[] = [
                'key' => 'loan-suspended',
                'color' => 'danger',
                'icon' => Heroicon::OutlinedExclamationTriangle,
                'heading' => 'Servicio de préstamos temporalmente fuera de servicio',
                'description' => $stateService->publicSuspensionMessage(),
                'actionLabel' => null,
                'actionUrl' => null,
                'newTab' => false,
            ];
        } elseif ($effectiveState === LoanOperationalState::CierreAnio) {
            $targetFiscalYear = $stateService->effectiveFiscalYear();

            $alerts[] = [
                'key' => 'loan-year-end',
                'color' => 'info',
                'icon' => Heroicon::OutlinedInformationCircle,
                'heading' => "Cierre de año — Recepción anticipada para el ejercicio fiscal {$targetFiscalYear}",
                'description' => sprintf(
                    'El ejercicio %d se encuentra cerrado. Las nuevas solicitudes se registrarán para el ejercicio fiscal %d.',
                    now()->year,
                    $targetFiscalYear,
                ),
                'actionLabel' => null,
                'actionUrl' => null,
                'newTab' => false,
            ];
        }

        $draftLoan = Loan::forUser($user)
            ->where('status', LoanStatus::Borrador)
            ->latest('id')
            ->first();

        if ($draftLoan !== null) {
            $alerts[] = [
                'key' => 'loan-draft',
                'color' => 'warning',
                'icon' => Heroicon::OutlinedDocumentText,
                'heading' => "Tienes un préstamo en borrador sin enviar ({$draftLoan->folio})",
                'description' => 'Retoma tu solicitud pendiente para completarla, firmarla y enviarla a revisión.',
                'actionLabel' => 'Continuar préstamo',
                'actionUrl' => LoanResource::getUrl('view', ['record' => $draftLoan]),
                'newTab' => false,
            ];
        }

        if (filled($user->curp)) {
            $openPetitions = AnnualPetition::query()
                ->whereDate('deadline', '>=', now()->toDateString())
                ->orderBy('deadline')
                ->get();

            if ($openPetitions->isNotEmpty()) {
                $normalizedCurp = mb_strtoupper(trim((string) $user->curp));

                $participatedPetitionIds = PetitionRequest::query()
                    ->whereIn('annual_petition_id', $openPetitions->pluck('id'))
                    ->whereRaw('UPPER(curp) = ?', [$normalizedCurp])
                    ->pluck('annual_petition_id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->all();

                $pendingPetition = $openPetitions->first(
                    fn (AnnualPetition $petition): bool => ! in_array($petition->id, $participatedPetitionIds, true),
                );

                if ($pendingPetition !== null) {
                    $alerts[] = [
                        'key' => 'annual-petition-open',
                        'color' => 'info',
                        'icon' => Heroicon::OutlinedMegaphone,
                        'heading' => "Convocatoria abierta: Pliego Anual {$pendingPetition->year}",
                        'description' => "Puedes registrar tus propuestas hasta el {$pendingPetition->deadline->format('d/m/Y')}.",
                        'actionLabel' => 'Participar en Pliego Anual',
                        'actionUrl' => route('petitions.index', ['annualPetition' => $pendingPetition]),
                        'newTab' => true,
                    ];
                }
            }
        }

        return $alerts;
    }
}
