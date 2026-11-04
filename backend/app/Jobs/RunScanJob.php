<?php

namespace App\Jobs;

use App\Models\Scan;
use App\Services\JobService;
use App\Services\ScanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private string $scanId, private string $jobId) {}

    public function handle(ScanService $scans, JobService $jobs): void
    {
        $scan = Scan::findOrFail($this->scanId);
        $job = \App\Models\BackgroundJob::find($this->jobId);

        if ($job && $job->isCancelled()) {
            $scan->update(['status' => 'CANCELLED', 'finished_at' => now()]);

            return;
        }

        $jobs->track($job, function () use ($scans, $scan) {
            $scans->run($scan);

            return ['scan_id' => $scan->id];
        });
    }
}
