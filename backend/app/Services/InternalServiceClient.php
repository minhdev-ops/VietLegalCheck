<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Client gọi dịch vụ Python nội bộ với chữ ký HMAC xoay vòng (SRS 6.4).
 *
 * Dịch vụ không nhận kết nối từ Internet; mọi request phải ký `{timestamp}.{raw_body}`.
 * Body là JSON được rã thành chuỗi cố định để hai bên tính cùng một chữ ký.
 */
final class InternalServiceClient
{
    private const TIMEOUT = 200;

    public function __construct(private HttpFactory $http) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function postJson(string $path, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('Không thể tạo payload JSON cho dịch vụ nội bộ.');
        }

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $this->serviceKey());

        // Gửi đúng chuỗi byte đã ký: ->post($url, $payload) sẽ re-encode JSON
        // theo cách của Laravel và làm lệch chữ ký HMAC bên dịch vụ Python.
        $response = $this->http
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-VL-Timestamp' => $timestamp,
                'X-VL-Signature' => $signature,
            ])
            ->timeout(self::TIMEOUT)
            ->withBody($body, 'application/json')
            ->post($this->url($path));

        return $this->parse($response);
    }

    public function health(): array
    {
        $response = $this->http->timeout(10)->get($this->url('/health'));

        return $this->parse($response);
    }

    private function url(string $path): string
    {
        $base = rtrim((string) config('services.ai.url'), '/').'/' ?? 'http://ai:8100/';

        return $base.ltrim($path, '/');
    }

    private function serviceKey(): string
    {
        $key = (string) config('services.ai.key');
        if ($key === '') {
            Log::warning('AI_SERVICE_KEY chưa cấu hình; dịch vụ nội bộ sẽ từ chối yêu cầu.');
        }

        return $key;
    }

    /** @return array<string, mixed> */
    private function parse(Response $response): array
    {
        if (! $response->successful()) {
            throw new RuntimeException(
                'Dịch vụ nội bộ trả lỗi '.$response->status().': '.substr((string) $response->body(), 0, 200)
            );
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Dịch vụ nội bộ trả định dạng không hợp lệ.');
        }

        return $payload;
    }
}
