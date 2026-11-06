<?php

namespace App\Services;

use App\Enums\LoanPaymentType;
use App\Enums\LoanStatus;
use App\Exceptions\LoanTransitionException;
use App\Models\Benefit;
use App\Models\Loan;
use App\Models\LoanAmortizationRow;
use App\Models\LoanBenefit;
use App\Models\LoanEvidence;
use App\Models\LoanFinancialSummary;
use App\Models\LoanStatusHistory;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanService
{
    public function __construct(
        protected readonly LoanCalendarService $calendarService,
        protected readonly LoanSimulationService $simulationService,
        protected readonly LoanPdfGenerationService $pdfService,
    ) {}

    /**
     * Create a new loan in draft status.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createDraft(User $user, array $data): Loan
    {
        if (! $this->calendarService->isApplicationWindowOpen()) {
            throw ValidationException::withMessages([
                'loan' => 'El periodo de recepción de solicitudes de préstamos ha concluido por este año (fecha límite: 30 de noviembre).',
            ]);
        }

        // Check if user already has an active loan unless an authorized exception applies
        $hasActive = Loan::forUser($user)->active()->exists();
        if ($hasActive && empty($data['exception_active_loan'])) {
            throw ValidationException::withMessages([
                'loan' => 'Actualmente ya cuenta con una solicitud de préstamo activa en el sistema.',
            ]);
        }

        return DB::transaction(function () use ($user, $data) {
            $paymentType = LoanPaymentType::from($data['payment_type']);
            $nominaSchedule = $paymentType->hasNomina()
                ? $this->resolveNominaSchedule((int) ($data['nomina_fortnights'] ?? 0))
                : null;

            $loan = Loan::create([
                'folio' => Loan::generateFolio(),
                'user_id' => $user->id,
                'status' => LoanStatus::Borrador,
                'payment_type' => $paymentType,
                'requested_amount' => (float) $data['requested_amount'],
                'monthly_rate' => isset($data['monthly_rate']) && $data['monthly_rate'] !== null ? (float) $data['monthly_rate'] : null,
                'salary_commitment_amount' => isset($data['salary_commitment_amount']) ? (float) $data['salary_commitment_amount'] : null,
                'nomina_amount' => $paymentType->hasNomina() ? (float) ($data['nomina_amount'] ?? 0) : null,
                'nomina_fortnights' => $nominaSchedule['fortnights'] ?? null,
                'first_fortnight_date' => $nominaSchedule['first_fortnight'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account' => $data['bank_account'] ?? null,
                'interbank_clabe' => $data['interbank_clabe'] ?? null,
                'exception_active_loan' => (bool) ($data['exception_active_loan'] ?? false),
                'exception_authorized_by' => $data['exception_authorized_by'] ?? null,
            ]);

            if ($paymentType->hasBenefits() && ! empty($data['benefits'])) {
                foreach ($data['benefits'] as $b) {
                    $benefit = Benefit::findOrFail((int) $b['benefit_id']);
                    $collectionDate = $benefit->collectionDateForYear(now()->year);

                    LoanBenefit::create([
                        'loan_id' => $loan->id,
                        'benefit_id' => $benefit->id,
                        'requested_amount' => (float) $b['amount'],
                        'collection_date' => $collectionDate->format('Y-m-d'),
                    ]);
                }
            }

            LoanStatusHistory::create([
                'loan_id' => $loan->id,
                'from_status' => null,
                'to_status' => LoanStatus::Borrador,
                'user_id' => $user->id,
                'comment' => 'Solicitud iniciada en borrador',
            ]);

            return $loan;
        });
    }

    /**
     * Get the latest bank details captured by the user for future applications.
     *
     * @return array{bank_name: ?string, bank_account: ?string, interbank_clabe: ?string}
     */
    public function latestBankDetails(User $user): array
    {
        $loan = Loan::forUser($user)
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('bank_name')
                    ->orWhereNotNull('bank_account')
                    ->orWhereNotNull('interbank_clabe');
            })
            ->latest('id')
            ->first(['bank_name', 'bank_account', 'interbank_clabe']);

        return [
            'bank_name' => $loan?->bank_name,
            'bank_account' => $loan?->bank_account,
            'interbank_clabe' => $loan?->interbank_clabe,
        ];
    }

    /**
     * Update an existing draft loan.
     * Invalidates the signature if any financial terms changed.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateDraft(Loan $loan, array $data): Loan
    {
        if ($loan->status !== LoanStatus::Borrador) {
            throw ValidationException::withMessages([
                'loan' => 'Solo se pueden editar solicitudes en estado borrador.',
            ]);
        }

        return DB::transaction(function () use ($loan, $data) {
            $paymentType = isset($data['payment_type'])
                ? LoanPaymentType::from($data['payment_type'])
                : $loan->payment_type;
            $nominaSchedule = $paymentType->hasNomina()
                ? $this->resolveNominaSchedule((int) ($data['nomina_fortnights'] ?? $loan->nomina_fortnights ?? 0))
                : null;

            $loan->update([
                'payment_type' => $paymentType,
                'requested_amount' => (float) ($data['requested_amount'] ?? $loan->requested_amount),
                'monthly_rate' => array_key_exists('monthly_rate', $data)
                    ? ($data['monthly_rate'] !== null ? (float) $data['monthly_rate'] : null)
                    : $loan->monthly_rate,
                'salary_commitment_amount' => array_key_exists('salary_commitment_amount', $data)
                    ? (float) $data['salary_commitment_amount']
                    : $loan->salary_commitment_amount,
                'nomina_amount' => $paymentType->hasNomina()
                    ? (float) ($data['nomina_amount'] ?? $loan->nomina_amount)
                    : null,
                'nomina_fortnights' => $nominaSchedule['fortnights'] ?? null,
                'first_fortnight_date' => $nominaSchedule['first_fortnight'] ?? null,
                'bank_name' => $data['bank_name'] ?? $loan->bank_name,
                'bank_account' => $data['bank_account'] ?? $loan->bank_account,
                'interbank_clabe' => $data['interbank_clabe'] ?? $loan->interbank_clabe,
            ]);

            if ($paymentType->hasBenefits() && isset($data['benefits'])) {
                $loan->loanBenefits()->delete();
                foreach ($data['benefits'] as $b) {
                    $benefit = Benefit::findOrFail((int) $b['benefit_id']);
                    $collectionDate = $benefit->collectionDateForYear(now()->year);

                    LoanBenefit::create([
                        'loan_id' => $loan->id,
                        'benefit_id' => $benefit->id,
                        'requested_amount' => (float) $b['amount'],
                        'collection_date' => $collectionDate->format('Y-m-d'),
                    ]);
                }

            } elseif (! $paymentType->hasBenefits()) {
                $loan->loanBenefits()->delete();
            }

            // Invalidate signature on term modifications
            $loan->invalidateSignature();

            return $loan;
        });
    }

    /**
     * @return array{fortnights: int, first_fortnight: string}
     *
     * @throws ValidationException
     */
    private function resolveNominaSchedule(int $fortnights): array
    {
        $eligibleFortnights = $this->calendarService->getEligibleFortnights();
        $firstFortnight = $eligibleFortnights[0]['date'] ?? null;

        if ($firstFortnight === null) {
            throw ValidationException::withMessages([
                'nomina_fortnights' => 'No hay quincenas disponibles para este año.',
            ]);
        }

        if ($fortnights < 1 || $fortnights > count($eligibleFortnights)) {
            throw ValidationException::withMessages([
                'nomina_fortnights' => 'El número de quincenas seleccionado no está disponible para este año.',
            ]);
        }

        return [
            'fortnights' => $fortnights,
            'first_fortnight' => $firstFortnight,
        ];
    }

    /**
     * Sign loan with drawn signature or prepared efirma mechanism.
     */
    public function signLoan(Loan $loan, string $signatureData, string $signatureType = 'drawn'): Loan
    {
        if ($loan->status !== LoanStatus::Borrador) {
            throw new LoanTransitionException('Solo es posible firmar una solicitud en borrador.', 422);
        }

        $hash = $loan->calculateTermsHash();

        $loan->update([
            'signature_data' => $signatureData,
            'signature_type' => $signatureType,
            'signature_hash' => $hash,
            'signed_at' => now(),
        ]);

        return $loan;
    }

    /**
     * Submit draft loan for review.
     *
     * @throws ValidationException|LoanTransitionException
     */
    public function submitLoan(Loan $loan, ?User $actor = null): Loan
    {
        $actor ??= $loan->user;

        if (! $this->calendarService->isApplicationWindowOpen()) {
            throw ValidationException::withMessages([
                'loan' => 'El periodo de solicitudes concluyó el 30 de noviembre.',
            ]);
        }

        return DB::transaction(function () use ($loan, $actor) {
            /** @var Loan $locked */
            $locked = Loan::where('id', $loan->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== LoanStatus::Borrador) {
                throw new LoanTransitionException('La solicitud ya no está en borrador. Estado actual: '.$locked->status->getLabel().'.');
            }

            if (! $locked->isSignatureValid()) {
                throw ValidationException::withMessages([
                    'signature' => 'La firma es obligatoria y debe corresponder a los términos actuales de la solicitud.',
                ]);
            }

            // Check evidence completeness
            $evidences = $locked->evidences;
            $hasNomina = $locked->payment_type->hasNomina();
            $hasBenefits = $locked->payment_type->hasBenefits();

            if ($hasNomina && ! $evidences->contains('source_type', 'nomina')) {
                throw ValidationException::withMessages([
                    'evidence' => 'Falta subir el comprobante de nómina.',
                ]);
            }

            if ($hasBenefits) {
                $requiredBenefitIds = $locked->loanBenefits->pluck('benefit_id')->all();
                $uploadedBenefitIds = $evidences->where('source_type', 'benefit')->pluck('benefit_id')->filter()->all();
                $missing = array_diff($requiredBenefitIds, $uploadedBenefitIds);
                if (! empty($missing)) {
                    throw ValidationException::withMessages([
                        'evidence' => 'Faltan comprobantes para una o más prestaciones seleccionadas.',
                    ]);
                }
            }

            $locked->update([
                'status' => LoanStatus::Enviada,
                'submitted_at' => now(),
            ]);

            LoanStatusHistory::create([
                'loan_id' => $locked->id,
                'from_status' => LoanStatus::Borrador,
                'to_status' => LoanStatus::Enviada,
                'user_id' => $actor->id,
                'comment' => 'Solicitud enviada a revisión formal',
            ]);

            return $locked;
        });
    }

    /**
     * Transition loan to "En Revisión".
     */
    public function markInReview(Loan $loan, User $reviewer): Loan
    {
        return DB::transaction(function () use ($loan, $reviewer) {
            /** @var Loan $locked */
            $locked = Loan::where('id', $loan->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [LoanStatus::Enviada, LoanStatus::ListaEspera], true)) {
                throw new LoanTransitionException('No se puede pasar a revisión desde el estado: '.$locked->status->getLabel());
            }

            $fromStatus = $locked->status;
            $locked->update([
                'status' => LoanStatus::EnRevision,
                'reviewed_by' => $reviewer->id,
            ]);

            LoanStatusHistory::create([
                'loan_id' => $locked->id,
                'from_status' => $fromStatus,
                'to_status' => LoanStatus::EnRevision,
                'user_id' => $reviewer->id,
                'comment' => 'Revisión técnica y documental iniciada por administración',
            ]);

            return $locked;
        });
    }

    /**
     * Approve loan, persist financial corrida, and trigger PDF generation.
     *
     * @param  array<string, mixed>  $params  May include approved_amount, monthly_rate, nomina_amount, nomina_fortnights, comment, benefits
     *
     * @throws ValidationException|LoanTransitionException
     */
    public function approve(Loan $loan, User $reviewer, array $params = []): Loan
    {
        return DB::transaction(function () use ($loan, $reviewer, $params) {
            /** @var Loan $locked */
            $locked = Loan::where('id', $loan->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [LoanStatus::Enviada, LoanStatus::EnRevision, LoanStatus::ListaEspera], true)) {
                throw new LoanTransitionException('La solicitud no está disponible para aprobación (estado actual: '.$locked->status->getLabel().').');
            }

            // Dossier completeness check: every evidence must be valid or approved
            if (! $locked->hasCompleteEvidence()) {
                throw ValidationException::withMessages([
                    'evidence' => 'No se puede aprobar la solicitud: el expediente documental no está completo o tiene documentos no válidos.',
                ]);
            }

            $fromStatus = $locked->status;

            // Apply reviewer adjustments
            $approvedAmount = isset($params['approved_amount']) ? (float) $params['approved_amount'] : (float) $locked->requested_amount;
            $monthlyRate = isset($params['monthly_rate']) && $params['monthly_rate'] !== null
                ? (float) $params['monthly_rate']
                : (float) ($locked->monthly_rate ?? 6.0);

            $nominaAmount = $locked->payment_type->hasNomina()
                ? (isset($params['nomina_amount']) ? (float) $params['nomina_amount'] : (float) ($locked->nomina_amount ?? 0))
                : null;

            $nominaFortnights = $locked->payment_type->hasNomina()
                ? (isset($params['nomina_fortnights']) ? (int) $params['nomina_fortnights'] : (int) ($locked->nomina_fortnights ?? 0))
                : null;

            $locked->update([
                'status' => LoanStatus::Aprobada,
                'approved_amount' => $approvedAmount,
                'monthly_rate' => $monthlyRate,
                'nomina_amount' => $nominaAmount,
                'nomina_fortnights' => $nominaFortnights,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            // Adjust benefit amounts if specified
            if ($locked->payment_type->hasBenefits() && ! empty($params['benefits'])) {
                foreach ($params['benefits'] as $b) {
                    $lb = $locked->loanBenefits()->where('benefit_id', (int) $b['benefit_id'])->first();
                    if ($lb) {
                        $lb->update([
                            'approved_amount' => (float) ($b['approved_amount'] ?? $lb->requested_amount),
                        ]);
                    }
                }
            } else {
                foreach ($locked->loanBenefits as $lb) {
                    $lb->update(['approved_amount' => $lb->requested_amount]);
                }
            }

            // Build and persist initial amortization table and financial summary
            $this->persistAmortizationAndSummary($locked, now()->format('Y-m-d'));

            LoanStatusHistory::create([
                'loan_id' => $locked->id,
                'from_status' => $fromStatus,
                'to_status' => LoanStatus::Aprobada,
                'user_id' => $reviewer->id,
                'comment' => $params['comment'] ?? 'Solicitud aprobada por la administración.',
                'metadata' => [
                    'approved_amount' => $approvedAmount,
                    'monthly_rate' => $monthlyRate,
                ],
            ]);

            // Generate documents — PDF failure must NOT rollback approval
            $this->pdfService->generateAllDocuments($locked, $reviewer);

            DB::afterCommit(function () use ($locked) {
                Notification::make()
                    ->title('¡Solicitud de préstamo aprobada!')
                    ->body("Tu solicitud {$locked->folio} por un monto de $ ".number_format($locked->approved_amount, 2).' ha sido autorizada.')
                    ->success()
                    ->sendToDatabase($locked->user);
            });

            return $locked;
        });
    }

    /**
     * Put loan on waitlist with mandatory justification.
     */
    public function waitlist(Loan $loan, User $reviewer, string $reason): Loan
    {
        return DB::transaction(function () use ($loan, $reviewer, $reason) {
            /** @var Loan $locked */
            $locked = Loan::where('id', $loan->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [LoanStatus::Enviada, LoanStatus::EnRevision], true)) {
                throw new LoanTransitionException('Solo se pueden poner en lista de espera solicitudes enviadas o en revisión.');
            }

            $fromStatus = $locked->status;
            $locked->update([
                'status' => LoanStatus::ListaEspera,
                'waitlist_reason' => $reason,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            LoanStatusHistory::create([
                'loan_id' => $locked->id,
                'from_status' => $fromStatus,
                'to_status' => LoanStatus::ListaEspera,
                'user_id' => $reviewer->id,
                'comment' => $reason,
            ]);

            DB::afterCommit(function () use ($locked) {
                Notification::make()
                    ->title('Solicitud en lista de espera')
                    ->body("Tu solicitud {$locked->folio} está en lista de espera.")
                    ->warning()
                    ->sendToDatabase($locked->user);
            });

            return $locked;
        });
    }

    /**
     * Reject loan with mandatory justification.
     */
    public function reject(Loan $loan, User $reviewer, string $reason): Loan
    {
        return DB::transaction(function () use ($loan, $reviewer, $reason) {
            /** @var Loan $locked */
            $locked = Loan::where('id', $loan->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [LoanStatus::Enviada, LoanStatus::EnRevision, LoanStatus::ListaEspera], true)) {
                throw new LoanTransitionException('No se puede rechazar una solicitud en estado: '.$locked->status->getLabel());
            }

            $fromStatus = $locked->status;
            $locked->update([
                'status' => LoanStatus::Rechazada,
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            LoanStatusHistory::create([
                'loan_id' => $locked->id,
                'from_status' => $fromStatus,
                'to_status' => LoanStatus::Rechazada,
                'user_id' => $reviewer->id,
                'comment' => $reason,
            ]);

            DB::afterCommit(function () use ($locked, $reason) {
                Notification::make()
                    ->title('Solicitud de préstamo rechazada')
                    ->body("Tu solicitud {$locked->folio} fue rechazada. Motivo: {$reason}")
                    ->danger()
                    ->sendToDatabase($locked->user);
            });

            return $locked;
        });
    }

    /**
     * Disburse an approved loan.
     *
     * @param  array<string, mixed>  $data  Keys: disbursed_amount, disbursement_method, disbursement_reference, disbursed_at, receipt_path
     *
     * @throws ValidationException|LoanTransitionException
     */
    public function disburse(Loan $loan, User $reviewer, array $data): Loan
    {
        return DB::transaction(function () use ($loan, $reviewer, $data) {
            /** @var Loan $locked */
            $locked = Loan::where('id', $loan->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== LoanStatus::Aprobada) {
                throw new LoanTransitionException('Solo se pueden desembolsar préstamos en estado aprobado (actual: '.$locked->status->getLabel().').');
            }

            $disbursedAt = Carbon::parse($data['disbursed_at'] ?? now())->format('Y-m-d');
            $disbursedAmount = isset($data['disbursed_amount']) ? (float) $data['disbursed_amount'] : (float) $locked->approved_amount;
            $method = LoanPaymentType::from($locked->payment_type->value);

            $locked->update([
                'status' => LoanStatus::Desembolsada,
                'disbursed_at' => $disbursedAt,
                'disbursed_amount' => $disbursedAmount,
                'disbursement_method' => $data['disbursement_method'] ?? 'transferencia',
                'disbursement_reference' => $data['disbursement_reference'] ?? null,
                'disbursed_by' => $reviewer->id,
                'disbursement_receipt_path' => $data['receipt_path'] ?? null,
            ]);

            // Recompute amortization from actual disbursement date
            $this->persistAmortizationAndSummary($locked, $disbursedAt);

            LoanStatusHistory::create([
                'loan_id' => $locked->id,
                'from_status' => LoanStatus::Aprobada,
                'to_status' => LoanStatus::Desembolsada,
                'user_id' => $reviewer->id,
                'comment' => 'Préstamo desembolsado exitosamente.',
                'metadata' => [
                    'disbursed_at' => $disbursedAt,
                    'disbursed_amount' => $disbursedAmount,
                    'method' => $locked->disbursement_method,
                    'reference' => $locked->disbursement_reference,
                ],
            ]);

            // Regenerate PDFs to include actual disbursement date and corrida
            $this->pdfService->generateAllDocuments($locked, $reviewer);

            DB::afterCommit(function () use ($locked) {
                Notification::make()
                    ->title('¡Préstamo desembolsado!')
                    ->body("El importe de tu préstamo {$locked->folio} por $ ".number_format($locked->disbursed_amount, 2).' ha sido desembolsado.')
                    ->success()
                    ->sendToDatabase($locked->user);
            });

            return $locked;
        });
    }

    /**
     * Manually review and approve/reject an uploaded evidence document.
     */
    public function reviewEvidence(LoanEvidence $evidence, User $reviewer, string $status, ?string $notes = null): LoanEvidence
    {
        $evidence->update([
            'manual_review_status' => $status,
            'manual_review_notes' => $notes,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        return $evidence;
    }

    /**
     * Persist or update amortization table rows and financial summary.
     */
    protected function persistAmortizationAndSummary(Loan $loan, string $disbursementDate): void
    {
        $params = [
            'monthly_rate' => (float) $loan->monthly_rate / 100,
            'disbursement_date' => $disbursementDate,
            'payment_methods' => [],
        ];

        if ($loan->payment_type->hasNomina()) {
            $params['payment_methods']['nomina'] = ['amount' => (float) ($loan->nomina_amount ?? $loan->approved_amount ?? $loan->requested_amount),
                'fortnights' => (int) ($loan->nomina_fortnights ?? 0),
                'first_fortnight' => $loan->first_fortnight_date?->format('Y-m-d') ?? now()->addDays(25)->format('Y-m-d'),
            ];
        }

        if ($loan->payment_type->hasBenefits()) {
            $benefits = [];
            foreach ($loan->loanBenefits as $lb) {
                $benefits[] = [
                    'benefit_id' => $lb->benefit_id,
                    'name' => $lb->benefit->name ?? 'Prestación',
                    'amount' => (float) ($lb->approved_amount ?? $lb->requested_amount),
                    'collection_date' => $lb->collection_date?->format('Y-m-d') ?? (string) $lb->collection_date,
                ];
            }
            $params['payment_methods']['benefits'] = $benefits;
        }

        $table = $this->simulationService->buildAmortizationTable($params);
        $summary = $this->simulationService->buildFinancialRunSummary($params);

        // Replace amortization rows
        $loan->amortizationRows()->delete();
        foreach ($table as $row) {
            LoanAmortizationRow::create([
                'loan_id' => $loan->id,
                'period' => $row['period'],
                'type' => $row['type'],
                'label' => $row['label'],
                'date' => $row['date'],
                'opening_balance' => (float) $row['opening_balance'],
                'principal' => (float) $row['principal'],
                'interest' => (float) $row['interest'],
                'payment' => (float) $row['payment'],
                'closing_balance' => (float) $row['closing_balance'],
            ]);
        }

        // Replace summary
        $loan->financialSummary()->delete();
        LoanFinancialSummary::create([
            'loan_id' => $loan->id,
            'total_principal' => (float) $summary['total_principal'],
            'total_interest' => (float) $summary['total_interest'],
            'total_payment' => (float) $summary['total_payment'],
            'disbursement_date' => $disbursementDate,
            'fortnights' => $summary['fortnights'],
            'first_fortnight' => $summary['first_fortnight'],
            'last_fortnight' => $summary['last_fortnight'],
        ]);
    }
}
