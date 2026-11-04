<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('legal_group', 48)->default('GENERAL');
            $table->jsonb('activity_tags')->nullable();
            $table->uuid('current_version_id')->nullable();
            $table->timestamps();
        });

        Schema::create('legal_rule_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rule_id')->constrained('legal_rules')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->jsonb('condition_json');
            $table->jsonb('result_json');
            $table->string('severity', 16)->default('MEDIUM');
            $table->string('status', 16)->default('DRAFT')->index();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignUuid('authored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->unique(['rule_id', 'version']);
            $table->index(['status', 'effective_from', 'effective_to']);
        });

        Schema::create('rule_provisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rule_version_id')->constrained('legal_rule_versions')->cascadeOnDelete();
            $table->foreignUuid('provision_id')->constrained('legal_provisions')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['rule_version_id', 'provision_id']);
        });

        Schema::create('rule_test_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rule_version_id')->constrained('legal_rule_versions')->cascadeOnDelete();
            $table->string('name');
            $table->jsonb('facts');
            $table->string('expected_applicability', 32);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_result', 16)->nullable();
            $table->timestamps();
        });

        Schema::create('rule_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rule_version_id')->constrained('legal_rule_versions')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 16);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('activity_tag_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tag_code', 32);
            $table->jsonb('condition_json');
            $table->text('reason_template');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_tag_rules');
        Schema::dropIfExists('rule_approvals');
        Schema::dropIfExists('rule_test_cases');
        Schema::dropIfExists('rule_provisions');
        Schema::dropIfExists('legal_rule_versions');
        Schema::dropIfExists('legal_rules');
    }
};
