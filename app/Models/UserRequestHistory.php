<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Database\Factories\UserRequestHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRequestHistory extends Model
{
    /** @use HasFactory<UserRequestHistoryFactory> */
    use HasFactory;

    protected $fillable = [
        'user_request_id',
        'user_id',
        'from_status',
        'to_status',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => RequestStatus::class,
            'to_status' => RequestStatus::class,
        ];
    }

    /**
     * Get the request this history entry belongs to.
     *
     * @return BelongsTo<UserRequest, $this>
     */
    public function userRequest(): BelongsTo
    {
        return $this->belongsTo(UserRequest::class, 'user_request_id');
    }

    /**
     * Get the user who made the status change.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
