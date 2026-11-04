<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AdministrativeProcedure;
use App\Models\Project;
use App\Models\ProjectProcedure;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcedureController extends ApiController
{
    public function __construct(private AuditLogger $audit) {}

    /** Thủ tục áp dụng theo thẻ hoạt động của dự án (FR-PROC-01). */
    public function index(Request $request, ?Project $project = null): JsonResponse
    {
        $tagCodes = $project?->tagCodes() ?? [];
        $query = AdministrativeProcedure::query()
            ->with('requirements', 'provisions.document')
            ->when($tagCodes !== [], fn ($builder) => $builder->whereJsonContains('activity_tags', $tagCodes))
            ->when($request->string('q')->toString(), fn ($builder, $term) => $builder->where('name', 'ilike', "%{$term}%"))
            ->latest();

        return $this->collection($query->get());
    }

    public function show(string $id): JsonResponse
    {
        return $this->ok(['procedure' => AdministrativeProcedure::with('requirements', 'provisions.document')->findOrFail($id)]);
    }

    public function track(Request $request, Project $project): JsonResponse
    {
        $this->guard($project);

        return $this->collection(
            $project->procedures()->with(['procedure.requirements', 'documents'])->get(),
        );
    }

    public function storeTracked(Request $request, Project $project): JsonResponse
    {
        $this->guard($project);
        $data = $request->validate([
            'procedure_id' => ['required', 'uuid', 'exists:administrative_procedures,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $tracked = ProjectProcedure::firstOrCreate([
            'project_id' => $project->id,
            'procedure_id' => $data['procedure_id'],
        ], ['notes' => $data['notes'] ?? null, 'status' => 'NOT_STARTED']);

        $this->audit->log('PROCEDURE_TRACKED', $tracked, [], $project->organization_id);

        return $this->ok(['tracked' => $tracked->fresh(['procedure', 'documents'])], 201);
    }

    public function updateTracked(Request $request, Project $project, ProjectProcedure $tracked): JsonResponse
    {
        $this->guard($project);
        abort_unless($tracked->project_id === $project->id, 404);

        $data = $request->validate([
            'status' => ['nullable', 'string', 'in:NOT_STARTED,IN_PROGRESS,SUBMITTED,COMPLETED,ABANDONED'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'submitted_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
        ]);

        $tracked->update($data);
        $this->audit->log('PROCEDURE_UPDATED', $tracked);

        return $this->ok(['tracked' => $tracked->fresh(['procedure', 'documents'])]);
    }

    private function guard(Project $project): void
    {
        $user = request()->user();
        abort_unless($user->memberships()->where('organization_id', $project->organization_id)->exists(), 403);
    }
}
