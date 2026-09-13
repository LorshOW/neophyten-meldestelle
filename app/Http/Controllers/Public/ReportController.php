<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Serves the public reporting app (the former static index.html).
 *
 * The app itself is unchanged: it collects a point or polygon on a Leaflet map
 * and hands the user off to the KoboToolbox Enketo form with prefilled
 * parameters. Kobo remains the data-entry system; this application receives the
 * finished submission through the webhook in Api\KoboWebhookController.
 */
class ReportController extends Controller
{
    public function __invoke(): View
    {
        return view('public.report', [
            'config' => [
                'koboFormUrl' => config('kobo.form_url'),
                'mapCenter' => config('meldestelle.map.center'),
                'mapZoom' => config('meldestelle.map.zoom'),
            ],
        ]);
    }
}
