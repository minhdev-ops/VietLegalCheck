<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrative_procedures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('subject')->nullable();
            $table->string('authority');
            $table->text('conditions')->nullable();
            $table->jsonb('steps')->nullable();
            $table->jsonb('submission_methods')->nullable();
            $table->string('processing_time')->nullable();
            $table->string('fee')->nullable();
            $table->text('result')->nullable();
            $table->string('portal_url')->nullable();
            $table->jsonb('legal_basis')->nullable();
            $table->jsonb('activity_tags')->nullable();
            $table->jsonb('obligation_codes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('last_verified_at')->nullable();
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('procedure_requirements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('procedure_id')->constrained('administrative_procedures')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('required')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['procedure_id', 'code']);
        });

        Schema::create('project_procedures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('procedure_id')->constrained('administrative_procedures');
            $table->string('status', 32)->default('NOT_STARTED');
            $table->text('notes')->nullable();
            $table->jsonb('snapshot')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'procedure_id']);
        });

        Schema::create('procedure_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_procedure_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('procedure_requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('file_id')->nullable();
            $table->string('status', 24)->default('MISSING');
            $table->text('note')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_documents');
        Schema::dropIfExists('project_procedures');
        Schema::dropIfExists('procedure_requirements');
        Schema::dropIfExists('administrative_procedures');
    }
};
