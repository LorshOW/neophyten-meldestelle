<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail. Every status change goes through WorkflowService and
 * lands here, including who did it and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vorgang_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vorgang_id')->constrained('vorgaenge')->cascadeOnDelete();
            $table->foreignId('from_status_id')->nullable()
                ->constrained('workflow_statuses')->nullOnDelete();
            $table->foreignId('to_status_id')
                ->constrained('workflow_statuses')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['vorgang_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vorgang_status_histories');
    }
};
