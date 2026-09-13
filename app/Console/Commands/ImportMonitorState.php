<?php

namespace App\Console\Commands;

use App\Models\Species;
use App\Models\User;
use App\Models\Vorgang;
use App\Models\WorkflowStatus;
use App\Support\ActionNeeded;
use App\Support\Priority;
use App\Support\RiskAssessment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Imports invasive-pflanzen-stand.json - the saved state of the prototype
 * monitor application - so existing cases and the office work already done on
 * them survive the move to Laravel.
 *
 * Idempotent: a record already imported (matched on its Kobo submission id) is
 * updated rather than duplicated.
 */
class ImportMonitorState extends Command
{
    protected $signature = 'meldestelle:import-stand
                            {file : Pfad zur invasive-pflanzen-stand.json}
                            {--dry-run : Nur anzeigen, nichts speichern}';

    protected $description = 'Importiert den Stand der bisherigen Monitor-Anwendung';

    /** Legacy status label => workflow_statuses.key */
    private const STATUS_MAP = [
        'Neu' => 'neu',
        'In Prüfung' => 'inpruefung',
        'Bestätigt' => 'bestaetigt',
        'In Bearbeitung' => 'inbearbeitung',
        'Bekämpft' => 'bekaempft',
        'Kein Handlungsbedarf' => 'keinhandlungsbedarf',
    ];

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("Datei nicht gefunden: {$path}");

            return self::FAILURE;
        }

        try {
            $payload = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $this->error('Datei ist kein gültiges JSON: '.$e->getMessage());

            return self::FAILURE;
        }

        $records = $payload['records'] ?? null;

        if (! is_array($records) || $records === []) {
            $this->error('Die Datei enthält keinen "records"-Abschnitt.');

            return self::FAILURE;
        }

        $statuses = WorkflowStatus::query()->pluck('id', 'key');

        if ($statuses->isEmpty()) {
            $this->error('Es sind keine Workflow-Status angelegt. Bitte zuerst db:seed ausführen.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $created = $updated = $skipped = 0;

        // Oldest first, so local_nr is handed out in the original order.
        $sorted = collect($records)->sortBy(fn (array $r) => $r['time'] ?? '')->values();

        foreach ($sorted as $record) {
            $result = $dryRun
                ? ($this->existing($record) ? 'update' : 'create')
                : DB::transaction(fn () => $this->importOne($record, $statuses));

            match ($result) {
                'create' => $created++,
                'update' => $updated++,
                default => $skipped++,
            };
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d neu, %d aktualisiert, %d übersprungen.',
            $dryRun ? 'Probelauf' : 'Import abgeschlossen',
            $created,
            $updated,
            $skipped,
        ));

        if (! $dryRun) {
            $this->comment('Hinweis: Fotos verbleiben zunächst in KoboToolbox. '
                .'Mit einem gültigen KOBO_API_TOKEN holt "kobo:sync" sie nach.');
        }

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $record */
    private function existing(array $record): ?Vorgang
    {
        $koboId = is_numeric($record['id'] ?? null) ? (int) $record['id'] : null;
        $meldeId = $this->string($record['meldeId'] ?? null);
        $localNr = $this->string($record['localNr'] ?? null);

        return Vorgang::query()
            ->when($koboId, fn ($q) => $q->orWhere('kobo_id', $koboId))
            ->when($meldeId, fn ($q) => $q->orWhere('melde_id', $meldeId))
            ->when(! $koboId && ! $meldeId && $localNr, fn ($q) => $q->orWhere('local_nr', $localNr))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $record
     * @return 'create'|'update'|'skip'
     */
    private function importOne(array $record, mixed $statuses): string
    {
        $statusKey = self::STATUS_MAP[$record['status'] ?? 'Neu'] ?? 'neu';
        $statusId = $statuses[$statusKey] ?? $statuses['neu'];

        $editorRisk = array_values(array_filter(
            (array) ($record['editorRisk'] ?? []),
            fn ($r) => in_array($r, RiskAssessment::options(), true),
        ));

        $riskFlags = [];
        foreach (RiskAssessment::options() as $option) {
            $riskFlags[$option] = (bool) (($record['riskFlags'] ?? [])[$option] ?? false);
        }

        $contact = $this->string($record['contactInfo'] ?? null);
        $species = $this->string($record['species'] ?? null);

        $attributes = [
            'status_id' => $statusId,
            'reported_species_raw' => $species,
            'species_id' => $this->resolveSpecies($species),
            'knows_species' => $this->string($record['knows'] ?? null),
            'location_summary' => $this->string($record['locationSummary'] ?? null),
            'size_category' => $this->string($record['size'] ?? null),
            'citizen_note' => $this->string($record['citizenNote'] ?? null),
            'citizen_risk_summary' => $this->string($record['citizenRiskSummary'] ?? null),

            'risk_flags' => $riskFlags,
            'editor_risk' => $editorRisk,
            'priority' => $this->string($record['priority'] ?? null) ?: RiskAssessment::computePriority($editorRisk),
            'priority_override' => $this->string($record['priorityOverride'] ?? null),

            'editor_note' => $this->string($record['editorNote'] ?? null),
            'confirmed' => (bool) ($record['confirmed'] ?? false),
            'visited' => (bool) ($record['visited'] ?? false),
            'visit_date' => $this->date($record['visitDate'] ?? null),
            'action_needed' => $this->actionNeeded($record['actionNeeded'] ?? null),
            'measure' => $this->string($record['measure'] ?? null),
            'editor_name' => $this->string($record['editorName'] ?? null),

            'geometry_type' => $this->geometryType($record),
            'latitude' => $this->float($record['lat'] ?? null),
            'longitude' => $this->float($record['lon'] ?? null),
            'geojson' => is_array($record['geometry'] ?? null) ? $record['geometry'] : null,

            'geo_label' => $this->string($record['geoLabel'] ?? null),
            'geo_plz' => $this->string($record['geoPlz'] ?? null),
            'geo_ort' => $this->string($record['geoOrt'] ?? null),
            'geo_kreis' => $this->string($record['geoKreis'] ?? null),
            'geo_failed' => (bool) ($record['geoFailed'] ?? false),

            'reporter_name' => $this->string($record['contactName'] ?? null),
            'reporter_contact_raw' => $contact,
            'reporter_email' => $this->email($contact),
            'reporter_phone' => $this->email($contact) === null ? $contact : null,
            'reporter_contact_consent' => str_starts_with((string) ($record['contactAllowed'] ?? ''), 'Ja'),

            'submitted_at' => $this->date($record['time'] ?? null),
            'assigned_to_id' => $this->resolveEditor($record['editorName'] ?? null),
        ];

        $existing = $this->existing($record);

        if ($existing !== null) {
            // Do not overwrite work done in Laravel since the import.
            $existing->fill($attributes)->save();

            return 'update';
        }

        Vorgang::create($attributes + [
            'melde_id' => $this->string($record['meldeId'] ?? null),
            // The monitor's record id is the KoboToolbox submission id; keeping
            // it lets a later kobo:sync recognise this case instead of
            // duplicating it.
            'kobo_id' => is_numeric($record['id'] ?? null) ? (int) $record['id'] : null,
            'local_nr' => $this->string($record['localNr'] ?? null) ?: Vorgang::nextLocalNr(),
        ]);

        return 'create';
    }

    /** @param array<string, mixed> $record */
    private function geometryType(array $record): ?string
    {
        return match ($record['geomKind'] ?? null) {
            'Fläche' => 'polygon',
            'Punkt' => 'point',
            default => null,
        };
    }

    private function resolveSpecies(?string $name): ?int
    {
        if (blank($name)) {
            return null;
        }

        return Species::query()
            ->where('name_de', $name)
            ->orWhere('name_latin', $name)
            ->value('id');
    }

    /** Matches a legacy free-text editor name onto a real account, if one exists. */
    private function resolveEditor(mixed $name): ?int
    {
        if (blank($name) || ! is_string($name)) {
            return null;
        }

        return User::query()->where('name', trim($name))->value('id');
    }

    private function actionNeeded(mixed $value): ?string
    {
        $value = $this->string($value);

        return $value !== null && array_key_exists($value, ActionNeeded::options()) ? $value : null;
    }

    private function email(?string $contact): ?string
    {
        if (blank($contact)) {
            return null;
        }

        return filter_var(trim($contact), FILTER_VALIDATE_EMAIL) ? trim($contact) : null;
    }

    private function string(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function date(mixed $value): ?Carbon
    {
        if (blank($value) || ! is_string($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
