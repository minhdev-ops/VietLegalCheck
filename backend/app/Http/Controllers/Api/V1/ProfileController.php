<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\RuleEngine\ClassificationEngine;
use App\Domain\RuleEngine\RuleEvaluator;
use App\Models\ActivityTagRule;
use App\Models\Project;
use App\Models\ProjectProfile;
use App\Models\QuestionSet;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Hồ sơ dự án + bảng câu hỏi (SRS 4.4): lưu nháp từng bước, phân loại theo câu trả lời
 * và khóa hồ sơ khi đã hoàn thành (FR-CLASS-02).
 */
class ProfileController extends ApiController
{
    public function __construct(
        private ClassificationEngine $classification,
        private AuditLogger $audit,
    ) {}

    public function show(Project $project): JsonResponse
    {
        $this->guard($project);
        $set = QuestionSet::where('status', 'PUBLISHED')->latest('version')->firstOrFail();

        return $this->ok([
            'profile' => $project->profile,
            'question_set' => $set->load('questions'),
        ]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->guard($project);
        abort_if($project->status === 'ARCHIVED', 422, 'Dự án đã lưu trữ; không cập nhật hồ sơ.');

        $data = $request->validate([
            'answers' => ['required', 'array'],
            'completed_step' => ['nullable', 'integer', 'min:0', 'max:20'],
            'status' => ['nullable', 'string', 'in:DRAFT,COMPLETED'],
        ]);

        $profile = DB::transaction(function () use ($project, $data) {
            $set = QuestionSet::where('status', 'PUBLISHED')->latest('version')->firstOrFail();

            /** @var ProjectProfile $profile */
            $profile = $project->profile()->updateOrCreate(
                ['project_id' => $project->id],
                [
                    'question_set_version' => $set->version,
                    'answers' => $data['answers'],
                    'completed_step' => $data['completed_step'] ?? ($project->profile?->completed_step ?? 0),
                    'status' => $data['status'] ?? 'DRAFT',
                ],
            );

            // Phân loại lại ngay để trả về thẻ (FR-CLASS-02);reasons đi kèm.
            $assigned = $this->classification->classify($data['answers']);
            $project->activityTags()->where('assigned_by', 'RULE_ENGINE')->delete();
            foreach ($assigned as $tag) {
                $project->activityTags()->create([
                    'tag_code' => $tag['tag_code'],
                    'assigned_by' => 'RULE_ENGINE',
                    'reason' => $tag['reason'],
                    'confidence' => $tag['confidence'],
                ]);
            }

            $profile->derived_tags = $assigned;
            $profile->save();

            return $profile;
        });

        $this->audit->log('PROFILE_UPDATED', $profile, ['step' => $profile->completed_step], $project->organization_id);

        return $this->ok(['profile' => $profile->fresh()]);
    }

    public function tags(Project $project): JsonResponse
    {
        $this->guard($project);

        return $this->ok([
            'tags' => $project->activityTags()->get(),
            'available' => \App\Models\ActivityTag::orderBy('code')->get(),
        ]);
    }

    public function overrideTag(Request $request, Project $project): JsonResponse
    {
        $this->guard($project);
        $data = $request->validate([
            'tag_code' => ['required', 'string', 'max:64'],
            'add' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        ClassificationEngine::userOverride($project, $data['tag_code'], (bool) $data['add'], $data['reason'] ?? null);
        $this->audit->log('TAG_OVERRIDDEN', $project, $data, $project->organization_id);

        return $this->ok(['tags' => $project->activityTags()->get()]);
    }

    private function guard(Project $project): void
    {
        $user = request()->user();
        abort_unless($user->memberships()->where('organization_id', $project->organization_id)->exists(), 403);
    }
}
