<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutgoingEmail extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'recipient', 'subject', 'body_html', 'email_template_id',
        'notification_rule_id', 'vorgang_id', 'status', 'error', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(NotificationRule::class, 'notification_rule_id');
    }

    public function vorgang(): BelongsTo
    {
        return $this->belongsTo(Vorgang::class);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function markSent(): void
    {
        $this->forceFill(['status' => self::STATUS_SENT, 'sent_at' => now(), 'error' => null])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill(['status' => self::STATUS_FAILED, 'error' => $error])->save();
    }
}
