<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Aufbewahrungsfrist
    |--------------------------------------------------------------------------
    |
    | Number of days an abgeschlossen/abgelehnt Vorgang is kept before
    | `vorgaenge:purge` removes it together with its attachments.
    | Null keeps everything indefinitely (the current Kobo behaviour).
    |
    */

    'retention_days' => env('MELDESTELLE_RETENTION_DAYS') !== null
        && env('MELDESTELLE_RETENTION_DAYS') !== ''
            ? (int) env('MELDESTELLE_RETENTION_DAYS')
            : null,

    /*
    |--------------------------------------------------------------------------
    | Ablage für Meldungsanhänge
    |--------------------------------------------------------------------------
    |
    | Photos from Kobo and from the Außendienst contain personal data and must
    | never be written to the publicly readable disk. They are streamed through
    | a signed route instead.
    |
    */

    'attachment_disk' => env('MELDESTELLE_ATTACHMENT_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Kartenausschnitt der öffentlichen Meldeseite
    |--------------------------------------------------------------------------
    |
    | Taken from the original static app (Erzgebirge).
    |
    */

    'map' => [
        'center' => [50.7, 12.95],
        'zoom' => 12,
    ],

];
