<?php

namespace App\Services;

use App\Models\DocumentFile;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Tài liệu (SRS 4.10): lưu trên S3 (server-side encryption), kiểm tra loại tệp thật
 * (magic bytes) và hash kiểm toàn vẹn (FR-DOC-02/03).
 */
final class DocumentService
{
    private const MAX_BYTES = 20_000_000;

    private const CATEGORY_BY_MIME = [
        'application/pdf' => 'PDF',
        'image/png' => 'IMAGE',
        'image/jpeg' => 'IMAGE',
        'image/webp' => 'IMAGE',
    ];

    public function __construct(private VirusScanService $scanner) {}

    public function upload(Project $project, UploadedFile|string $upload, string $category, ?string $notes = null): DocumentFile
    {
        $bytes = $this->read($upload, $originalName);
        $mime = $this->sniff($bytes);
        $this->guard($mime, strlen($bytes));
        $this->scanner->assertClean($bytes);
        $hash = hash('sha256', $bytes);
        $key = $this->storageKey($project->id, $hash, $originalName);

        if (! Storage::disk('private')->exists($key)) {
            Storage::disk('private')->put($key, $bytes, [
                'ContentType' => $mime,
                'ServerSideEncryption' => 'AES256',
            ]);
        }
        /** @var DocumentFile $file */
        $file = $project->files()->create([
            'storage_path' => $key,
            'mime' => $mime,
            'size' => strlen($bytes),
            'category' => $category,
            'checksum_sha256' => $hash,
            'scan_status' => 'CLEAN',
            'notes' => $notes,
            'uploaded_by' => auth()->id(),
        ]);

        return $file;
    }

    public function temporaryUrl(DocumentFile $file, int $expiresMinutes = 10): string
    {
        return Storage::disk('private')->temporaryUrl($file->storage_path, now()->addMinutes($expiresMinutes));
    }

    public function delete(DocumentFile $file): void
    {
        Storage::disk('private')->delete($file->storage_path);
        $file->delete();
    }

    /** @param string|null $originalName out-param */
    private function read(UploadedFile|string $upload, ?string &$originalName): string
    {
        if ($upload instanceof UploadedFile) {
            $originalName = $upload->getClientOriginalName();

            return (string) file_get_contents($upload->getRealPath());
        }

        $originalName = basename($upload);

        return (string) file_get_contents($upload);
    }

    private function sniff(string $bytes): string
    {
        // Chấp nhận magic bytes phổ biến, không tin vào đuôi tên tệp.
        return match (true) {
            str_starts_with($bytes, '%PDF-') => 'application/pdf',
            str_starts_with($bytes, "\x89PNG\r\n\x1a\n") => 'image/png',
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($bytes, 'RIFF') && str_starts_with($bytes, 'WEBP', 8) => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    private function guard(string $mime, int $size): void
    {
        if (! isset(self::CATEGORY_BY_MIME[$mime])) {
            throw new RuntimeException('Loại tệp không được hỗ trợ. Chỉ chấp nhận PDF, PNG, JPG, WebP.');
        }

        if ($size > self::MAX_BYTES) {
            throw new RuntimeException('Tệp vượt quá giới hạn 20 MB.');
        }
    }

    private function storageKey(string $projectId, string $hash, ?string $original): string
    {
        $extension = pathinfo((string) $original, PATHINFO_EXTENSION) ?: 'bin';

        return "projects/{$projectId}/{$hash}.{$extension}";
    }
}
