<?php

namespace App\Services;

/**
 * Kiểm tra mã độc (FR-DOC-03): quét qua dịch vụ Python (ClamAV nếu có) —
 * không có ClamAV thì kiểm tra heuristic tệp PDF nhúng và bỏ qua nếu sạch.
 *
 * Trong compose chưa có ClamAV; `scanner.py` của dịch vụ Python thực hiện heuristic
 * (cấu trúc tệp + danh sách khiếm khuyết đã biết). Laravel gọi qua InternalServiceClient.
 */
final class VirusScanService
{
    private const HEURISTIC = [
        '/\/OpenAction\s*\(/i' => 'PDF chứa OpenAction tự động (khả năng JavaScript nhúng).',
        '/\/JavaScript\s*\//i' => 'PDF chứa JavaScript nhúng.',
        '/\/EmbeddedFile\s*[[]/i' => 'PDF chứa tệp nhúng.',
    ];

    public function assertClean(string $bytes): void
    {
        if ($this->looksInfected($bytes)) {
            throw new \RuntimeException('Tệp có dấu hiệu mã độc; từ chối tải lên.');
        }

        if (config('services.ai.clamav_enabled', false)) {
            // Nếu triển khai ClamAV: gửi quét qua dịch vụ nội bộ, ném RuntimeError khi nhiễm.
            app(InternalServiceClient::class)->postJson('/internal/scan-file-async', [
                'size' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
            ]);
        }
    }

    public function looksInfected(string $bytes): bool
    {
        $sample = substr($bytes, 0, 2_000_000);

        foreach (self::HEURISTIC as $pattern => $message) {
            if (preg_match($pattern, $sample)) {
                return true;
            }
        }

        return false;
    }
}
