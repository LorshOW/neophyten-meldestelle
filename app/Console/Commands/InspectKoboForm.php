<?php

namespace App\Console\Commands;

use App\Services\Kobo\KoboApiClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Prints the question name/label pairs of the live Kobo form.
 *
 * The reference material only proves the LABELS of most questions; a webhook
 * payload carries the NAMES. Run this once against the live asset and copy the
 * names into the `fields` map in config/kobo.php.
 */
class InspectKoboForm extends Command
{
    protected $signature = 'kobo:inspect-form {--json : Rohes Formular-JSON ausgeben}';

    protected $description = 'Zeigt die Fragenamen und Beschriftungen des Kobo-Formulars an';

    public function handle(): int
    {
        $client = KoboApiClient::fromConfig();

        if (! $client->isConfigured()) {
            $this->error('KOBO_API_TOKEN und KOBO_ASSET_UID müssen in der .env gesetzt sein.');

            return self::FAILURE;
        }

        try {
            $definition = $client->formDefinition();
        } catch (Throwable $e) {
            $this->error('Formular konnte nicht geladen werden: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $survey = $definition['content']['survey'] ?? [];

        if ($survey === []) {
            $this->warn('Das Formular enthält keine Fragen (oder die Antwort hat ein anderes Format).');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($survey as $question) {
            $type = $question['type'] ?? '';

            if (in_array($type, ['begin_group', 'end_group', 'begin_repeat', 'end_repeat'], true)) {
                continue;
            }

            $label = $question['label'] ?? [];

            $rows[] = [
                $question['name'] ?? ($question['$autoname'] ?? '—'),
                $type,
                is_array($label) ? ($label[0] ?? '') : (string) $label,
            ];
        }

        $this->table(['Name (im Webhook)', 'Typ', 'Beschriftung (im CSV-Export)'], $rows);

        $this->newLine();
        $this->info('Diese Namen gehören als erster Kandidat in die "fields"-Zuordnung in config/kobo.php.');

        return self::SUCCESS;
    }
}
