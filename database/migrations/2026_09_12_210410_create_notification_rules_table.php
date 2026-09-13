<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connects a workflow event to an email template and a recipient. This is what
 * makes "automated emails triggered by workflow events" configurable rather
 * than hardcoded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');

            // vorgang_created | status_changed | vorgang_assigned | inspection_completed
            $table->string('trigger_event', 64)->index();

            // Only for status_changed: restrict the rule to one target status.
            $table->foreignId('status_id')->nullable()
                ->constrained('workflow_statuses')->cascadeOnDelete();

            $table->foreignId('email_template_id')
                ->constrained('email_templates')->cascadeOnDelete();

            // reporter | assignee | role | fixed
            $table->string('recipient_type', 32);
            $table->string('recipient_value')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_rules');
    }
};
