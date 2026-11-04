<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('doc_number')->unique();
            $table->string('title');
            $table->string('doc_type', 32);
            $table->string('issuer')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('effective_from')->nullable()->index();
            $table->date('effective_to')->nullable();
            $table->string('status', 32)->default('DRAFT')->index();
            $table->string('official_url')->nullable();
            $table->string('source_type', 16)->default('PRIMARY');
            $table->text('source_note')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('legal_provisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('legal_document_id')->constrained()->cascadeOnDelete();
            $table->string('chapter', 32)->nullable();
            $table->string('article', 32)->nullable();
            $table->string('clause', 32)->nullable();
            $table->string('point', 16)->nullable();
            $table->string('heading')->nullable();
            $table->text('content');
            $table->date('effective_from')->nullable()->index();
            $table->date('effective_to')->nullable();
            $table->string('status', 32)->default('EFFECTIVE');
            $table->uuid('supersedes_id')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['legal_document_id', 'article']);
        });

        Schema::table('legal_provisions', function (Blueprint $table) {
            $table->foreign('supersedes_id')->references('id')->on('legal_provisions')->nullOnDelete();
        });

        Schema::create('legal_relationships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('from_document_id')->constrained('legal_documents')->cascadeOnDelete();
            $table->foreignUuid('to_document_id')->constrained('legal_documents')->cascadeOnDelete();
            $table->string('relation', 24);
            $table->text('scope')->nullable();
            $table->timestamps();
            $table->unique(['from_document_id', 'to_document_id', 'relation']);
        });

        Schema::create('legal_update_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('legal_document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->jsonb('changes')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        if (DB::getDriverName() === 'pgsql') {
            // Tìm kiếm tiếng Việt có dấu/không dấu (SRS 6.1) mà không cần cột sinh sẵn.
            DB::statement('CREATE INDEX legal_provisions_content_trgm ON legal_provisions USING gin (unaccent(lower(content)) gin_trgm_ops)');
            DB::statement('CREATE INDEX legal_documents_title_trgm ON legal_documents USING gin (unaccent(lower(title)) gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_update_logs');
        Schema::dropIfExists('legal_relationships');
        Schema::dropIfExists('legal_provisions');
        Schema::dropIfExists('legal_documents');
    }
};
