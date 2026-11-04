<?php

namespace App\Jobs;

use App\Models\Assessment;
use App\Services\JobService;
use App\Services\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private string $assessmentId, private string $jobId) {}

    public function handle(ReportService $reports, JobService $jobs): void
    {
        $assessment = Assessment::findOrFail($this->assessmentId);
        $job = \App\Models\BackgroundJob::find($this->jobId);

        if ($job && $job->isCancelled()) {
            return;
        }

        $jobs->track($job, function () use ($reports, $assessment) {
            $reports->generate($assessment);

            return ['assessment_id' => $assessment->id];
        });
    }
}
