<?php

namespace App\Domain\RuleEngine;

/**
 * Kết quả đánh giá một điều kiện: giá trị ba trạng thái + vết giải thích (FR-RULE-07).
 */
final class EvaluationResult
{
    /**
     * @param  array<int, array<string, mixed>>  $trace
     * @param  array<int, string>  $missingFields
     */
    public function __construct(
        public readonly Tri $value,
        public readonly array $trace,
        public readonly array $missingFields,
    ) {}

    public function isTrue(): bool
    {
        return $this->value === Tri::TRUE;
    }
}
