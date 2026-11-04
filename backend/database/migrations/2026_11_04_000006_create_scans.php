<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->string('target_url');
            $table->string('resolved_ip', 45)->nullable();
            $table->string('mode', 8)->default('LIGHT');
            $table->string('status', 16)->default('QUEUED')->index();
            $table->unsignedInteger('criteria_version')->default(1);
            $table->uuid('job_id')->nullable();
            $table->text('error')->nullable();
            $table->string('blocked_reason', 32)->nullable();
            $table->jsonb('site')->nullable();
            $table->jsonb('findings')->nullable();
            $table->unsignedInteger('page_count')->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'created_at']);
        });

        Schema::create('scan_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('scan_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('title')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->string('snapshot_path')->nullable();
            $table->jsonb('signals')->nullable();
            $table->jsonb('evidence')->nullable();
            $table->string('fetch_error')->nullable();
            $table->timestamps();
            $table->index(['scan_id']);
        });

        Schema::create('domain_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('method', 16)->default('META_TAG');
            $table->string('token', 64);
            $table->string('status', 16)->default('PENDING');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_verifications');
        Schema::dropIfExists('scan_pages');
        Schema::dropIfExists('scans');
    }
};
