<?php

namespace App\Models;

use App\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanStatusHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'loan_id',
        'from_status',
        'to_status',
        'user_id',
        'comment',
        'metadata',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => LoanStatus::class,
            'to_status' => LoanStatus::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
