<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First persistence layer: the untouched KoboToolbox payload.
 *
 * Nothing is interpreted here. The webhook stores the request verbatim and
 * returns 200 immediately; ProcessKoboSubmission turns it into a Vorgang
 * afterwards. Keeping the raw payload means a mapping bug can always be
 * repaired by reprocessing instead of asking reporters to submit again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kobo_submissions', function (Blueprint $table): void {
            $table->id();

            $table->string('asset_uid')->nullable()->index();

            // Kobo's own submission id (_id) and uuids. The (asset_uid, kobo_id)
            // pair is the idempotency key - Kobo retries failed webhook calls.
            $table->unsignedBigInteger('kobo_id')->nullable();
            $table->string('kobo_uuid')->nullable()->index();
            $table->string('instance_id')->nullable();

            $table->json('payload');
            $table->json('headers')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();

            $table->string('processing_status', 32)->default('pending');
            $table->text('processing_error')->nullable();
            $table->unsignedInteger('processing_attempts')->default(0);

            // FK added after the vorgaenge table exists (circular reference).
            $table->unsignedBigInteger('vorgang_id')->nullable()->index();

            $table->timestamps();

            $table->unique(['asset_uid', 'kobo_id']);
            $table->index(['processing_status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kobo_submissions');
    }
};
