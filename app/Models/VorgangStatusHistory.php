<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only: written by WorkflowService, never edited afterwards.
 */
class VorgangStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'vorgang_id', 'from_status_id', 'to_status_id', 'user_id', 'note',
    ];

    public function vorgang(): BelongsTo
    {
        return $this->belongsTo(Vorgang::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'to_status_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
