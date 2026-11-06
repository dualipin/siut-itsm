<?php

namespace App\Models;

use App\Enums\DisbursementMethod;
use App\Enums\LoanPaymentType;
use App\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'folio',
        'user_id',
        'status',
        'payment_type',
        'requested_amount',
        'approved_amount',
        'monthly_rate',
        'salary_commitment_amount',
        'nomina_amount',
        'nomina_fortnights',
        'first_fortnight_date',
        'signature_data',
        'signature_type',
        'signature_hash',
        'signed_at',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'waitlist_reason',
        'documents_generated',
        'pdf_generation_errors',
        'disbursed_at',
        'disbursed_amount',
        'disbursement_method',
        'disbursement_reference',
        'disbursed_by',
        'disbursement_receipt_path',
        'bank_name',
        'bank_account',
        'interbank_clabe',
        'exception_active_loan',
        'exception_authorized_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Loan $loan) {
            if (empty($loan->folio)) {
                $loan->folio = static::generateFolio();
            }
        });
    }

    public static function generateFolio(): string
    {
        $year = now()->format('Y');
        $lastId = (int) (static::whereYear('created_at', $year)->max('id') ?? 0) + 1;
        $candidate = sprintf('SOL-%s-%05d', $year, $lastId);
        $count = 1;

        while (static::where('folio', $candidate)->exists()) {
            $candidate = sprintf('SOL-%s-%05d', $year, $lastId + $count);
            $count++;
        }

        return $candidate;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
            'payment_type' => LoanPaymentType::class,
            'requested_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'monthly_rate' => 'decimal:2',
            'salary_commitment_amount' => 'decimal:2',
            'nomina_amount' => 'decimal:2',
            'nomina_fortnights' => 'integer',
            'first_fortnight_date' => 'date',
            'signed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'documents_generated' => 'boolean',
            'pdf_generation_errors' => 'array',
            'disbursed_at' => 'date',
            'disbursed_amount' => 'decimal:2',
            'disbursement_method' => DisbursementMethod::class,
            'exception_active_loan' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function disburser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function exceptionAuthorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exception_authorized_by');
    }

    public function efirmaSignature(): HasOne
    {
        return $this->hasOne(LoanEfirmaSignature::class);
    }

    public function loanBenefits(): HasMany
    {
        return $this->hasMany(LoanBenefit::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(LoanEvidence::class);
    }

    public function amortizationRows(): HasMany
    {
        return $this->hasMany(LoanAmortizationRow::class)->orderBy('period');
    }

    public function financialSummary(): HasOne
    {
        return $this->hasOne(LoanFinancialSummary::class);
    }

    public function generatedDocuments(): HasMany
    {
        return $this->hasMany(LoanGeneratedDocument::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LoanStatusHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * Scope for a specific user.
     */
    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        $id = $user instanceof User ? $user->id : $user;

        return $query->where('user_id', $id);
    }

    /**
     * Scope for active loans: borrador, enviada, en_revision, aprobada, lista_espera, desembolsada.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            LoanStatus::Borrador,
            LoanStatus::Enviada,
            LoanStatus::EnRevision,
            LoanStatus::Aprobada,
            LoanStatus::ListaEspera,
            LoanStatus::Desembolsada,
        ]);
    }

    /**
     * Scope for pending review: enviada, en_revision.
     */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->whereIn('status', [
            LoanStatus::Enviada,
            LoanStatus::EnRevision,
        ]);
    }

    /**
     * Calculate SHA-256 hash of financial/term attributes.
     * Used to detect if terms change after signature, requiring re-signing.
     */
    public function calculateTermsHash(): string
    {
        $benefitsData = $this->loanBenefits()
            ->orderBy('benefit_id')
            ->get()
            ->map(fn (LoanBenefit $b) => [
                'benefit_id' => $b->benefit_id,
                'requested_amount' => (float) $b->requested_amount,
            ])
            ->toArray();

        $payload = [
            'requested_amount' => (float) $this->requested_amount,
            'payment_type' => $this->payment_type instanceof LoanPaymentType ? $this->payment_type->value : (string) $this->payment_type,
            'monthly_rate' => $this->monthly_rate !== null ? (float) $this->monthly_rate : null,
            'nomina_amount' => (float) ($this->nomina_amount ?? 0),
            'nomina_fortnights' => (int) ($this->nomina_fortnights ?? 0),
            'first_fortnight_date' => $this->first_fortnight_date?->format('Y-m-d') ?? (string) $this->first_fortnight_date,
            'benefits' => $benefitsData,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Check if signature is currently valid against loan terms.
     */
    public function isSignatureValid(): bool
    {
        if (blank($this->signature_data) || $this->signed_at === null || blank($this->signature_hash)) {
            return false;
        }

        return hash_equals($this->signature_hash, $this->calculateTermsHash());
    }

    public function invalidateSignature(): void
    {
        $this->update([
            'signature_data' => null,
            'signature_hash' => null,
            'signed_at' => null,
        ]);
    }

    /**
     * Check if all required payment sources have evidence uploaded and either
     * marked as valid by AI or approved through manual review.
     */
    public function hasCompleteEvidence(): bool
    {
        $evidences = $this->evidences()->get();

        if ($this->payment_type->hasNomina()) {
            $nominaEvidence = $evidences->firstWhere('source_type', 'nomina');
            if (! $nominaEvidence || ! $nominaEvidence->isValidOrApproved()) {
                return false;
            }
        }

        if ($this->payment_type->hasBenefits()) {
            $requiredBenefitIds = $this->loanBenefits()->pluck('benefit_id');
            foreach ($requiredBenefitIds as $benefitId) {
                $benefitEvidence = $evidences->where('source_type', 'benefit')->firstWhere('benefit_id', $benefitId);
                if (! $benefitEvidence || ! $benefitEvidence->isValidOrApproved()) {
                    return false;
                }
            }
        }

        return true;
    }
}
