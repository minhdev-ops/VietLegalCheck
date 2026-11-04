<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->date('as_of_date');
            $table->unsignedInteger('question_set_version');
            $table->jsonb('rule_set_snapshot');
            $table->string('status', 16)->default('QUEUED');
            $table->string('overall_status', 24)->nullable();
            $table->text('error')->nullable();
            $table->uuid('job_id')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'created_at']);
        });

        Schema::create('assessment_findings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('rule_version_id')->constrained('legal_rule_versions');
            $table->string('rule_code', 64);
            $table->string('title');
            $table->string('legal_group', 48)->default('GENERAL');
            $table->string('applicability', 24);
            $table->string('fulfillment', 24)->default('NOT_STARTED');
            $table->string('severity', 16)->default('MEDIUM');
            $table->jsonb('trace_json')->nullable();
            $table->text('explanation')->nullable();
            $table->text('recommendation')->nullable();
            $table->jsonb('obligation_json')->nullable();
            $table->jsonb('legal_basis')->nullable();
            $table->jsonb('required_info')->nullable();
            $table->timestamps();
            $table->index(['assessment_id', 'applicability']);
        });

        Schema::create('evidence_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('finding_id')->nullable()->constrained('assessment_findings')->nullOnDelete();
            $table->uuid('scan_id')->nullable();
            $table->uuid('file_id')->nullable();
            $table->string('type', 24);
            $table->string('source_url')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_items');
        Schema::dropIfExists('assessment_findings');
        Schema::dropIfExists('assessments');
    }
};
