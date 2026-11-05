<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Organization;
use App\Models\Project;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends ApiController
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $organization = $this->organization($request);

        $projects = Project::query()
            ->where('organization_id', $organization->id)
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->when($request->string('q')->toString(), function ($query, $term) {
                $query->where(fn ($builder) => $builder->where('name', 'ilike', "%{$term}%")->orWhere('description', 'ilike', "%{$term}%"));
            })
            ->latest()
            ->paginate(min(50, (int) $request->integer('per_page', 20)));

        return $this->collection($projects->items(), [
            'meta' => [
                'page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'total' => $projects->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $this->organization($request, requireWrite: true);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'url', 'max:500'],
            'platform' => ['nullable', 'string', 'max:60'],
            'markets' => ['nullable', 'array', 'max:10'],
            'markets.*' => ['string', 'size:2'],
            'product_type' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(['DRAFT', 'ACTIVE', 'ARCHIVED'])],
        ]);

        $project = Project::create(array_merge($data, [
            'organization_id' => $organization->id,
            'status' => $data['status'] ?? 'DRAFT',
            'created_by' => $request->user()->id,
        ]));

        $this->audit->log('PROJECT_CREATED', $project, [], $organization->id);

        return $this->ok(['project' => $project], 201);
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        $project->load(['profile', 'activityTags', 'latestAssessment']);

        return $this->ok(['project' => $project]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project, requireWrite: true);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'url', 'max:500'],
            'platform' => ['nullable', 'string', 'max:60'],
            'markets' => ['nullable', 'array', 'max:10'],
            'markets.*' => ['string', 'size:2'],
            'product_type' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(['DRAFT', 'ACTIVE', 'ARCHIVED'])],
        ]);

        $project->update($data);
        $this->audit->log('PROJECT_UPDATED', $project);

        return $this->ok(['project' => $project]);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorizeProject($project, requireWrite: true);
        $project->delete();
        $this->audit->log('PROJECT_DELETED', $project);

        return $this->ok();
    }

    private function organization(Request $request, bool $requireWrite = false): Organization
    {
        $organizationId = (string) $request->header('X-Organization-Id', '');
        $user = $request->user();

        if ($organizationId !== '') {
            $membership = $user->memberships()->where('organization_id', $organizationId)->first();
            if (! $membership) {
                abort(403, 'Bạn không thuộc tổ chức này.');
            }
            abort_if($requireWrite && ! $membership->role->canWrite(), 403, 'Cần quyền thành viên được phép tạo dự án trong tổ chức.');

            return $membership->organization;
        }

        $organization = $user->personalOrganization() ?? $user->organizations()->first();
        if (! $organization) {
            abort(404, 'Tổ chức không tồn tại.');
        }

        return $organization;
    }

    private function authorizeProject(Project $project, bool $requireWrite = false): void
    {
        $user = request()->user();
        $member = $user->memberships()->where('organization_id', $project->organization_id)->first();

        if (! $member) {
            abort(403, 'Không có quyền truy cập dự án này.');
        }

        // FR-AUTH-03 / SRS 2.2: VIEWER chỉ xem; sửa/xóa dự án cần MEMBER trở lên.
        if ($requireWrite && ! $member->role->canWrite()) {
            abort(403, 'Cần quyền thành viên được phép chỉnh sửa trong tổ chức.');
        }
    }
}
