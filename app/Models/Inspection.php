<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Inspection extends Model
{
    public const CONFIRMED = 'bestaetigt';
    public const NOT_CONFIRMED = 'nicht_bestaetigt';
    public const OTHER_SPECIES = 'andere_art';

    protected $fillable = [
        'vorgang_id', 'inspector_id', 'inspected_at', 'confirmation',
        'species_confirmed_id', 'area_estimate_sqm', 'growth_stage',
        'accessibility', 'land_owner_type', 'recommended_measure',
        'measure_urgency', 'notes', 'gps_lat', 'gps_lon', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'inspected_at' => 'datetime',
            'completed_at' => 'datetime',
            'area_estimate_sqm' => 'integer',
            'gps_lat' => 'float',
            'gps_lon' => 'float',
        ];
    }

    public function vorgang(): BelongsTo
    {
        return $this->belongsTo(Vorgang::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function speciesConfirmed(): BelongsTo
    {
        return $this->belongsTo(Species::class, 'species_confirmed_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public static function confirmationOptions(): array
    {
        return [
            self::CONFIRMED => 'Bestätigt',
            self::NOT_CONFIRMED => 'Nicht bestätigt',
            self::OTHER_SPECIES => 'Andere Art',
        ];
    }
}
