<?php

namespace App\Models;

use App\Enums\ContactSubmissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'ip_address',
        'read_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactSubmissionStatus::class,
            'read_at' => 'datetime',
        ];
    }

    /**
     * Get all replies for this contact submission.
     *
     * @return HasMany<ContactReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ContactReply::class, 'contact_submission_id');
    }

    /**
     * Determine if submission is pending.
     */
    public function isPending(): bool
    {
        return $this->status === ContactSubmissionStatus::Pending;
    }

    /**
     * Determine if submission is replied.
     */
    public function isReplied(): bool
    {
        return $this->status === ContactSubmissionStatus::Replied;
    }

    /**
     * Mark the submission as read.
     */
    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }
}
