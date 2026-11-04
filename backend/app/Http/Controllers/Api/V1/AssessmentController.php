<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\JobType;
use App\Domain\RuleEngine\AssessmentEngine;
use App\Models\Assessment;
use App\Models\AssessmentFinding;
use App\Models\Project;
use App\Services\AuditLogger;
use App\Services\JobService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentController extends ApiController
{
    public function __construct(
        private AssessmentEngine $engine,
        private JobService $jobs,
        private AuditLogger $audit,
    ) {}

    public function index(Project $project): JsonResponse
    {
        $this->guard($project);

        return $this->collection($project->assessments()->latest()->get());
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->guard($project);

        $data = $request->validate([
            'as_of_date' => ['nullable', 'date'],
        ]);

        $asOfDate = isset($data['as_of_date']) ? Carbon::parse($data['as_of_date']) : Carbon::today();

        $assessment = Assessment::create([
            'organization_id' => $project->organization_id,
            'project_id' => $project->id,
            'as_of_date' => $asOfDate->toDateString(),
            'question_set_version' => $project->profile?->question_set_version ?? 1,
            'rule_set_snapshot' => [],
            'status' => 'QUEUED',
            'created_by' => $request->user()->id,
        ]);

        $job = $this->jobs->dispatch(JobType::ASSESSMENT, [
            'project_id' => $project->id,
            'organization_id' => $project->organization_id,
            'as_of_date' => $asOfDate->toDateString(),
            'question_set_version' => $project->profile?->question_set_version ?? 1,
            'assessment_id' => $assessment->id,
        ], 'assessment:'.$project->id.':'.$asOfDate->toDateString());

        $assessment->update(['job_id' => $job->id]);

        $this->audit->log('ASSESSMENT_CREATED', $assessment, [], $project->organization_id);

        return $this->accepted(['assessment_id' => $assessment->id, 'job_id' => $job->id]);
    }

    public function show(Project $project, Assessment $assessment): JsonResponse
    {
        $this->guard($project);
        abort_unless($assessment->project_id === $project->id, 404);

        $assessment->load(['findings' => fn ($query) => $query->orderBy('legal_group')->orderBy('severity')]);

        return $this->ok(['assessment' => $assessment]);
    }

    public function updateFinding(Request $request, Project $project, Assessment $assessment, AssessmentFinding $finding): JsonResponse
    {
        $this->guard($project);
        abort_unless($finding->assessment_id === $assessment->id, 404);

        $data = $request->validate([
            'fulfillment' => ['nullable', 'string', 'in:NOT_STARTED,IN_PROGRESS,SELF_DECLARED_DONE,EVIDENCE_ATTACHED,EXPERT_VERIFIED'],
            'status_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $finding->update($data);
        $this->audit->log('FINDING_UPDATED', $finding);

        return $this->ok(['finding' => $finding->fresh()]);
    }

    /** Chạy đồng bộ (dùng cho test và cho dự án nhỏ) — vẫn ghi findings đầy đủ. */
    public function run(Project $project, Assessment $assessment): JsonResponse
    {
        $this->guard($project);

        $profile = $project->profile;
        abort_unless($profile?->answers, 422, 'Chưa có hồ sơ dự án. Hoàn thành bảng khai báo trước.');

        $assessment->update(['status' => 'RUNNING', 'started_at' => now()]);
        $asOfDate = Carbon::parse($assessment->as_of_date);

        try {
            $result = $this->engine->run($project, (array) $profile->answers, $asOfDate);

            DB::transaction(function () use ($assessment, $result, $project) {
                $assessment->findings()->delete();
                foreach ($result['findings'] as $finding) {
                    $finding->assessment_id = $assessment->id;
                    $finding->save();
                }

                $project->activityTags()->delete();
                foreach ($result['tags'] as $tag) {
                    $project->activityTags()->create([
                        'tag_code' => $tag['tag_code'],
                        'assigned_by' => 'RULE_ENGINE',
                        'reason' => $tag['reason'],
                        'confidence' => $tag['confidence'],
                    ]);
                }

                $assessment->update([
                    'status' => 'COMPLETED',
                    'overall_status' => $this->overall($result['applicability_counts']),
                    'rule_set_snapshot' => $result['rule_set_snapshot'],
                    'completed_at' => now(),
                ]);
            });
        } catch (\Throwable $throwable) {
            $assessment->update(['status' => 'FAILED', 'error' => $throwable->getMessage()]);

            throw $throwable;
        }

        $assessment->load('findings');

        return $this->ok(['assessment' => $assessment]);
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function overall(array $counts): string
    {
        if (($counts['APPLICABLE'] ?? 0) > 0) {
            return 'ACTION_REQUIRED';
        }
        if (($counts['NEED_MORE_INFO'] ?? 0) > 0) {
            return 'INCOMPLETE';
        }
        if (($counts['NEED_EXPERT_REVIEW'] ?? 0) > 0) {
            return 'NEEDS_EXPERT_REVIEW';
        }

        return 'COMPLIANT';
    }

    private function guard(Project $project): void
    {
        $user = request()->user();
        abort_unless($user->memberships()->where('organization_id', $project->organization_id)->exists(), 403);
    }
}
