{{--
    Uses inline styles rather than Tailwind utility classes: Filament ships a
    precompiled stylesheet, and utilities that appear only in a custom view are
    not part of it unless a custom theme is built.
--}}
@php
    $tone = fn (string $status) => match ($status) {
        'ok' => ['#16a34a', 'rgba(22,163,74,.12)', '✓'],
        'warnung' => ['#d97706', 'rgba(217,119,6,.12)', '!'],
        default => ['#dc2626', 'rgba(220,38,38,.12)', '✕'],
    };
@endphp

<x-filament-panels::page>

    <x-filament::section>
        <x-slot name="heading">Datenstand</x-slot>
        <x-slot name="description">Vergleich zwischen KoboToolbox und dieser Anwendung.</x-slot>

        @if ($status['error'])
            <p style="color:#dc2626;font-size:.875rem;">{{ $status['error'] }}</p>
        @else
            @php $missing = $status['missing'] ?? 0; @endphp

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
                <div style="border-radius:.75rem;padding:1rem;background:rgba(128,128,128,.08);">
                    <div style="font-size:1.75rem;font-weight:700;line-height:1.1;">{{ $status['remote'] ?? '–' }}</div>
                    <div style="font-size:.8125rem;opacity:.7;margin-top:.25rem;">Meldungen in KoboToolbox</div>
                </div>

                <div style="border-radius:.75rem;padding:1rem;background:rgba(128,128,128,.08);">
                    <div style="font-size:1.75rem;font-weight:700;line-height:1.1;">{{ $status['local'] }}</div>
                    <div style="font-size:.8125rem;opacity:.7;margin-top:.25rem;">Rohdatensätze hier</div>
                </div>

                <div style="border-radius:.75rem;padding:1rem;background:{{ $missing > 0 ? 'rgba(217,119,6,.12)' : 'rgba(22,163,74,.12)' }};">
                    <div style="font-size:1.75rem;font-weight:700;line-height:1.1;color:{{ $missing > 0 ? '#d97706' : '#16a34a' }};">{{ $missing }}</div>
                    <div style="font-size:.8125rem;opacity:.75;margin-top:.25rem;">noch nicht übernommen</div>
                </div>
            </div>

            @if ($missing > 0)
                <p style="margin-top:1rem;font-size:.875rem;opacity:.75;">
                    Es fehlen Meldungen. Über <strong>„Aus KoboToolbox synchronisieren“</strong>
                    oben lassen sie sich jederzeit nachholen.
                </p>
            @endif

            @php $fotosFehlen = $attachments['missing'] ?? 0; @endphp

            <div style="margin-top:1rem;border-radius:.75rem;padding:.875rem 1rem;
                        background:{{ $fotosFehlen > 0 ? 'rgba(217,119,6,.12)' : 'rgba(128,128,128,.08)' }};">
                <strong>Fotos:</strong>
                {{ $attachments['stored'] ?? 0 }} von {{ $attachments['expected'] ?? 0 }} übertragen.
                @if ($fotosFehlen > 0)
                    <span style="opacity:.8;">
                        Fotos liegen in KoboToolbox und werden erst mit dem API-Token heruntergeladen –
                        über <strong>„Fehlende Fotos laden“</strong> oder eine laufende Warteschlange.
                    </span>
                @endif
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Webhook-Prüfung</x-slot>
        <x-slot name="description">
            KoboToolbox müsste Meldungen an <code>{{ $this->webhookUrl() }}</code> senden.
        </x-slot>

        <div style="display:flex;flex-direction:column;gap:.25rem;">
            @foreach ($checks as $check)
                @php [$colour, $background, $icon] = $tone($check['status']); @endphp

                <div style="display:flex;gap:.75rem;padding:.875rem 0;border-top:{{ $loop->first ? 'none' : '1px solid rgba(128,128,128,.2)' }};">
                    <span style="flex:0 0 1.5rem;height:1.5rem;border-radius:9999px;display:flex;align-items:center;
                                 justify-content:center;font-size:.8125rem;font-weight:700;
                                 color:{{ $colour }};background:{{ $background }};">{{ $icon }}</span>

                    <div style="min-width:0;">
                        <div style="font-weight:600;">{{ $check['label'] }}</div>
                        <div style="font-size:.875rem;opacity:.8;margin-top:.125rem;">{{ $check['detail'] }}</div>

                        @if ($check['hint'])
                            <div style="font-size:.875rem;margin-top:.375rem;color:{{ $colour }};">
                                → {{ $check['hint'] }}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Webhook in KoboToolbox einrichten</x-slot>

        <ol style="padding-left:1.25rem;font-size:.875rem;opacity:.85;line-height:1.7;">
            <li>Die Anwendung muss unter einer öffentlich erreichbaren Adresse laufen,
                und <code>APP_URL</code> muss auf genau diese Adresse zeigen.</li>
            <li>In KoboToolbox das Formular öffnen → <strong>Settings</strong> →
                <strong>REST Services</strong> → <strong>Register a New Service</strong>.</li>
            <li>Als <em>Endpoint URL</em> eintragen: <code>{{ $this->webhookUrl() }}</code></li>
            <li>Unter <em>Custom HTTP Headers</em> hinzufügen:
                <code>{{ config('kobo.webhook.header') }}</code> mit dem Wert aus
                <code>KOBO_WEBHOOK_SECRET</code>.</li>
            <li>Speichern. Ab der nächsten Meldung wird automatisch zugestellt;
                ältere Meldungen holt der Synchronisieren-Knopf.</li>
        </ol>
    </x-filament::section>

</x-filament-panels::page>
