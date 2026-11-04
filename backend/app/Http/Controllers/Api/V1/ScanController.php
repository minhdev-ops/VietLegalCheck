<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\ScanMode;
use App\Models\Project;
use App\Models\Scan;
use App\Services\AuditLogger;
use App\Services\ScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScanController extends ApiController
{
    public function __construct(private ScanService $scans, private AuditLogger $audit) {}

    public function index(Project $project): JsonResponse
    {
        $this->guard($project);

        return $this->collection($project->scans()->latest()->get());
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->guard($project);
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:500'],
            'mode' => ['nullable', Rule::in(['LIGHT', 'DEEP'])],
        ]);

        $created = $this->scans->create($project, ScanMode::from($data['mode'] ?? 'LIGHT'), $data['url']);
        $this->audit->log('SCAN_CREATED', $project, $created, $project->organization_id);

        return $this->accepted($created);
    }

    public function show(Project $project, Scan $scan): JsonResponse
    {
        $this->guard($project);
        abort_unless($scan->project_id === $project->id, 404);
        $scan->load('pages');

        return $this->ok(['scan' => $scan]);
    }

    public function cancel(Project $project, Scan $scan): JsonResponse
    {
        $this->guard($project);
        abort_unless($scan->project_id === $project->id, 404);
        abort_if($scan->status === 'COMPLETED', 422, 'Lần quét đã kết thúc.');

        $this->scans->cancel($scan);
        $this->audit->log('SCAN_CANCELLED', $scan);

        return $this->ok();
    }

    public function verifyDomain(Request $request, Project $project): JsonResponse
    {
        $this->guard($project);
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:253'],
            'method' => ['nullable', Rule::in(['META_TAG', 'DNS_TXT'])],
        ]);

        $verification = $this->scans->createVerification(
            $project,
            strtolower($data['domain']),
            $data['method'] ?? 'META_TAG',
        );

        return $this->ok(['verification' => $verification], 201);
    }

    public function checkDomain(Project $project, string $domain): JsonResponse
    {
        $this->guard($project);
        $verification = $project->domainVerifications()->where('domain', strtolower($domain))->firstOrFail();

        $verified = $this->scans->checkVerification($verification);

        return $this->ok(['verification' => $verification->fresh(), 'verified' => $verified]);
    }

    private function guard(Project $project): void
    {
        $user = request()->user();
        abort_unless($user->memberships()->where('organization_id', $project->organization_id)->exists(), 403);
    }
}
