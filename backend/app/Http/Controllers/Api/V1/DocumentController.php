<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Project;
use App\Services\AuditLogger;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends ApiController
{
    public function __construct(private DocumentService $documents, private AuditLogger $audit) {}

    public function index(Project $project): JsonResponse
    {
        $this->guard($project);

        return $this->collection($project->files()->latest()->get());
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->guard($project);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'category' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $file = $this->documents->upload($project, $data['file'], $data['category'] ?? 'OTHER', $data['notes'] ?? null);
        $this->audit->log('DOCUMENT_UPLOADED', $file, ['size' => $file->size], $project->organization_id);

        return $this->ok(['file' => $file], 201);
    }

    public function download(Project $project, string $fileId): StreamedResponse
    {
        $this->guard($project);
        $file = $project->files()->where('id', $fileId)->firstOrFail();

        return response()->streamDownload(function () use ($file) {
            echo \Illuminate\Support\Facades\Storage::disk('private')->get($file->storage_path);
        }, $file->original_name, [
            'Content-Type' => $file->mime,
            'X-File-Hash' => (string) $file->checksum_sha256,
        ]);
    }

    public function destroy(Project $project, string $fileId): JsonResponse
    {
        $this->guard($project);
        $file = $project->files()->where('id', $fileId)->firstOrFail();
        $this->documents->delete($file);
        $this->audit->log('DOCUMENT_DELETED', $file);

        return $this->ok();
    }

    private function guard(Project $project): void
    {
        $user = request()->user();
        abort_unless($user->memberships()->where('organization_id', $project->organization_id)->exists(), 403);
    }
}
