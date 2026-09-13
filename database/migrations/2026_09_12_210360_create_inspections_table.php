<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The result of one Vor-Ort-Kontrolle carried out by an Außendienst user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('vorgang_id')
                ->constrained('vorgaenge')->cascadeOnDelete();
            $table->foreignId('inspector_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamp('inspected_at')->nullable();

            // bestaetigt | nicht_bestaetigt | andere_art
            $table->string('confirmation', 32)->nullable();
            $table->foreignId('species_confirmed_id')->nullable()
                ->constrained('species')->nullOnDelete();

            $table->unsignedBigInteger('area_estimate_sqm')->nullable();
            $table->string('growth_stage', 64)->nullable();
            $table->string('accessibility', 64)->nullable();
            $table->string('land_owner_type', 64)->nullable();
            $table->text('recommended_measure')->nullable();
            $table->string('measure_urgency', 32)->nullable();
            $table->text('notes')->nullable();

            // Where the inspector actually stood, which may differ from the
            // reported location.
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lon', 10, 7)->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['vorgang_id', 'inspected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
