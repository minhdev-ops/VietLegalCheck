<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Báo cáo PDF (SRS 4.12, FR-REPORT-*). PDF là bản sao cố định: hash SHA-256 ghi vào
 * DB và in trong tài liệu; không tạo lại khi đã có hash (tính bất biến, FR-REPORT-06).
 */
final class ReportService
{
    public function generate(Assessment $assessment): Report
    {
        $existing = Report::where('assessment_id', $assessment->id)->whereNotNull('sha256')->first();
        if ($existing) {
            return $existing;
        }

        $assessment->load(['project.organization']);
        $findings = $assessment->findings()
            ->with('ruleVersion.rule')
            ->orderBy('legal_group')
            ->orderByRaw("(case severity when 'CRITICAL' then 1 when 'HIGH' then 2 when 'MEDIUM' then 3 when 'LOW' then 4 else 5 end)")
            ->get();

        $pdf = Pdf::loadView('reports.assessment', [
            'assessment' => $assessment,
            'findings' => $findings,
            'as_of_date' => Carbon::parse($assessment->as_of_date)->format('d/m/Y'),
            'generated_at' => now()->format('H:i:s d/m/Y'),
        ])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', false);

        $bytes = $pdf->output();
        $sha = hash('sha256', $bytes);
        $key = 'reports/'.$assessment->project_id.'/'.$sha.'.pdf';

        if (! Storage::disk('private')->exists($key)) {
            Storage::disk('private')->put($key, $bytes, [
                'ContentType' => 'application/pdf',
                'ServerSideEncryption' => 'AES256',
            ]);
        }

        /** @var Report $report */
        return Report::updateOrCreate(
            ['assessment_id' => $assessment->id],
            [
                'project_id' => $assessment->project_id,
                'organization_id' => $assessment->project->organization_id,
                'status' => 'COMPLETED',
                'as_of_date' => $assessment->as_of_date,
                'sha256' => $sha,
                'pdf_path' => $key,
                                                'generated_by' => auth()->id(),
            ],
        );
    }

    public function temporaryUrl(Report $report, int $expiresMinutes = 15): string
    {
        return Storage::disk('private')->temporaryUrl($report->pdf_path, now()->addMinutes($expiresMinutes));
    }
}
