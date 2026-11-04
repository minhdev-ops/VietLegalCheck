<?php

namespace App\Services;

use App\Domain\Enums\JobStatus;
use App\Domain\Enums\JobType;
use App\Jobs\GenerateReportJob;
use App\Jobs\RunAssessmentJob;
use App\Jobs\RunScanJob;
use App\Models\BackgroundJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Tác vụ nền (SRS 6.4): Laravel ghi một bản ghi `background_jobs` rồi mới giao việc.
 * Khóa idempotency chống tạo bản sao khi thử lại (NFR-08, AC-25).
 */
final class JobService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(
        JobType $type,
        array $payload,
        ?string $idempotencyKey = null,
        ?string $queue = 'default',
    ): BackgroundJob {
        $idempotencyKey = $idempotencyKey ?? (string) Str::uuid();

        return JobService::withoutOverlaps($idempotencyKey, function () use ($type, $payload, $idempotencyKey, $queue) {
            /** @var BackgroundJob $job */
            $job = BackgroundJob::firstWhere('idempotency_key', $idempotencyKey);

            if ($job) {
                // Tác vụ cũ vẫn chưa kết thúc: trả lại, không tạo bản sao (AC-25).
                if (! $job->status->isTerminal()) {
                    return $job;
                }

                // Tác vụ đã kết thúc: cho phép chạy lại bằng key mới sinh từ key cũ.
                return $this->create($type, $payload, $idempotencyKey.'-'.now()->timestamp, $queue);
            }

            $record = $this->create($type, $payload, $idempotencyKey, $queue);
            $this->DispatchWork($type, $record);

            return $record;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(JobType $type, array $payload, string $idempotencyKey, string $queue = 'default'): BackgroundJob
    {
        /** @var BackgroundJob $job */
        $job = BackgroundJob::create([
            'job_type' => $type,
            'payload' => $payload,
            'status' => JobStatus::QUEUED,
            'idempotency_key' => $idempotencyKey,
            'user_id' => Auth::id(),
            'organization_id' => $payload['organization_id'] ?? null,
        ]);

        return $job;
    }

    private function DispatchWork(JobType $type, BackgroundJob $job): void
    {
        $payload = $job->payload ?? [];
        match ($type) {
            JobType::SCAN => RunScanJob::dispatch($payload['scan_id'] ?? $job->id, $job->id)->onQueue('high'),
            JobType::ASSESSMENT => RunAssessmentJob::dispatch($payload['assessment_id'] ?? null, $job->id),
            JobType::REPORT => GenerateReportJob::dispatch($payload['assessment_id'] ?? null, $job->id),
            JobType::AI_EXPLAIN, JobType::MONITOR => null,
        };
    }

    public function track(BackgroundJob $job, callable $work): mixed
    {
        $job->update(['status' => JobStatus::RUNNING, 'started_at' => now(), 'attempts' => $job->attempts + 1]);

        try {
            $result = $work($job);
            $job->update([
                'status' => JobStatus::SUCCEEDED,
                'result' => is_array($result) ? $result : null,
                'finished_at' => now(),
            ]);

            return $result;
        } catch (\Throwable $throwable) {
            $job->update([
                'status' => JobStatus::FAILED,
                'last_error' => $throwable->getMessage(),
                'finished_at' => now(),
            ]);

            throw $throwable;
        }
    }

    public function cancel(BackgroundJob $job): bool
    {
        if ($job->status->isTerminal()) {
            return false;
        }
        $job->update(['status' => JobStatus::CANCELLED, 'finished_at' => now()]);

        return true;
    }

    public static function withoutOverlaps(string $key, callable $callback): mixed
    {
        $lock = cache()->lock("job:{$key}", 30);

        try {
            $lock->block(5);

            return $callback();
        } finally {
            optional($lock)->release();
        }
    }
}
