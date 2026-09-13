<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos and files belonging to a Vorgang or an Inspection.
 *
 * Files live on a private disk and are streamed through a signed route - they
 * may show private property and are personal data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();

            $table->morphs('attachable');

            $table->string('disk', 64);
            $table->string('path', 1024);
            $table->string('original_filename')->nullable();
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size')->nullable();

            // kobo | aussendienst | admin
            $table->string('source', 32)->default('kobo');

            // Kobo's attachment id + URL, so a failed download can be retried
            // and a duplicate download can be detected.
            $table->unsignedBigInteger('kobo_attachment_id')->nullable();
            $table->string('kobo_download_url', 1024)->nullable();

            $table->string('checksum', 64)->nullable();
            $table->foreignId('uploaded_by_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('taken_at')->nullable();

            $table->timestamps();

            $table->unique('kobo_attachment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
