<?php

namespace App\Domain\RuleEngine;

use App\Domain\Enums\Applicability;
use App\Domain\Enums\Fulfillment;
use App\Models\Assessment;
use App\Models\AssessmentFinding;
use App\Models\Project;
use Carbon\Carbon;

/**
 * Chạy bộ máy quy tắc cho một dự án tại một ngày pháp lý (SRS 4.6, 4.7, 6.3).
 *
 * Đầu ra là findings законченными: applicability + fulfillment tách biệt, vết giải thích
 * TRUE/FALSE/UNKNOWN, căn cứ điều khoản, và câu hỏi cần bổ sung khi NEED_MORE_INFO.
 */
final class AssessmentEngine
{
    public function __construct(
        private RuleEvaluator $evaluator,
        private RuleSetResolver $resolver,
        private ClassificationEngine $classification,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     * @return array{
     *   applicability_counts: array<string, int>,
     *   findings: \Illuminate\Support\Collection<int, AssessmentFinding>,
     *   tags: array<int, array{tag_code: string, reason: string, confidence: float, question_codes: array<int, string>}>
     * }
     */
    public function run(Project $project, array $answers, Carbon $asOfDate): array
    {
        $facts = FactSheet::fromAnswers($answers, $this->derivedFacts($project, $answers));
        $resolved = $this->resolver->resolve($asOfDate);

        $findings = [];
        foreach ($resolved['versions'] as $version) {
            $result = $this->evaluator->evaluate($version->condition_json, $facts);
            $resultJson = $version->result_json ?? [];
            $basis = $this->resolver->basisFor($version, $asOfDate);

            $applicability = $this->applicability($result, $resultJson, $basis['stale']);
            if ($applicability === Applicability::NOT_APPLICABLE && ! ($resultJson['report_not_applicable'] ?? false)) {
                continue; // SRS: không làm шум những nghĩa vụ chắc chắn không áp dụng.
            }

            $findings[] = $this->makeFinding(
                assessment: null,
                version: $version,
                applicability: $applicability,
                result: $result,
                resultJson: $resultJson,
                basis: $basis['provisions'],
                staleReason: $basis['reason'],
            );
        }

        $applicabilityCounts = [];
        foreach ($findings as $finding) {
            $key = $finding->applicability->value;
            $applicabilityCounts[$key] = ($applicabilityCounts[$key] ?? 0) + 1;
        }

        return [
            'applicability_counts' => $applicabilityCounts,
            'findings' => collect($findings),
            'tags' => $this->classification->classify($answers),
            'rule_set_snapshot' => $resolved['snapshot'],
        ];
    }

    /**
     * @param  array<string, mixed>  $resultJson
     */
    private function applicability(EvaluationResult $result, array $resultJson, bool $staleBasis): Applicability
    {
        $applicability = match ($result->value) {
            Tri::TRUE => RuleSetResolver::applicabilityFromString((string) ($resultJson['applicability'] ?? Applicability::APPLICABLE->value)),
            Tri::FALSE => ($resultJson['report_not_applicable'] ?? false)
                ? Applicability::NOT_APPLICABLE
                : Applicability::NOT_APPLICABLE,
            Tri::UNKNOWN => RuleSetResolver::applicabilityFromString((string) ($resultJson['unknown_applicability'] ?? Applicability::NEED_MORE_INFO->value)),
        };

        if ($staleBasis && $applicability === Applicability::APPLICABLE) {
            // BR-06: căn cứ chưa xác định hiệu lực ⇒ không kết luận "có khả năng áp dụng" như hiện hành.
            return Applicability::NEED_EXPERT_REVIEW;
        }

        return $applicability;
    }

    /**
     * @param  array<string, mixed>  $resultJson
     * @param  array<int, array<string, mixed>>  $basis
     */
    private function makeFinding(
        ?Assessment $assessment,
        object $version,
        Applicability $applicability,
        EvaluationResult $result,
        array $resultJson,
        array $basis,
        ?string $staleReason,
    ): AssessmentFinding {
        $obligation = is_array($resultJson['obligation'] ?? null) ? $resultJson['obligation'] : [];
        $missingFields = $result->missingFields;

        $explanation = match ($applicability) {
            Applicability::APPLICABLE => $resultJson['explanation_when_applicable']
                ?? 'Bộ quy tắc cho thấy nghĩa vụ này có khả năng áp dụng với mô hình hoạt động bạn khai báo.',
            Applicability::NOT_APPLICABLE => $resultJson['explanation_when_not_applicable']
                ?? 'Các điều kiện áp dụng không thỏa với dữ kiện bạn khai báo.',
            Applicability::NEED_MORE_INFO => $missingFields === []
                ? 'Một điều kiện chưa xác định được; cần bổ sung thông tin để đánh giá chính xác.'
                : 'Thiếu dữ kiện cho các câu hỏi: '.implode(', ', $missingFields).'.',
            Applicability::NEED_EXPERT_REVIEW => $staleReason
                ?? 'Trường hợp này không thể xác định tự động; chuyển chuyên gia pháp lý kiểm tra.',
        };

        return new AssessmentFinding([
            'rule_version_id' => $version->id,
            'rule_code' => $version->rule->code,
            'title' => $obligation['name'] ?? $version->rule->name,
            'legal_group' => $version->legal_group ?? $version->rule->legal_group ?? 'GENERAL',
            'applicability' => $applicability,
            'fulfillment' => Fulfillment::NOT_STARTED,
            'severity' => $version->severity->value,
            'trace_json' => $result->trace,
            'explanation' => $explanation,
            'recommendation' => $resultJson['recommendation'] ?? $obligation['guide'] ?? null,
            'obligation_json' => $obligation ?: null,
            'legal_basis' => $basis ?: null,
            'required_info' => $missingFields === [] ? null : array_map(
                fn (string $field) => ['field' => $field, 'question' => $field],
                $missingFields,
            ),
        ]);
    }

    /**
     * Dữ kiện phái sinh từ câu trả lời + hồ sơ: thẻ hoạt động, thị trường, nền tảng.
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private function derivedFacts(Project $project, array $answers): array
    {
        $tags = array_map(
            fn (array $tag) => $tag['tag_code'],
            $this->classification->classify($answers),
        );

        return [
            'activity_tags' => $tags,
            'operates_in_vietnam' => in_array('VN', array_map('strtoupper', $project->markets ?? ['VN']), true),
            'project_platform' => $project->platform,
            'has_website' => filled($project->url),
        ];
    }
}
