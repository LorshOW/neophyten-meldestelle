{{--
    Renders the stored geometry on a Leaflet map.

    The Alpine component and Leaflet itself are registered on the panel layout
    (filament.hooks.vorgang-map-scripts). They must not be defined here:
    Livewire does not run scripts it injects into modals or form updates.
--}}
@php
    $record = $getRecord();
    $geometry = $record?->geojson;
    $lat = $record?->latitude;
    $lon = $record?->longitude;
    $hasLocation = $geometry !== null || ($lat !== null && $lon !== null);
@endphp

@if (! $hasLocation)
    <div class="fi-section rounded-xl bg-gray-50 p-6 text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400">
        Für diesen Vorgang liegt keine Position vor.
    </div>
@else
    <div
        wire:ignore
        x-data="vorgangMap(@js($geometry), @js($lat), @js($lon))"
        class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
    >
        <div x-ref="map" style="height: 420px; width: 100%; background: #e8ece8;"></div>
    </div>
@endif
