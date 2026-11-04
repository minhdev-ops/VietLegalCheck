<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_tags', function (Blueprint $table) {
            $table->string('code', 32)->primary();
            $table->string('name_vi');
            $table->string('group', 32)->default('GENERAL');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->string('code', 16)->primary();
            $table->string('name_vi');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('question_sets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('version')->unique();
            $table->string('status', 16)->default('DRAFT');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('question_set_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('section', 32);
            $table->text('prompt');
            $table->text('help')->nullable();
            $table->string('type', 16);
            $table->jsonb('options')->nullable();
            $table->jsonb('show_if')->nullable();
            $table->jsonb('activity_tags')->nullable();
            $table->string('importance', 16)->default('REQUIRED');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['question_set_id', 'code']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('product_type', 16)->nullable();
            $table->string('url')->nullable();
            $table->jsonb('app_links')->nullable();
            $table->string('platform', 16)->nullable();
            $table->jsonb('markets')->nullable();
            $table->string('status', 16)->default('DRAFT');
            $table->timestamp('archived_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('project_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->string('category_code', 16);
            $table->timestamps();
            $table->unique(['project_id', 'category_code']);
        });

        Schema::create('project_activity_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->string('tag_code', 32);
            $table->string('assigned_by', 16)->default('RULE');
            $table->text('reason')->nullable();
            $table->decimal('confidence', 4, 2)->nullable();
            $table->jsonb('question_codes')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'tag_code']);
        });

        Schema::create('project_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('question_set_version')->nullable();
            $table->jsonb('answers')->nullable();
            $table->jsonb('derived_tags')->nullable();
            $table->string('status', 16)->default('DRAFT');
            $table->unsignedSmallInteger('completed_step')->default(1);
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_profiles');
        Schema::dropIfExists('project_activity_tags');
        Schema::dropIfExists('project_categories');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('question_sets');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('activity_tags');
    }
};
