<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStatus extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'color', 'sort_order',
        'is_initial', 'is_terminal', 'requires_assignee', 'requires_inspection',
        'visible_to_aussendienst', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_initial' => 'boolean',
            'is_terminal' => 'boolean',
            'requires_assignee' => 'boolean',
            'requires_inspection' => 'boolean',
            'visible_to_aussendienst' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function vorgaenge(): HasMany
    {
        return $this->hasMany(Vorgang::class, 'status_id');
    }

    /** Transitions that lead out of this status. */
    public function transitionsFrom(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'from_status_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public static function initial(): ?self
    {
        return static::active()->where('is_initial', true)->ordered()->first();
    }
}
