<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\JobType;
use App\Models\Assessment;
use App\Models\Project;
use App\Models\Report;
use App\Services\AuditLogger;
use App\Services\JobService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends ApiController
{
    public function __construct(
        private ReportService $reports,
        private JobService $jobs,
        private AuditLogger $audit,
    ) {}

    public function index(Project $project): JsonResponse
    {
        $this->guard($project);

        return $this->collection($project->reports()->latest()->get());
    }

    public function store(Request $request, Project $project, Assessment $assessment): JsonResponse
    {
        $this->guard($project);
        abort_unless($assessment->project_id === $project->id, 404);
        abort_unless($assessment->status === 'COMPLETED', 422, 'Đánh giá chưa hoàn thành.');

        $job = $this->jobs->dispatch(JobType::REPORT, [
            'assessment_id' => $assessment->id,
            'organization_id' => $project->organization_id,
        ], 'report:'.$assessment->id);

        Report::firstOrCreate(
            ['assessment_id' => $assessment->id],
            [
                'project_id' => $project->id,
                'organization_id' => $project->organization_id,
                'status' => 'QUEUED',
                'as_of_date' => $assessment->as_of_date,
                'rule_set_snapshot' => $assessment->rule_set_snapshot ?? [],
                'generated_by' => $request->user()->id,
            ],
        );

        $this->audit->log('REPORT_REQUESTED', $assessment, ['job_id' => $job->id], $project->organization_id);

        return $this->accepted(['job_id' => $job->id]);
    }

    public function download(Project $project, Report $report): StreamedResponse
    {
        $this->guard($project);
        abort_unless($report->project_id === $project->id, 404);
        abort_if(! $report->sha256, 422, 'Báo cáo chưa hoàn tất.');

        return response()->streamDownload(function () use ($report) {
            echo \Illuminate\Support\Facades\Storage::disk('private')->get($report->pdf_path);
        }, $report->pdf_path ? basename($report->pdf_path) : 'bao-cao.pdf', [
            'Content-Type' => 'application/pdf',
            'X-Report-Hash' => (string) $report->sha256,
        ]);
    }
}
