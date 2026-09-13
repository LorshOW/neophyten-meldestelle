<?php

use App\Http\Controllers\Api\KoboWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| KoboToolbox-Webhook
|--------------------------------------------------------------------------
|
| Configure this URL as a REST Service on the Kobo asset and add the shared
| secret as a custom header (default: X-Kobo-Secret).
|
| Rate limited because the endpoint is public; Kobo sends one call per
| submission, so the limit is generous but finite.
|
*/

Route::post('/webhooks/kobo', KoboWebhookController::class)
    ->middleware('throttle:kobo-webhook')
    ->name('webhooks.kobo');
