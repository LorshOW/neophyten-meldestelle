<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Public\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Öffentliche Meldeseite
|--------------------------------------------------------------------------
|
| The public PWA. Everything else in this application lives behind the
| Filament panels at /admin and /aussendienst.
|
*/

Route::get('/', ReportController::class)->name('public.report');

/*
|--------------------------------------------------------------------------
| Anhänge
|--------------------------------------------------------------------------
|
| Attachments live on a private disk. They are reachable only through a signed,
| expiring URL and only for users whose policy allows viewing the owning record.
|
*/

Route::get('/anhaenge/{attachment}', [AttachmentController::class, 'show'])
    ->middleware(['auth', 'signed'])
    ->name('attachments.show');
