<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The KoboToolbox submission id a case originates from.
 *
 * Set even when the raw payload is not held locally - cases imported from the
 * old monitor application carry it, so a later `kobo:sync` recognises them
 * instead of creating a second case for the same report. Offline submissions
 * have no melde_id, which makes this the only reliable link for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vorgaenge', function (Blueprint $table): void {
            $table->unsignedBigInteger('kobo_id')->nullable()->after('melde_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('vorgaenge', function (Blueprint $table): void {
            $table->dropColumn('kobo_id');
        });
    }
};
