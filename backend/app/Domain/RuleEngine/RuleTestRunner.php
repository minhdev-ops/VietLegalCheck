<?php

namespace App\Domain\RuleEngine;

use App\Domain\Enums\Applicability;
use App\Models\LegalRuleVersion;
use App\Models\RuleTestCase;
use Illuminate\Support\Collection;

/**
 * Bộ test quy tắc (FR-RULE-08): mỗi phiên bản quy tắc phải có ca đạt/không đạt
 * và chạy qua trước khi được phê duyệt (BR-20).
 */
final class RuleTestRunner
{
    public function __construct(private RuleEvaluator $evaluator) {}

    /**
     * @return array{
     *   passed: bool,
     *   total: int,
     *   results: array<int, array{name: string, expected: string, actual: string, passed: bool}>,
     * }
     */
    public function runVersion(LegalRuleVersion $version): array
    {
        $cases = $version->testCases()->get();
        $results = [];

        foreach ($cases as $case) {
            $actual = $this->runCase($version, $case);
            $passed = $actual === $case->expected_applicability;

            $case->update([
                'last_run_at' => now(),
                'last_result' => $passed ? 'PASS' : 'FAIL',
            ]);

            $results[] = [
                'name' => $case->name,
                'expected' => $case->expected_applicability,
                'actual' => $actual,
                'passed' => $passed,
            ];
        }

        return [
            'passed' => $results !== [] && collect($results)->every(fn (array $r) => $r['passed']),
            'total' => count($results),
            'results' => $results,
        ];
    }

    public function passes(LegalRuleVersion $version): bool
    {
        return $this->runVersion($version)['passed'];
    }

    private function runCase(LegalRuleVersion $version, RuleTestCase $case): string
    {
        // Dữ kiện test có thể vừa chứa câu trả lời vừa chứa dữ kiện phái sinh (activity_tags…).
        $facts = FactSheet::fromAnswers($case->facts ?? []);
        $result = $this->evaluator->evaluate($version->condition_json, $facts);

        $resultJson = $version->result_json ?? [];

        return match ($result->value) {
            Tri::TRUE => (string) ($resultJson['applicability'] ?? Applicability::APPLICABLE->value),
            Tri::FALSE => Applicability::NOT_APPLICABLE->value,
            Tri::UNKNOWN => (string) ($resultJson['unknown_applicability'] ?? Applicability::NEED_MORE_INFO->value),
        };
    }

    /** Số quy tắc đang ở trạng thái chờ phê duyệt (ADM dashboard, SRS 4.15). */
    public function pendingApprovalCount(): int
    {
        return LegalRuleVersion::where('status', 'IN_REVIEW')->count();
    }

    /** Số test ca đã thất bại lần chạy gần nhất. */
    public function failingTestCount(): int
    {
        return RuleTestCase::where('last_result', 'FAIL')->count();
    }

    /** @param Collection<int, LegalRuleVersion> $versions */
    public function coverageLegend(Collection $versions): array
    {
        return $versions->map(fn (LegalRuleVersion $version) => [
            'rule_code' => $version->rule->code,
            'version' => $version->version,
            'tests' => $version->testCases()->count(),
        ])->values()->all();
    }
}
