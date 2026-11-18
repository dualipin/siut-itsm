<?php

namespace App\Models;

use App\Enums\LoanOperationalState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanOperationalSetting extends Model
{
    protected $fillable = [
        'state',
        'target_fiscal_year',
        'suspension_message',
        'updated_by',
        'state_changed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => LoanOperationalState::class,
            'target_fiscal_year' => 'integer',
            'state_changed_at' => 'datetime',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
