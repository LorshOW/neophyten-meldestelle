<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery log. Every automated mail is recorded here whether it succeeded or
 * not, so the office can prove what a reporter was told and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outgoing_emails', function (Blueprint $table): void {
            $table->id();
            $table->string('recipient');
            $table->string('subject');
            $table->longText('body_html')->nullable();

            $table->foreignId('email_template_id')->nullable()
                ->constrained('email_templates')->nullOnDelete();
            $table->foreignId('notification_rule_id')->nullable()
                ->constrained('notification_rules')->nullOnDelete();
            $table->foreignId('vorgang_id')->nullable()
                ->constrained('vorgaenge')->cascadeOnDelete();

            // queued | sent | failed
            $table->string('status', 32)->default('queued');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['vorgang_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_emails');
    }
};
