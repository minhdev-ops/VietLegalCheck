<?php

namespace App\Domain\RuleEngine;

use App\Models\ActivityTagRule;
use App\Models\Project;

/**
 * Gán thẻ hoạt động (SRS 3.3, FR-CLASS-01..04) bằng quy tắc xác định, không phải AI.
 *
 * Mỗi thẻ đi kèm lý do (câu hỏi đã trả lời + nhóm điều kiện) và độ tin cậy:
 * - RULE-engine deterministic → confidence 1.00
 * - tag do người dùng tự thêm → confidence do người dùng báo
 */
final class ClassificationEngine
{
    public function __construct(private RuleEvaluator $evaluator) {}

    /**
     * @param  array<string, mixed>  $answers
     * @return array<int, array{tag_code: string, reason: string, confidence: float, question_codes: array<int, string>}>
     */
    public function classify(array $answers): array
    {
        $rules = ActivityTagRule::where('is_active', true)->orderBy('position')->get();
        $facts = FactSheet::fromAnswers($answers);
        $assigned = [];

        foreach ($rules as $rule) {
            $result = $this->evaluator->evaluate($rule->condition_json, $facts);

            if (! $result->isTrue()) {
                continue;
            }

            $assigned[] = [
                'tag_code' => $rule->tag_code,
                'reason' => $this->reason($rule->reason_template, $result, $answers),
                'confidence' => 1.0,
                'question_codes' => $this->questionCodes($result->trace),
            ];
        }

        return $assigned;
    }

    /**
     * @param  array<int, array<string, mixed>>  $trace
     */
    private function questionCodes(array $trace): array
    {
        $codes = [];

        foreach ($trace as $node) {
            if (isset($node['field']) && is_string($node['field'])) {
                $codes[] = $node['field'];
            }
            if (isset($node['children']) && is_array($node['children'])) {
                $codes = array_merge($codes, $this->questionCodes($node['children']));
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function reason(string $template, EvaluationResult $result, array $answers): string
    {
        $reason = str_replace('{questions}', implode(', ', $this->questionCodes($result->trace)), $template);

        $confirmations = array_filter(
            array_map(function (array $node) use ($answers): ?string {
                $field = $node['field'] ?? null;
                if (! is_string($field)) {
                    return null;
                }
                $label = $node['label'] ?? $field;
                $answer = $answers[$field] ?? null;
                if ($answer === null) {
                    return null;
                }
                $shown = is_bool($answer) ? ($answer ? 'có' : 'không') : (is_array($answer) ? implode(', ', $answer) : (string) $answer);

                return mb_strtolower((string) $label).' = '.$shown;
            }, array_filter($result->trace, fn ($node) => ($node['kind'] ?? null) === 'condition')),
        );

        return $confirmations === []
            ? $reason
            : $reason.' ('.implode('; ', array_slice($confirmations, 0, 3)).')';
    }

    /** Người dùng tự bổ sung/xóa thẻ (FR-CLASS-03 vẫn ghi lý do). */
    public static function userOverride(Project $project, string $tagCode, bool $add, ?string $reason = null): void
    {
        if ($add) {
            $project->activityTags()->updateOrCreate(
                ['tag_code' => $tagCode],
                ['assigned_by' => 'USER', 'reason' => $reason ?? 'Người dùng tự thêm', 'confidence' => 0.5],
            );
        } else {
            $project->activityTags()->where('tag_code', $tagCode)->where('assigned_by', 'USER')->delete();
        }
    }
}
