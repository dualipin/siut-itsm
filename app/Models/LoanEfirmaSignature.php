<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanEfirmaSignature extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'certificate',
        'certificate_serial',
        'rfc',
        'curp',
        'signer_name',
        'valid_from',
        'valid_to',
        'original_chain',
        'seal',
        'algorithm',
        'signed_at',
        'validated_at',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'signed_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
