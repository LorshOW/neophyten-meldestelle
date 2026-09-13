<?php

use App\Support\RiskAssessment;

return [

    /*
    |--------------------------------------------------------------------------
    | Öffentliches Meldeformular
    |--------------------------------------------------------------------------
    */

    'form_url' => env('KOBO_FORM_URL', 'https://ee-eu.kobotoolbox.org/x/aeG09o4G'),

    /*
    |--------------------------------------------------------------------------
    | KPI-API
    |--------------------------------------------------------------------------
    |
    | The EU deployment splits the hosts: Enketo serves the public form from
    | ee-eu.kobotoolbox.org while the API lives on eu.kobotoolbox.org.
    |
    */

    'api_base' => rtrim((string) env('KOBO_API_BASE', 'https://eu.kobotoolbox.org'), '/'),
    'api_token' => env('KOBO_API_TOKEN'),
    'asset_uid' => env('KOBO_ASSET_UID', 'aUTdQWQfjDBbnYqBtqWWUP'),

    /*
    |--------------------------------------------------------------------------
    | Webhook-Absicherung
    |--------------------------------------------------------------------------
    */

    'webhook' => [
        'secret' => env('KOBO_WEBHOOK_SECRET'),
        'header' => env('KOBO_WEBHOOK_HEADER', 'X-Kobo-Secret'),
        // Comma separated IP addresses. Leave empty to accept any source; the
        // shared secret is what actually authenticates the call.
        'ip_allowlist' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('KOBO_WEBHOOK_IPS', ''))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feldzuordnung
    |--------------------------------------------------------------------------
    |
    | First candidate = the XLSForm QUESTION NAME, verified against the live
    | asset with `php artisan kobo:inspect-form`. That is what a webhook
    | payload contains.
    |
    | Further candidates are the question LABELS, which is what the CSV export
    | contains, so a CSV-derived import maps just as well.
    |
    */

    'fields' => [
        // Hidden fields prefilled by the public map screen.
        'melde_id' => ['melde_id', 'Karten Melde-ID'],
        'geometry_type' => ['karten_typ', 'Karten Typ'],
        'latitude' => ['karten_lat', 'Karten Breitengrad'],
        'longitude' => ['karten_lon', 'Karten Längengrad'],
        'geojson' => ['karten_geojson', 'Karten Geometrie'],
        'captured_online' => ['karten_online', 'hidden'],

        // Questions answered by the reporting person.
        'gps_point' => ['offline_geopoint', '📍 Fundort per GPS erfassen'],
        'knows_species' => ['Kennst_du_die_Pflanzenart', 'Kennst du die Pflanzenart?'],
        'species' => [
            'Welche_Pflanzenart_hast_du_gef',
            '_Welche_Pflanzenart_vermutest_du',
            'Welche Pflanzenart hast du gefunden bzw. vermutest du?',
            '🌿 Welche Pflanzenart vermutest du?',
        ],
        'location_summary' => [
            'Wo_befindet_sich_der_Pflanzenb',
            'Wo befindet sich der Pflanzenbestand?',
        ],
        'location_other' => ['Wo_befindet_sich_der_Pflanzenbestand'],
        'risks' => [
            'Trifft_etwas_davon_auf_den_Fundort_zu',
            'Trifft etwas davon auf den Fundort zu?',
        ],
        'size_category' => [
            'Wie_gro_ist_der_Pfl_nzenbestand_ungef_hr',
            'Wie groß ist der Pflanzenbestand ungefähr?',
        ],
        'citizen_note' => [
            '_M_chtest_du_noch_e_nzenbestand_erg_nzen',
            '📝 Möchtest du noch etwas zum Fundort oder Pflanzenbestand ergänzen?',
        ],
        'reporter_contact_consent' => [
            'D_rfen_wir_dich_bei_R_ckfragen',
            'Dürfen wir dich bei Rückfragen zu deiner Meldung kontaktieren?',
        ],
        'reporter_name' => [
            'Wie_d_rfen_wir_dich_ansprechen',
            'Wie dürfen wir dich ansprechen?',
        ],
        // One free-text field holding either an email address or a phone number.
        'reporter_contact' => [
            'Wie_d_rfen_wir_dich_besten_kontaktieren',
            'Wie dürfen wir dich am besten kontaktieren?',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Antwortwerte der Auswahlfragen
    |--------------------------------------------------------------------------
    |
    | A webhook delivers the choice NAME ("kanadische_goldrute"), while the CSV
    | export delivers the LABEL ("Kanadische Goldrute"). These maps translate
    | the former into the latter so the office always reads plain German,
    | whichever route the data took.
    |
    */

    'choices' => [
        'knows_species' => [
            'ja__ich_kenne_die_art' => 'Ja, ich kenne die Art',
            'ich_vermute_eine_bestimmte_art' => 'Ich vermute eine bestimmte Art',
            'nein__ich_bin_mir_nicht_sicher' => 'Nein, ich bin mir nicht sicher',
        ],

        'species' => [
            'Japanischer_Staudenknöterich' => 'Japanischer Staudenknöterich',
            'Riesen_Bärenklau' => 'Riesen-Bärenklau',
            'dr_siges_springkraut' => 'Drüsiges Springkraut',
            'kanadische_goldrute' => 'Kanadische Goldrute',
            'vielbl_ttrige_lupine' => 'Vielblättrige Lupine',
            'einj_hriges_berufkraut' => 'Einjähriges Berufkraut',
            'andere___nicht_aufgef_hrte_art' => 'Andere / nicht aufgeführte Art',
        ],

        'location_summary' => [
            'gew_sser___bachlauf___ufer' => 'Gewässer / Bachlauf / Ufer',
            'stra_en__oder_wegrand' => 'Straßen- oder Wegrand',
            'wald___waldrand' => 'Wald / Waldrand',
            'wiese___gr_nland' => 'Wiese / Acker / sonstige Grünfläche',
            'garten___privatgrundst_ck' => 'Garten / Privatgrundstück',
            // The choice name still says "Brache"; the label was changed later.
            'brache___ungenutzte_fl_che' => 'öffentliche Fläche',
            'kann_ich_nicht_einsch_tzen' => 'Kann ich nicht einschätzen',
            'sonstiger_standort' => 'Sonstiger Standort',
        ],

        'size_category' => [
            'einzelne_pflanze' => 'Einzelne Pflanze',
            'kleiner_bestand___bis_ca__10_pflanzen' => 'Sehr klein – bis ca. 1 m² (etwa Haustürgröße)',
            'mittlerer_bestand___ca__10_50_pflanzen' => 'Klein – ca. 1–5 m² (etwa kleines Beet)',
            'gro_er_bestand___mehr_als_50_pflanzen' => 'Mittel – ca. 5–20 m² (etwa PKW-Stellplatz)',
            'sehr_gro____gr_er_als_ein_kleiner_garten' => 'Groß – ca. 20–100 m² (etwa mehrere PKW-Stellplätze)',
            'sehr_gro_____ber_100_m___gr_er_als_etwa_' => 'Sehr groß – über 100 m²',
            'kann_ich_nicht_einsch_tzen' => 'Kann ich nicht einschätzen',
        ],

        'reporter_contact_consent' => [
            'ja__gerne' => 'Ja, gerne',
            'nein' => 'Nein',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gefährdungsangaben
    |--------------------------------------------------------------------------
    |
    | Maps the choices of "Trifft etwas davon auf den Fundort zu?" onto the six
    | risk options in App\Support\RiskAssessment, which drive the priority.
    |
    | A webhook sends a select_multiple as ONE space separated string; the CSV
    | export writes one "<question>/<choice label>" column per choice. Both
    | spellings are listed so either source maps correctly.
    |
    */

    'risk_choices' => [
        'gef_hrdung_von_menschen_m_glich__z__b__h' => RiskAssessment::HUMAN,
        'gef_hrdung_von_tieren_m_glich' => RiskAssessment::ANIMAL,
        'fundort_liegt_in_einem_naturschutzgebiet' => RiskAssessment::PROTECTED_AREA,
        'gesch_tzte_oder_besonders_sensible_natur' => RiskAssessment::SENSITIVE_NATURE,
        'keine_dieser_angaben_trifft_zu' => RiskAssessment::NONE,
        'kann_ich_nicht_einsch_tzen' => RiskAssessment::UNKNOWN,
    ],

    /** CSV export columns, one per choice. */
    'risk_columns' => [
        RiskAssessment::HUMAN => 'Trifft etwas davon auf den Fundort zu?/Gefährdung von Menschen möglich -z. B. Hautkontakt, Verbrennungs-/Vergiftungsgefahr-',
        RiskAssessment::ANIMAL => 'Trifft etwas davon auf den Fundort zu?/Gefährdung von Tieren möglich',
        RiskAssessment::PROTECTED_AREA => 'Trifft etwas davon auf den Fundort zu?/Fundort liegt in einem Naturschutzgebiet oder anderen Schutzgebiet',
        RiskAssessment::SENSITIVE_NATURE => 'Trifft etwas davon auf den Fundort zu?/Geschützte oder besonders sensible Natur befindet sich unmittelbar im Umfeld',
        RiskAssessment::NONE => 'Trifft etwas davon auf den Fundort zu?/Keine dieser Angaben trifft zu',
        RiskAssessment::UNKNOWN => 'Trifft etwas davon auf den Fundort zu?/Kann ich nicht einschätzen',
    ],

    /*
    |--------------------------------------------------------------------------
    | Wertzuordnung
    |--------------------------------------------------------------------------
    */

    'values' => [
        // karten_typ as written by the public map screen.
        'geometry_type' => [
            'Punkt' => 'point',
            'Flaeche' => 'polygon',
        ],

        // Consent is "Ja, gerne" / "ja__gerne" in the live form.
        'truthy' => ['ja', 'ja, gerne', 'ja__gerne', 'yes', '1', 'true'],
    ],

];
