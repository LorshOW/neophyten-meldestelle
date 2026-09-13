<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow stages are data, not an enum, so administrators can rename, reorder
 * and extend them without a deployment. The static reference application had no
 * workflow at all - the seeded defaults are the designed starting point.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('color', 32)->default('gray');
            $table->unsignedInteger('sort_order')->default(0);

            // Exactly one status should carry is_initial; new Vorgänge start there.
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_terminal')->default(false);

            // Guards enforced by WorkflowService before entering this status.
            $table->boolean('requires_assignee')->default(false);
            $table->boolean('requires_inspection')->default(false);

            // Whether an Außendienst user may see a Vorgang sitting in this status.
            $table->boolean('visible_to_aussendienst')->default(false);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_statuses');
    }
};
