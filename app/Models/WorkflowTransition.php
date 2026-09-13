<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransition extends Model
{
    protected $fillable = [
        'from_status_id', 'to_status_id', 'label',
        'required_permission', 'requires_note', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_note' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'to_status_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Transitions available from a status, including the wildcard transitions
     * that have no from_status_id and therefore apply everywhere.
     */
    public function scopeAvailableFrom(Builder $query, ?int $statusId): Builder
    {
        return $query->where(function (Builder $q) use ($statusId): void {
            $q->whereNull('from_status_id')->orWhere('from_status_id', $statusId);
        })->where('to_status_id', '!=', $statusId);
    }
}
