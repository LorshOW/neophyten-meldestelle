<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vorgang_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vorgang_id')->constrained('vorgaenge')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');

            // internal = office only, aussendienst = also shown in the field panel
            $table->string('visibility', 32)->default('internal');
            $table->timestamps();

            $table->index(['vorgang_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vorgang_notes');
    }
};
