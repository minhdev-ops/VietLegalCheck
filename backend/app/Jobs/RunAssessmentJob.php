<?php

namespace App\Jobs;

use App\Domain\RuleEngine\AssessmentEngine;
use App\Models\Assessment;
use App\Models\Project;
use App\Services\JobService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class RunAssessmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private string $assessmentId, private string $jobId) {}

    public function handle(AssessmentEngine $engine, JobService $jobs): void
    {
        $assessment = Assessment::findOrFail($this->assessmentId);
        $job = \App\Models\BackgroundJob::find($this->jobId);

        if ($job && $job->isCancelled()) {
            $assessment->update(['status' => 'CANCELLED']);

            return;
        }

        $project = Project::with('profile')->findOrFail($assessment->project_id);
        $jobs->track($job, function () use ($engine, $assessment, $project) {
            $asOfDate = Carbon::parse($assessment->as_of_date);
            $result = $engine->run($project, (array) ($project->profile?->answers ?? []), $asOfDate);

            DB::transaction(function () use ($assessment, $result, $project) {
                $assessment->findings()->delete();
                foreach ($result['findings'] as $finding) {
                    $finding->assessment_id = $assessment->id;
                    $finding->save();
                }

                $project->activityTags()->where('assigned_by', 'RULE_ENGINE')->delete();
                foreach ($result['tags'] as $tag) {
                    $project->activityTags()->create([
                        'tag_code' => $tag['tag_code'],
                        'assigned_by' => 'RULE_ENGINE',
                        'reason' => $tag['reason'],
                        'confidence' => $tag['confidence'],
                    ]);
                }

                $counts = $result['applicability_counts'];
                $assessment->update([
                    'status' => 'COMPLETED',
                    'rule_set_snapshot' => $result['rule_set_snapshot'],
                    'completed_at' => now(),
                ]);
                unset($counts);
            });

            return ['assessment_id' => $assessment->id];
        });
    }
}
