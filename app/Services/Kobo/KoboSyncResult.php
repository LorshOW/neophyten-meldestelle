<?php

namespace App\Services\Kobo;

/**
 * Outcome of one synchronisation run.
 */
class KoboSyncResult
{
    public function __construct(
        public int $fetched = 0,
        public int $imported = 0,
        public int $skipped = 0,
        public int $invalid = 0,
        public ?string $error = null,
    ) {
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }

    /** One-line summary for a notification or console output. */
    public function summary(): string
    {
        if ($this->failed()) {
            return 'Abruf fehlgeschlagen: '.$this->error;
        }

        if ($this->fetched === 0) {
            return 'KoboToolbox hat keine Meldungen zurückgegeben.';
        }

        return sprintf(
            '%d neu übernommen, %d bereits vorhanden%s (%d geprüft).',
            $this->imported,
            $this->skipped,
            $this->invalid > 0 ? ", {$this->invalid} ohne Kobo-ID übersprungen" : '',
            $this->fetched,
        );
    }
}
