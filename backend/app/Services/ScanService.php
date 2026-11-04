<?php

namespace App\Services;

use App\Domain\Enums\JobType;
use App\Domain\Enums\ScanMode;
use App\Models\DomainVerification;
use App\Models\Project;
use App\Models\Scan;
use App\Models\ScanPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Quét website (SRS 4.9). Kiểm soát lạm dụng: quét sâu chỉ khi đã xác minh sở hữu
 * (FR-SCAN-07); quét nhẹ cho URL chưa xác minh. Công việc crawler nằm ở dịch vụ Python.
 */
final class ScanService
{
    public function __construct(private InternalServiceClient $client, private JobService $jobs) {}

    public function create(Project $project, ScanMode $mode, string $url): array
    {
        $this->guard($project, $mode, $url);

        /** @var Scan|null $existing */
        $existing = Scan::where('target_url', $url)
            ->where('project_id', $project->id)
            ->where('status', 'QUEUED')
            ->first();
        if ($existing) {
            return ['scan_id' => $existing->id, 'job_id' => $existing->job_id];
        }

        $scan = Scan::create([
            'organization_id' => $project->organization_id,
            'project_id' => $project->id,
            'target_url' => $url,
            'mode' => $mode,
            'status' => 'QUEUED',
            'criteria_version' => Scan::CRITERIA_VERSION,
            'created_by' => auth()->id(),
        ]);

        $job = $this->jobs->dispatch(
            JobType::SCAN,
            [
                'project_id' => $project->id,
                'organization_id' => $project->organization_id,
                'url' => $url,
                'mode' => $mode->value,
                'criteria_version' => Scan::CRITERIA_VERSION,
                'scan_id' => $scan->id,
            ],
            'scan:'.$project->id.':'.$url.':'.$mode->value,
            'high',
        );

        $scan->update(['job_id' => $job->id]);

        return ['scan_id' => $scan->id, 'job_id' => $job->id];
    }

    public function run(Scan $scan): void
    {
        $job = $scan->job;
        if ($job && $job->isCancelled()) {
            $scan->update(['status' => 'CANCELLED', 'finished_at' => now()]);

            return;
        }

        $scan->update(['status' => 'RUNNING', 'started_at' => now()]);

        try {
            $payload = $this->client->postJson('/internal/scan', [
                'job_id' => $scan->job_id,
                'url' => $scan->target_url,
                'mode' => $scan->mode->value,
                'criteria_version' => $scan->criteria_version,
                'limits' => [
                    'max_pages' => (int) config('services.ai.scan_max_pages', 15),
                    'max_bytes' => (int) config('services.ai.scan_max_bytes', 3_000_000),
                    'timeout_seconds' => (int) config('services.ai.scan_timeout', 20),
                ],
            ]);

            $this->applyResult($scan, $payload);
        } catch (\Throwable $throwable) {
            $scan->update([
                'status' => 'FAILED',
                'error' => $throwable->getMessage(),
                'finished_at' => now(),
            ]);

            throw $throwable;
        }
    }

    public function cancel(Scan $scan): void
    {
        if ($scan->job_id) {
            $this->client->postJson('/internal/scan/'.$scan->job_id.'/cancel', []);
        }
        if ($scan->job) {
            $this->jobs->cancel($scan->job);
        }
        $scan->update(['status' => 'CANCELLED', 'finished_at' => now()]);
    }

    /** FR-SCAN-07: xác minh sở hữu tên miền. */
    public function createVerification(Project $project, string $domain, string $method): DomainVerification
    {
        return DB::transaction(function () use ($project, $domain, $method) {
            return DomainVerification::updateOrCreate(
                ['project_id' => $project->id, 'domain' => $domain],
                [
                    'method' => $method,
                    'token' => 'vlc-'.Str::random(40),
                    'status' => 'PENDING',
                ],
            );
        });
    }

    public function checkVerification(DomainVerification $verification): bool
    {
        $verification->increment('attempts');
        $verification->update(['last_checked_at' => now()]);

        $verified = $verification->method === 'META_TAG'
            ? $this->checkMetaTag($verification)
            : $this->checkDnsTxt($verification);

        $verification->update([
            'status' => $verified ? 'VERIFIED' : 'PENDING',
            'verified_at' => $verified ? now() : null,
        ]);

        return $verified;
    }

    private function guard(Project $project, ScanMode $mode, string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            abort(422, 'URL không hợp lệ.');
        }

        if ($mode === ScanMode::DEEP) {
            $verified = DomainVerification::where('project_id', $project->id)
                ->where('domain', $host)
                ->where('status', 'VERIFIED')
                ->exists();

            if (! $verified) {
                abort(403, 'Quét sâu chỉ áp dụng cho tên miền đã xác minh quyền sở hữu (FR-SCAN-07).');
            }
        }

        $existing = Scan::where('project_id', $project->id)
            ->where('target_url', $url)
            ->where('status', 'RUNNING')
            ->exists();

        if ($existing) {
            abort(409, 'Đang có một lần quét khác cho URL này.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function applyResult(Scan $scan, array $payload): void
    {
        $status = (string) ($payload['status'] ?? 'FAILED');

        if ($status === 'BLOCKED') {
            $scan->update([
                'status' => 'BLOCKED',
                'blocked_reason' => $payload['blocked_reason'] ?? null,
                'finished_at' => now(),
            ]);

            return;
        }

        if ($status !== 'COMPLETED') {
            $scan->update([
                'status' => 'FAILED',
                'error' => $payload['error'] ?? 'Dịch vụ quét báo thất bại.',
                'finished_at' => now(),
            ]);

            return;
        }

        $pages = collect($payload['pages'] ?? []);

        DB::transaction(function () use ($scan, $payload, $pages) {
            foreach ($pages as $page) {
                $snapshotPath = $this->storeSnapshot($scan, $page);

                ScanPage::create([
                    'scan_id' => $scan->id,
                    'url' => $page['url'] ?? '',
                    'http_status' => $page['http_status'] ?? null,
                    'title' => $page['title'] ?? null,
                    'content_hash' => $page['content_hash'] ?? null,
                    'snapshot_path' => $snapshotPath,
                    'signals' => $page['signals'] ?? null,
                    'evidence' => $page['evidence'] ?? null,
                    'fetch_error' => $page['fetch_error'] ?? null,
                ]);
            }

            $scan->update([
                'status' => 'COMPLETED',
                'resolved_ip' => $payload['resolved_ip'] ?? null,
                'site' => $payload['site'] ?? null,
                'findings' => $payload['findings'] ?? null,
                'page_count' => $pages->count(),
                'finished_at' => now(),
            ]);
        });
    }

    /** @param array<string, mixed> $page */
    private function storeSnapshot(Scan $scan, array $page): ?string
    {
        $html = $page['snapshot_html'] ?? null;
        if (! is_string($html) || $html === '') {
            return null;
        }

        $hash = hash('sha256', $html);
        $path = "scans/{$scan->id}/".substr($hash, 0, 16).'.html';

        if (! Storage::disk('private')->exists($path)) {
            Storage::disk('private')->put($path, $html);
        }

        return $path;
    }

    private function checkMetaTag(DomainVerification $verification): bool
    {
        try {
            $response = $this->client->postJson('/internal/probe', [
                'url' => 'https://'.$verification->domain.'/',
                'expect' => 'meta:'.$verification->token,
            ]);

            return (bool) ($response['found'] ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkDnsTxt(DomainVerification $verification): bool
    {
        try {
            $records = dns_get_record($verification->domain, DNS_TXT);
        } catch (\Throwable) {
            return false;
        }

        foreach ($records as $record) {
            if (str_contains((string) ($record['txt'] ?? ''), $verification->token)) {
                return true;
            }
        }

        return false;
    }
}
