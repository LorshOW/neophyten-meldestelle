<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Neophyten master data. Seeded from the hardcoded NEOPHYTEN array of the
 * static application so the public plant overview and the case system share
 * one source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('species', function (Blueprint $table): void {
            $table->id();
            $table->string('name_de');
            $table->string('name_latin')->nullable();
            $table->string('wiki_title')->nullable();
            $table->string('habitat')->nullable();
            $table->text('warning_text')->nullable();
            $table->string('image_url', 1024)->nullable();

            // The choice value this species has in the Kobo form, used to map
            // an incoming submission onto a species record.
            $table->string('kobo_value')->nullable()->index();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('species');
    }
};
