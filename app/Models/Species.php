<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Species extends Model
{
    use HasFactory;

    // "species" is already plural; stop Eloquent guessing "specie".
    protected $table = 'species';

    protected $fillable = [
        'name_de', 'name_latin', 'wiki_title', 'habitat', 'warning_text',
        'image_url', 'kobo_value', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function vorgaenge(): HasMany
    {
        return $this->hasMany(Vorgang::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function wikipediaUrl(): ?string
    {
        return $this->wiki_title
            ? 'https://de.wikipedia.org/wiki/'.rawurlencode($this->wiki_title)
            : null;
    }
}
