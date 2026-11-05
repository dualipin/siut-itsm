<?php

namespace App\Models;

use App\Enums\LoanAiStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanEvidence extends Model
{
    use HasFactory;

    protected $table = 'loan_evidences';

    protected $fillable = [
        'loan_id',
        'source_type',
        'benefit_id',
        'file_path',
        'file_hash',
        'file_name',
        'mime_type',
        'file_size',
        'ai_status',
        'ai_classification',
        'ai_confidence',
        'ai_model',
        'ai_prompt_version',
        'extracted_data',
        'comparison_data',
        'manual_review_status',
        'manual_review_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ai_status' => LoanAiStatus::class,
            'ai_confidence' => 'decimal:2',
            'extracted_data' => 'array',
            'comparison_data' => 'array',
            'file_size' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Loan, LoanEvidence>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * @return BelongsTo<Benefit, LoanEvidence>
     */
    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class);
    }

    /**
     * @return BelongsTo<User, LoanEvidence>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isValidOrApproved(): bool
    {
        return $this->isAccepted();
    }

    public function isAccepted(): bool
    {
        if ($this->manual_review_status === 'approved') {
            return true;
        }

        if ($this->manual_review_status === 'rejected') {
            return false;
        }

        return $this->ai_status === LoanAiStatus::Valido;
    }
}
