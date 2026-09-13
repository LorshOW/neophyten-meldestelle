<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationRule extends Model
{
    public const EVENT_VORGANG_CREATED = 'vorgang_created';
    public const EVENT_STATUS_CHANGED = 'status_changed';
    public const EVENT_VORGANG_ASSIGNED = 'vorgang_assigned';
    public const EVENT_INSPECTION_COMPLETED = 'inspection_completed';

    public const RECIPIENT_REPORTER = 'reporter';
    public const RECIPIENT_ASSIGNEE = 'assignee';
    public const RECIPIENT_ROLE = 'role';
    public const RECIPIENT_FIXED = 'fixed';

    protected $fillable = [
        'name', 'trigger_event', 'status_id', 'email_template_id',
        'recipient_type', 'recipient_value', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent(Builder $query, string $event): Builder
    {
        return $query->where('trigger_event', $event);
    }

    public static function eventOptions(): array
    {
        return [
            self::EVENT_VORGANG_CREATED => 'Neue Meldung eingegangen',
            self::EVENT_STATUS_CHANGED => 'Status geändert',
            self::EVENT_VORGANG_ASSIGNED => 'Vorgang zugewiesen',
            self::EVENT_INSPECTION_COMPLETED => 'Vor-Ort-Kontrolle abgeschlossen',
        ];
    }

    public static function recipientOptions(): array
    {
        return [
            self::RECIPIENT_REPORTER => 'Meldende Person',
            self::RECIPIENT_ASSIGNEE => 'Zugewiesene:r Bearbeiter:in',
            self::RECIPIENT_ROLE => 'Alle Nutzer einer Rolle',
            self::RECIPIENT_FIXED => 'Feste E-Mail-Adresse',
        ];
    }
}
