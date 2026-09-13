<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_transitions', function (Blueprint $table): void {
            $table->id();

            // Null means "from any status" - used for cancel/reject style moves.
            $table->foreignId('from_status_id')->nullable()
                ->constrained('workflow_statuses')->cascadeOnDelete();
            $table->foreignId('to_status_id')
                ->constrained('workflow_statuses')->cascadeOnDelete();

            $table->string('label');
            $table->string('required_permission')->nullable();
            $table->boolean('requires_note')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['from_status_id', 'to_status_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
    }
};
