<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanFinancialSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'total_principal',
        'total_interest',
        'total_payment',
        'disbursement_date',
        'fortnights',
        'first_fortnight',
        'last_fortnight',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_principal' => 'decimal:2',
            'total_interest' => 'decimal:2',
            'total_payment' => 'decimal:2',
            'disbursement_date' => 'date',
            'fortnights' => 'integer',
            'first_fortnight' => 'date',
            'last_fortnight' => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
