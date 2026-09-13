<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VorgangNote extends Model
{
    public const VISIBILITY_INTERNAL = 'internal';
    public const VISIBILITY_AUSSENDIENST = 'aussendienst';

    protected $fillable = ['vorgang_id', 'user_id', 'body', 'visibility'];

    public function vorgang(): BelongsTo
    {
        return $this->belongsTo(Vorgang::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisibleToAussendienst(Builder $query): Builder
    {
        return $query->where('visibility', self::VISIBILITY_AUSSENDIENST);
    }

    public static function visibilityOptions(): array
    {
        return [
            self::VISIBILITY_INTERNAL => 'Nur intern',
            self::VISIBILITY_AUSSENDIENST => 'Auch für Außendienst sichtbar',
        ];
    }
}
