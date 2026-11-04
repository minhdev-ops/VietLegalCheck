<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime', 128);
            $table->unsignedBigInteger('size');
            $table->string('checksum_sha256', 64);
            $table->string('scan_status', 16)->default('CLEAN');
            $table->string('category', 32)->default('OTHER');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'project_id']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('QUEUED');
            $table->date('as_of_date');
            $table->jsonb('rule_set_snapshot');
            $table->string('pdf_path')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->text('error')->nullable();
            $table->foreignUuid('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('files');
    }
};
