<?php

namespace App\Services\Kobo;

use App\Models\Species;
use App\Support\RiskAssessment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a raw KoboToolbox payload into Vorgang attributes.
 *
 * Mirrors rowsToRecords() from the reference monitor application, but reads a
 * webhook payload instead of a CSV export. Everything is defensive: the payload
 * comes from a public form, question names may be renamed or grouped in the
 * XLSForm, and offline submissions never pass through the map screen and so
 * carry none of the karten_* fields.
 */
class KoboSubmissionMapper
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function map(array $payload): array
    {
        $geometry = $this->mapGeometry($payload);
        $riskFlags = $this->mapRiskFlags($payload);

        // The office assessment starts as a copy of the citizen's answers,
        // exactly as mergeParsedRecords() does in the reference application.
        $editorRisk = array_values(array_keys(array_filter($riskFlags)));

        $contact = $this->trimmed($this->value($payload, 'reporter_contact'));

        return [
            'melde_id' => $this->meldeId($payload),

            'reported_species_raw' => $this->species($payload),
            'species_id' => $this->resolveSpeciesId($this->species($payload)),
            'knows_species' => $this->choice($payload, 'knows_species'),

            'location_summary' => $this->locationSummary($payload),
            'size_category' => $this->choice($payload, 'size_category'),
            'citizen_note' => $this->trimmed($this->value($payload, 'citizen_note')),
            'citizen_risk_summary' => $this->trimmed($this->value($payload, 'citizen_risk_summary')),

            'risk_flags' => $riskFlags,
            'editor_risk' => $editorRisk,
            'priority' => RiskAssessment::computePriority($editorRisk),

            'geometry_type' => $geometry['type'],
            'latitude' => $geometry['latitude'],
            'longitude' => $geometry['longitude'],
            'geojson' => $geometry['geojson'],

            'reporter_name' => $this->trimmed($this->value($payload, 'reporter_name')),
            'reporter_contact_raw' => $contact,
            'reporter_email' => $this->extractEmail($contact),
            'reporter_phone' => $this->extractPhone($contact),
            'reporter_contact_consent' => $this->boolean($this->value($payload, 'reporter_contact_consent')),

            'submitted_at' => $this->submittedAt($payload),
            'captured_offline' => ! $this->boolean($this->value($payload, 'captured_online')),
        ];
    }

    // -----------------------------------------------------------------
    // Geometrie
    // -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: ?string, latitude: ?float, longitude: ?float, geojson: ?array}
     */
    private function mapGeometry(array $payload): array
    {
        $geojson = $this->decodeGeojson($this->value($payload, 'geojson'));

        $latitude = $this->coordinate($this->value($payload, 'latitude'));
        $longitude = $this->coordinate($this->value($payload, 'longitude'));

        // Offline submissions skip the map screen, so fall back to the form's
        // own GPS question before giving up on a location - the reference
        // application does the same with its gpsLat/gpsLon columns.
        if ($latitude === null || $longitude === null) {
            [$latitude, $longitude] = $this->fallbackCoordinates($payload, $latitude, $longitude);
        }

        $rawType = $this->value($payload, 'geometry_type');

        // geomKind resolution from the reference application: a polygon wins,
        // then an explicit type, then the presence of coordinates.
        $type = match (true) {
            ($geojson['type'] ?? null) === 'Polygon' => 'polygon',
            ($geojson['type'] ?? null) === 'MultiPolygon' => 'polygon',
            $rawType === 'Flaeche' => 'polygon',
            ($geojson['type'] ?? null) === 'Point' => 'point',
            $rawType === 'Punkt' => 'point',
            $latitude !== null && $longitude !== null => 'point',
            default => null,
        };

        return [
            'type' => $type,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'geojson' => $geojson,
        ];
    }

    private function decodeGeojson(mixed $raw): ?array
    {
        if (blank($raw) || ! is_string($raw)) {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($decoded) || ! isset($decoded['type'], $decoded['coordinates'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: ?float, 1: ?float}
     */
    private function fallbackCoordinates(array $payload, ?float $latitude, ?float $longitude): array
    {
        // A geopoint answer is "lat lon altitude accuracy".
        $point = $this->value($payload, 'gps_point');

        if (is_string($point) && preg_match('/^\s*(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)/', $point, $m)) {
            $latitude ??= $this->coordinate($m[1]);
            $longitude ??= $this->coordinate($m[2]);
        }

        // Kobo also derives _geolocation as [lat, lon] from the geopoint.
        $geolocation = $payload['_geolocation'] ?? null;

        if (is_array($geolocation) && count($geolocation) === 2) {
            $latitude ??= $this->coordinate($geolocation[0]);
            $longitude ??= $this->coordinate($geolocation[1]);
        }

        return [$latitude, $longitude];
    }

    private function coordinate(mixed $raw): ?float
    {
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;

        // Reject obviously broken values rather than storing them.
        return abs($value) <= 180.0 ? $value : null;
    }

    // -----------------------------------------------------------------
    // Gefährdungsangaben
    // -----------------------------------------------------------------

    /**
     * The six risk questions as answered by the citizen.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, bool>
     */
    private function mapRiskFlags(array $payload): array
    {
        $selected = $this->selectedRisks($payload);

        $flags = [];

        foreach (RiskAssessment::options() as $option) {
            $flags[$option] = in_array($option, $selected, true);
        }

        return $flags;
    }

    /**
     * The six risk answers, read from whichever shape the source used.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function selectedRisks(array $payload): array
    {
        // Webhook: one space separated string of choice names.
        $raw = $this->trimmed($this->value($payload, 'risks'));

        if ($raw !== null) {
            $map = (array) config('kobo.risk_choices', []);

            $selected = array_values(array_filter(array_map(
                fn (string $name) => $map[$name] ?? null,
                preg_split('/\s+/', $raw) ?: [],
            )));

            if ($selected !== []) {
                return $selected;
            }
        }

        // CSV export: one "1"/"0" column per choice.
        $selected = [];

        foreach ((array) config('kobo.risk_columns', []) as $option => $column) {
            $value = $this->lookup($payload, $column);

            if ($value !== null && in_array((string) $value, ['1', 'true', 'ja'], true)) {
                $selected[] = $option;
            }
        }

        return $selected;
    }

    // -----------------------------------------------------------------
    // Einzelfelder
    // -----------------------------------------------------------------

    /** @param array<string, mixed> $payload */
    private function species(array $payload): ?string
    {
        return $this->choice($payload, 'species');
    }

    /**
     * The location question is a select_multiple with a free-text "other" box.
     *
     * @param  array<string, mixed>  $payload
     */
    private function locationSummary(array $payload): ?string
    {
        $selected = $this->multiChoice($payload, 'location_summary');
        $other = $this->trimmed($this->value($payload, 'location_other'));

        if ($other !== null) {
            $selected[] = $other;
        }

        return $selected === [] ? null : implode(', ', array_unique($selected));
    }

    /**
     * Reads a select_one and returns its German label.
     *
     * A webhook delivers the choice name ("kanadische_goldrute"), the CSV
     * export the label ("Kanadische Goldrute"). Unknown values are passed
     * through unchanged so a renamed choice still shows something useful.
     *
     * @param  array<string, mixed>  $payload
     */
    private function choice(array $payload, string $field): ?string
    {
        $raw = $this->trimmed($this->value($payload, $field));

        if ($raw === null) {
            return null;
        }

        $labels = (array) config("kobo.choices.{$field}", []);

        return $labels[$raw] ?? $raw;
    }

    /**
     * Reads a select_multiple. Kobo sends the selected choices as ONE space
     * separated string.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function multiChoice(array $payload, string $field): array
    {
        $raw = $this->trimmed($this->value($payload, $field));

        if ($raw === null) {
            return [];
        }

        $labels = (array) config("kobo.choices.{$field}", []);

        // A CSV export already contains labels, which may themselves contain
        // spaces - only split when the value looks like choice names.
        if (isset($labels[$raw])) {
            return [$labels[$raw]];
        }

        $parts = preg_split('/\s+/', $raw) ?: [];

        return array_values(array_filter(array_map(
            fn (string $part) => $labels[$part] ?? ($labels === [] ? $part : ($this->looksLikeChoiceName($part) ? $part : null)),
            $parts,
        )));
    }

    private function looksLikeChoiceName(string $value): bool
    {
        return (bool) preg_match('/^[a-z0-9_äöüß]+$/iu', $value);
    }

    /** @param array<string, mixed> $payload */
    private function meldeId(array $payload): ?string
    {
        $meldeId = $this->trimmed($this->value($payload, 'melde_id'));

        // Keep it recognisable but bounded - it is public input.
        return $meldeId !== null ? Str::limit($meldeId, 60, '') : null;
    }

    /** @param array<string, mixed> $payload */
    private function submittedAt(array $payload): ?Carbon
    {
        foreach (['_submission_time', 'end', 'start'] as $key) {
            $raw = $payload[$key] ?? null;

            if (blank($raw) || ! is_string($raw)) {
                continue;
            }

            try {
                return Carbon::parse($raw);
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function resolveSpeciesId(?string $raw): ?int
    {
        if (blank($raw)) {
            return null;
        }

        $value = trim($raw);

        return Species::query()
            ->where('kobo_value', $value)
            ->orWhere('name_de', $value)
            ->orWhere('name_latin', $value)
            ->value('id');
    }

    /**
     * The form asks for contact details in one free-text field, so an address
     * and a phone number arrive in the same string.
     */
    private function extractEmail(?string $contact): ?string
    {
        if (blank($contact)) {
            return null;
        }

        if (preg_match('/[^\s<>,;]+@[^\s<>,;]+\.[A-Za-z]{2,}/', $contact, $m)) {
            $candidate = rtrim($m[0], '.');

            return filter_var($candidate, FILTER_VALIDATE_EMAIL) ? $candidate : null;
        }

        return null;
    }

    private function extractPhone(?string $contact): ?string
    {
        if (blank($contact) || $this->extractEmail($contact) !== null) {
            return null;
        }

        // Enough digits to be a phone number rather than a house number.
        $digits = preg_replace('/\D+/', '', $contact) ?? '';

        return strlen($digits) >= 6 ? Str::limit(trim($contact), 60, '') : null;
    }

    private function boolean(mixed $raw): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }

        if (blank($raw)) {
            return false;
        }

        $truthy = array_map('strval', (array) config('kobo.values.truthy', []));

        return in_array(Str::lower(trim((string) $raw)), $truthy, true);
    }

    private function trimmed(mixed $raw): ?string
    {
        if ($raw === null || is_array($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        return $value === '' ? null : Str::limit($value, 2000, '');
    }

    // -----------------------------------------------------------------
    // Feldsuche
    // -----------------------------------------------------------------

    /**
     * Resolves one logical field against the candidate keys in config/kobo.php.
     *
     * @param  array<string, mixed>  $payload
     */
    private function value(array $payload, string $field): mixed
    {
        foreach ((array) config("kobo.fields.{$field}", []) as $candidate) {
            $found = $this->lookup($payload, $candidate);

            if ($found !== null && $found !== '') {
                return $found;
            }
        }

        return null;
    }

    /**
     * Exact key first, then the same key behind an XLSForm group prefix
     * ("gruppe/frage"). Array access is avoided so that dots in a label - the
     * export labels contain plenty - are not read as nested paths.
     *
     * @param  array<string, mixed>  $payload
     */
    private function lookup(array $payload, string $candidate): mixed
    {
        if (array_key_exists($candidate, $payload)) {
            return $payload[$candidate];
        }

        foreach ($payload as $key => $value) {
            if (is_string($key) && str_ends_with($key, '/'.$candidate)) {
                return $value;
            }
        }

        return null;
    }
}
