<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Geplante Aufgaben
|--------------------------------------------------------------------------
*/

// Only does anything once an administrator sets a retention period.
Schedule::command('vorgaenge:purge')->dailyAt('03:30');

// Catches anything a failed webhook call missed.
Schedule::command('kobo:sync --process')->hourly();

// Fills in place names for cases whose geocoding has not run yet.
Schedule::command('geocode:vorgaenge')->hourlyAt(15);
