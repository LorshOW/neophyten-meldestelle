<?php

namespace App\Services\Kobo;

use App\Models\Attachment;
use App\Models\KoboSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Answers "why is the webhook not delivering?" without guesswork.
 *
 * Checks the five things that actually break, in the order they break:
 * credentials, API reachability, whether KoboToolbox has a REST Service
 * pointing at THIS application, whether the endpoint is reachable from the
 * internet at all, and whether the local configuration is complete.
 */
class KoboDiagnostics
{
    public const OK = 'ok';
    public const WARN = 'warnung';
    public const FAIL = 'fehler';

    public function __construct(private readonly KoboApiClient $client)
    {
    }

    public static function fromConfig(): self
    {
        return new self(KoboApiClient::fromConfig());
    }

    /**
     * @return list<array{key: string, label: string, status: string, detail: string, hint: ?string}>
     */
    public function run(): array
    {
        $checks = [];

        $checks[] = $this->checkCredentials();
        $checks[] = $this->checkApi();
        $checks[] = $this->checkWebhookSecret();
        $checks[] = $this->checkEndpointReachable();
        $checks[] = $this->checkRestServices();
        $checks[] = $this->checkDelivery();
        $checks[] = $this->checkQueue();
        $checks[] = $this->checkAttachments();

        return $checks;
    }

    private function result(string $key, string $label, string $status, string $detail, ?string $hint = null): array
    {
        return compact('key', 'label', 'status', 'detail', 'hint');
    }

    private function checkCredentials(): array
    {
        if (! $this->client->isConfigured()) {
            return $this->result('credentials', 'Zugangsdaten', self::FAIL,
                'KOBO_API_TOKEN und/oder KOBO_ASSET_UID fehlen.',
                'In der .env eintragen oder unter /admin/einstellungen pflegen.');
        }

        return $this->result('credentials', 'Zugangsdaten', self::OK,
            'Token und Asset-UID ('.config('kobo.asset_uid').') sind gesetzt.');
    }

    private function checkApi(): array
    {
        if (! $this->client->isConfigured()) {
            return $this->result('api', 'Kobo-API', self::FAIL, 'Nicht geprüft – Zugangsdaten fehlen.');
        }

        try {
            $form = $this->client->formDefinition();
        } catch (Throwable $e) {
            return $this->result('api', 'Kobo-API', self::FAIL,
                'Formular konnte nicht geladen werden: '.$e->getMessage(),
                'Token prüfen und sicherstellen, dass KOBO_API_BASE zum richtigen Server zeigt.');
        }

        return $this->result('api', 'Kobo-API', self::OK, sprintf(
            'Verbunden mit „%s“ – %s Meldungen in KoboToolbox.',
            $form['name'] ?? 'unbekannt',
            $form['deployment__submission_count'] ?? '?',
        ));
    }

    private function checkWebhookSecret(): array
    {
        $secret = config('kobo.webhook.secret');

        if (blank($secret)) {
            return $this->result('secret', 'Webhook-Geheimnis', self::FAIL,
                'KOBO_WEBHOOK_SECRET ist nicht gesetzt – der Webhook lehnt jede Anfrage ab.',
                'Ein Geheimnis setzen und denselben Wert als Header im Kobo-REST-Service eintragen.');
        }

        $allowlist = (array) config('kobo.webhook.ip_allowlist', []);

        foreach ($allowlist as $entry) {
            if (! filter_var($entry, FILTER_VALIDATE_IP) && ! Str::contains($entry, '/')) {
                return $this->result('secret', 'Webhook-Geheimnis', self::FAIL,
                    "KOBO_WEBHOOK_IPS enthält „{$entry}“ – das ist keine IP-Adresse. Dadurch wird jede Anfrage abgewiesen.",
                    'KOBO_WEBHOOK_IPS leeren; authentifiziert wird über das Geheimnis.');
            }
        }

        return $this->result('secret', 'Webhook-Geheimnis', self::OK,
            'Gesetzt; Header „'.config('kobo.webhook.header').'“.'
            .($allowlist === [] ? ' Keine IP-Beschränkung.' : ' IP-Freigabe: '.implode(', ', $allowlist)));
    }

    /** The URL KoboToolbox would have to reach. */
    public function webhookUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/webhooks/kobo';
    }

    private function checkEndpointReachable(): array
    {
        $url = $this->webhookUrl();
        $host = parse_url($url, PHP_URL_HOST) ?: '';

        $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || Str::endsWith($host, '.test')
            || Str::endsWith($host, '.local')
            || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && ! filter_var(
                $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE
            );

        if ($isLocal) {
            return $this->result('endpoint', 'Erreichbarkeit', self::FAIL,
                "APP_URL zeigt auf „{$host}“ – diese Adresse ist aus dem Internet nicht erreichbar, "
                .'KoboToolbox kann den Webhook also gar nicht aufrufen.',
                'Für Tests einen Tunnel nutzen (z. B. ngrok) oder die Anwendung öffentlich bereitstellen. '
                .'Bis dahin die Daten über „Aus KoboToolbox synchronisieren“ holen.');
        }

        return $this->result('endpoint', 'Erreichbarkeit', self::OK,
            "Öffentliche Adresse: {$url}");
    }

    private function checkRestServices(): array
    {
        if (! $this->client->isConfigured()) {
            return $this->result('hooks', 'REST Service in Kobo', self::FAIL, 'Nicht geprüft – Zugangsdaten fehlen.');
        }

        try {
            $hooks = $this->client->restServices();
        } catch (Throwable $e) {
            return $this->result('hooks', 'REST Service in Kobo', self::WARN,
                'Konnte nicht abgefragt werden: '.$e->getMessage());
        }

        if ($hooks === []) {
            return $this->result('hooks', 'REST Service in Kobo', self::FAIL,
                'In KoboToolbox ist kein REST Service eingerichtet – es wird nichts gesendet.',
                'Im Kobo-Asset unter Settings → REST Services einen Dienst auf '
                .$this->webhookUrl().' anlegen.');
        }

        $ours = array_filter($hooks, fn (array $h) => Str::contains((string) ($h['endpoint'] ?? ''), '/api/webhooks/kobo'));

        if ($ours === []) {
            $list = implode(', ', array_map(fn (array $h) => (string) ($h['endpoint'] ?? '?'), $hooks));

            return $this->result('hooks', 'REST Service in Kobo', self::FAIL,
                'Es gibt '.count($hooks).' REST Service(s), aber keinen, der auf diese Anwendung zeigt. '
                .'Eingetragen ist: '.$list,
                'Einen Dienst auf '.$this->webhookUrl().' anlegen, mit Header „'
                .config('kobo.webhook.header').'“.');
        }

        $hook = reset($ours);
        $failed = (int) ($hook['failed_count'] ?? 0);

        return $this->result('hooks', 'REST Service in Kobo',
            $failed > 0 ? self::WARN : self::OK,
            sprintf('„%s“ → %s · aktiv: %s · erfolgreich: %d · fehlgeschlagen: %d',
                $hook['name'] ?? '?', $hook['endpoint'] ?? '?',
                ($hook['active'] ?? false) ? 'ja' : 'nein',
                (int) ($hook['success_count'] ?? 0), $failed),
            $failed > 0 ? 'Fehlgeschlagene Zustellungen in KoboToolbox unter „REST Services“ ansehen.' : null);
    }

    /**
     * Photos, geocoding and emails all run through the queue. Without a worker
     * they simply pile up, which is invisible unless someone looks.
     */
    private function checkQueue(): array
    {
        if (config('queue.default') === 'sync') {
            return $this->result('queue', 'Warteschlange', self::OK,
                'Läuft synchron – alle Aufgaben werden sofort ausgeführt.');
        }

        try {
            $pending = DB::table('jobs')->count();
            $oldest = DB::table('jobs')->min('available_at');
            $failed = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            return $this->result('queue', 'Warteschlange', self::WARN, 'Status nicht ermittelbar.');
        }

        if ($pending === 0) {
            return $this->result('queue', 'Warteschlange', $failed > 0 ? self::WARN : self::OK,
                $failed > 0
                    ? "Keine offenen Aufgaben, aber {$failed} fehlgeschlagen."
                    : 'Keine offenen Aufgaben.');
        }

        $waitingMinutes = $oldest ? (int) round((time() - (int) $oldest) / 60) : 0;

        // Anything older than a couple of minutes means nobody is processing.
        if ($waitingMinutes >= 3) {
            return $this->result('queue', 'Warteschlange', self::FAIL,
                "{$pending} Aufgaben warten seit {$waitingMinutes} Minuten – es läuft offenbar kein Worker. "
                .'Fotos, Ortsermittlung und E-Mails bleiben dadurch liegen.',
                'php artisan queue:work starten. Fotos lassen sich alternativ über '
                .'„Fehlende Fotos laden“ sofort holen.');
        }

        return $this->result('queue', 'Warteschlange', self::OK,
            "{$pending} Aufgaben in Bearbeitung.");
    }

    /** Photos live in KoboToolbox until they are downloaded with the token. */
    private function checkAttachments(): array
    {
        $expected = KoboSubmission::all()
            ->sum(fn (KoboSubmission $s) => count($s->attachmentDescriptors()));

        $stored = Attachment::where('source', Attachment::SOURCE_KOBO)->count();

        if ($expected === 0) {
            return $this->result('attachments', 'Fotos', self::OK, 'Keine Fotos in den Rohdaten.');
        }

        if ($stored >= $expected) {
            return $this->result('attachments', 'Fotos', self::OK,
                "Alle {$expected} Fotos aus KoboToolbox sind übertragen.");
        }

        return $this->result('attachments', 'Fotos', self::WARN,
            ($expected - $stored)." von {$expected} Fotos sind noch nicht übertragen.",
            'Über „Fehlende Fotos laden“ sofort nachholen, oder die Warteschlange laufen lassen.');
    }

    private function checkDelivery(): array
    {
        $viaWebhook = KoboSubmission::count();
        $latest = KoboSubmission::latest('received_at')->value('received_at');

        if ($viaWebhook === 0) {
            return $this->result('delivery', 'Eingegangene Rohdaten', self::WARN,
                'Es wurde noch keine Meldung empfangen.');
        }

        return $this->result('delivery', 'Eingegangene Rohdaten', self::OK,
            $viaWebhook.' Rohdatensätze gespeichert, zuletzt am '
            .($latest?->format('d.m.Y H:i') ?? 'unbekannt').'.');
    }
}
