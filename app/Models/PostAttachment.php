<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PostAttachment extends Model
{
    protected $fillable = [
        'post_id',
        'file_name',
        'file_type',
        'file_path',
    ];

    protected static function booted(): void
    {
        static::saving(function (PostAttachment $attachment): void {
            if ($attachment->file_path) {
                $attachment->file_type = Storage::disk('public')->mimeType($attachment->file_path)
                    ?? pathinfo($attachment->file_path, PATHINFO_EXTENSION)
                    ?? 'unknown';
            }
        });
    }

    /**
     * Get the post that owns the attachment.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
