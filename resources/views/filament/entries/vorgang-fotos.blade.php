{{--
    Photo gallery for a Vorgang.

    Files live on a private disk, so every image is served through a signed,
    expiring route rather than a public URL.
--}}
@php
    $record = $getRecord();
    $attachments = $record->attachments()->orderBy('id')->get();
@endphp

@if ($attachments->isEmpty())
    <div class="fi-section rounded-xl bg-gray-50 p-6 text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400">
        Für diesen Vorgang liegen keine Fotos vor.
        @if ($record->koboSubmission && $record->koboSubmission->attachmentDescriptors())
            Die Meldung enthält Anhänge in KoboToolbox, die noch nicht geladen wurden
            (benötigt <code>KOBO_API_TOKEN</code>).
        @endif
    </div>
@else
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($attachments as $attachment)
            <figure class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                @if ($attachment->isDisplayable())
                    <a href="{{ $attachment->temporaryUrl() }}" target="_blank" rel="noopener">
                        <img
                            src="{{ $attachment->temporaryUrl() }}"
                            alt="{{ $attachment->original_filename }}"
                            loading="lazy"
                            class="block h-36 w-full object-cover"
                        >
                    </a>
                @else
                    {{-- z. B. TIFF: kein Browser zeigt das an --}}
                    <a href="{{ $attachment->temporaryUrl() }}?download=1"
                       style="display:flex;height:9rem;width:100%;align-items:center;justify-content:center;
                              flex-direction:column;gap:.25rem;background:rgba(128,128,128,.1);text-decoration:none;">
                        <span style="font-size:1.75rem;">📄</span>
                        <span style="font-size:.75rem;opacity:.7;">
                            {{ strtoupper(str_replace('image/', '', (string) $attachment->mime)) }} – herunterladen
                        </span>
                    </a>
                @endif

                <figcaption class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                    <span class="block truncate" title="{{ $attachment->original_filename }}">
                        {{ $attachment->original_filename }}
                    </span>
                    <span class="text-gray-400">
                        {{ $attachment->humanSize() }} ·
                        {{ $attachment->source === 'kobo' ? 'Meldung' : 'Außendienst' }}
                    </span>
                </figcaption>
            </figure>
        @endforeach
    </div>
@endif
