<?php

namespace App\Models;

use App\Support\Priority;
use App\Support\RiskAssessment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * A case. One Kobo submission, turned into something the office can work on.
 *
 * Field names follow the reference monitor application so imported records keep
 * their meaning. The status is never assigned directly - every change goes
 * through App\Services\Workflow\WorkflowService so the guards and the audit
 * trail cannot be bypassed.
 */
class Vorgang extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'vorgaenge';

    protected $fillable = [
        'local_nr', 'melde_id', 'kobo_id', 'kobo_submission_id', 'status_id',
        'reported_species_raw', 'species_id', 'knows_species',
        'location_summary', 'size_category', 'citizen_note', 'citizen_risk_summary',
        'risk_flags', 'editor_risk', 'priority', 'priority_override',
        'editor_note', 'confirmed', 'visited', 'visit_date',
        'action_needed', 'measure', 'editor_name',
        'geometry_type', 'latitude', 'longitude', 'geojson',
        'geo_label', 'geo_plz', 'geo_ort', 'geo_kreis', 'geo_failed',
        'reporter_name', 'reporter_contact_raw', 'reporter_email', 'reporter_phone',
        'reporter_contact_consent',
        'submitted_at', 'captured_offline',
        'assigned_to_id', 'assigned_by_id', 'assigned_at', 'due_date',
        'closed_at', 'closed_by_id',
    ];

    protected function casts(): array
    {
        return [
            'risk_flags' => 'array',
            'editor_risk' => 'array',
            'geojson' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'confirmed' => 'boolean',
            'visited' => 'boolean',
            'geo_failed' => 'boolean',
            'reporter_contact_consent' => 'boolean',
            'captured_offline' => 'boolean',
            'visit_date' => 'date',
            'submitted_at' => 'datetime',
            'assigned_at' => 'datetime',
            'due_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The computed priority must never drift from the office assessment,
        // so it is recalculated whenever that assessment changes.
        static::saving(function (self $vorgang): void {
            if ($vorgang->isDirty('editor_risk')) {
                $vorgang->priority = RiskAssessment::computePriority($vorgang->editor_risk);
            }
        });
    }

    // ---------------------------------------------------------------
    // Beziehungen
    // ---------------------------------------------------------------

    public function status(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'status_id');
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    public function koboSubmission(): BelongsTo
    {
        return $this->belongsTo(KoboSubmission::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class)->latest('inspected_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(VorgangNote::class)->latest();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(VorgangStatusHistory::class)->latest();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function outgoingEmails(): HasMany
    {
        return $this->hasMany(OutgoingEmail::class)->latest();
    }

    // ---------------------------------------------------------------
    // Abfragen
    // ---------------------------------------------------------------

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to_id', $userId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereHas('status', fn (Builder $q) => $q->where('is_terminal', false));
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to_id');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('due_date')->whereDate('due_date', '<', now());
    }

    /** Matches the monitor's priority filter, which uses the effective value. */
    public function scopeWithEffectivePriority(Builder $query, string $priority): Builder
    {
        return $query->where(function (Builder $q) use ($priority): void {
            $q->where('priority_override', $priority)
                ->orWhere(function (Builder $inner) use ($priority): void {
                    $inner->where('priority', $priority)
                        ->where(fn (Builder $b) => $b->whereNull('priority_override')->orWhere('priority_override', ''));
                });
        });
    }

    public function scopeAwaitingGeocoding(Builder $query): Builder
    {
        return $query->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNull('geo_label')
            ->where('geo_failed', false);
    }

    // ---------------------------------------------------------------
    // Abgeleitete Werte
    // ---------------------------------------------------------------

    /** effectivePriority() from the reference application. */
    public function effectivePriority(): string
    {
        return filled($this->priority_override)
            ? $this->priority_override
            : ($this->priority ?: Priority::NORMAL);
    }

    public function computedPriority(): string
    {
        return RiskAssessment::computePriority($this->editor_risk);
    }

    public function isClosed(): bool
    {
        return (bool) $this->status?->is_terminal;
    }

    public function hasCompletedInspection(): bool
    {
        return $this->inspections()->whereNotNull('completed_at')->exists();
    }

    /**
     * A reporter may only be emailed when they left an address AND agreed to be
     * contacted. The published Datenschutzerklärung limits the use of contact
     * data to exactly that.
     */
    public function canBeEmailed(): bool
    {
        return filled($this->reporter_email) && $this->reporter_contact_consent;
    }

    public function latestInspection(): ?Inspection
    {
        return $this->inspections()->first();
    }

    /** Display label: the office number, falling back to the reporter's id. */
    public function reference(): string
    {
        return $this->local_nr ?: ($this->melde_id ?: '#'.$this->getKey());
    }

    /** Generates the same id format the public map screen produces. */
    public static function generateMeldeId(): string
    {
        return 'NEO-'.strtoupper(base_convert((string) now()->getTimestampMs(), 10, 36))
            .'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
    }

    /**
     * Next sequential office number (N-000001), mirroring nextLocalNrStart()
     * and formatNr() from the reference application.
     *
     * Call inside a transaction that also inserts the row - the read and the
     * write must not interleave with another import.
     */
    public static function nextLocalNr(): string
    {
        // local_nr is zero padded to a fixed width, so the lexical maximum is
        // also the numeric maximum - no database specific CAST needed.
        $highest = DB::table('vorgaenge')->whereNotNull('local_nr')->max('local_nr');

        $current = $highest !== null && preg_match('/(\d+)$/', $highest, $m)
            ? (int) $m[1]
            : 0;

        return 'N-'.str_pad((string) ($current + 1), 6, '0', STR_PAD_LEFT);
    }
}
