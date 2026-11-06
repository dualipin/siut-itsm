<?php

namespace App\Models;

use App\Enums\LoanGeneratedDocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanGeneratedDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'document_type',
        'version',
        'file_path',
        'file_hash',
        'file_size',
        'generated_by',
        'generated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => LoanGeneratedDocumentType::class,
            'version' => 'integer',
            'file_size' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
