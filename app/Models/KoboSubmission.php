<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The verbatim KoboToolbox payload, exactly as it arrived.
 *
 * Treat every value in $payload as untrusted input from a public form.
 */
class KoboSubmission extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'asset_uid', 'kobo_id', 'kobo_uuid', 'instance_id',
        'payload', 'headers', 'submitted_at', 'received_at',
        'processed_at', 'processing_status', 'processing_error',
        'processing_attempts', 'vorgang_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'headers' => 'array',
            'submitted_at' => 'datetime',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'processing_attempts' => 'integer',
        ];
    }

    public function vorgang(): BelongsTo
    {
        return $this->belongsTo(Vorgang::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('processing_status', self::STATUS_PENDING);
    }

    /** Attachment descriptors Kobo includes with every submission. */
    public function attachmentDescriptors(): array
    {
        $attachments = $this->payload['_attachments'] ?? [];

        return is_array($attachments) ? $attachments : [];
    }

    public function markProcessed(?Vorgang $vorgang): void
    {
        $this->forceFill([
            'processing_status' => self::STATUS_PROCESSED,
            'processing_error' => null,
            'processed_at' => now(),
            'vorgang_id' => $vorgang?->getKey(),
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'processing_status' => self::STATUS_FAILED,
            'processing_error' => $error,
            'processed_at' => now(),
        ])->save();
    }
}
