<?php

namespace App\Domain\RuleEngine;

use InvalidArgumentException;

/**
 * Bộ đánh giá điều kiện quy tắc với logic ba giá trị (FR-RULE-02, FR-RULE-03, FR-RULE-07).
 *
 * Cấu trúc JSON hỗ trợ hai dạng nhóm, cả hai đều hợp lệ:
 *   { "all": [ ... ] }                     / { "operator": "AND", "operands": [ ... ] }
 *   { "any": [ ... ] }                     / { "operator": "OR",  "operands": [ ... ] }
 *   { "not": { ... } }                     / { "operator": "NOT", "operands": [ ... ] }
 *   { "field": "...", "operator": "EQUALS", "value": ..., "label": "..." }
 *
 * Tài liệu quy tắc không bao giờ được "đoán": điều kiện thiếu dữ kiện trả UNKNOWN và field đó
 * được liệt kê trong missingFields để hệ thống tạo finding NEED_MORE_INFO (BR-07).
 */
final class RuleEvaluator
{
    private const GROUP_KEYS = ['all', 'any', 'not'];

    public function evaluate(array $condition, FactSheet $facts): EvaluationResult
    {
        $trace = [];
        $missing = [];
        $value = $this->evaluateNode($condition, $facts, '$', $trace, $missing);

        return new EvaluationResult($value, $trace, array_values(array_unique($missing)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $trace
     * @param  array<int, string>  $missing
     */
    private function evaluateNode(array $node, FactSheet $facts, string $path, array &$trace, array &$missing): Tri
    {
        if (isset($node['operator']) && isset($node['operands'])) {
            return $this->evaluateGroup(
                strtoupper((string) $node['operator']),
                array_values((array) $node['operands']),
                $facts,
                $path,
                $trace,
                $missing,
            );
        }

        foreach (self::GROUP_KEYS as $key) {
            if (array_key_exists($key, $node)) {
                return match ($key) {
                    'all' => $this->evaluateGroup('AND', array_values((array) $node['all']), $facts, $path, $trace, $missing),
                    'any' => $this->evaluateGroup('OR', array_values((array) $node['any']), $facts, $path, $trace, $missing),
                    'not' => $this->evaluateGroup('NOT', [(array) $node['not']], $facts, $path, $trace, $missing),
                };
            }
        }

        return $this->evaluateLeaf($node, $facts, $path, $trace, $missing);
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @param  array<int, array<string, mixed>>  $trace
     * @param  array<int, string>  $missing
     */
    private function evaluateGroup(string $operator, array $children, FactSheet $facts, string $path, array &$trace, array &$missing): Tri
    {
        $results = [];
        $childTraces = [];

        foreach ($children as $index => $child) {
            $childTrace = [];
            $childMissing = [];
            $result = $this->evaluateNode(
                (array) $child,
                $facts,
                $path.'.'.$operator.'['.$index.']',
                $childTrace,
                $childMissing,
            );
            $results[] = $result;
            $childTraces = array_merge($childTraces, $childTrace);
            $missing = array_merge($missing, $childMissing);
        }

        $value = match ($operator) {
            'AND' => Tri::all($results),
            'OR' => Tri::any($results),
            'NOT' => $results ? $results[0]->not() : Tri::UNKNOWN,
            default => throw new InvalidArgumentException("Toán tử nhóm không hỗ trợ: {$operator}"),
        };

        $trace[] = [
            'node' => $path,
            'kind' => 'group',
            'operator' => $operator,
            'result' => $value->value,
            'label' => $this->groupLabel($operator),
            'children' => $childTraces,
        ];

        return $value;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, array<string, mixed>>  $trace
     * @param  array<int, string>  $missing
     */
    private function evaluateLeaf(array $node, FactSheet $facts, string $path, array &$trace, array &$missing): Tri
    {
        $field = (string) ($node['field'] ?? '');
        $operator = strtoupper((string) ($node['operator'] ?? ''));

        if ($field === '' || $operator === '') {
            throw new InvalidArgumentException('Điều kiện thiếu "field" hoặc "operator".');
        }

        $expected = $node['value'] ?? null;
        $present = $facts->has($field);
        $actual = $facts->get($field);

        $value = match ($operator) {
            'EXISTS' => Tri::fromBool($present && ! $this->isEmpty($actual)),
            'NOT_EXISTS' => Tri::fromBool(! $present || $this->isEmpty($actual)),
            'EQUALS' => $present ? Tri::fromBool($this->equals($actual, $expected)) : Tri::UNKNOWN,
            'NOT_EQUALS' => $present ? Tri::fromBool(! $this->equals($actual, $expected)) : Tri::UNKNOWN,
            'CONTAINS' => $present ? $this->contains($actual, $expected) : Tri::UNKNOWN,
            'NOT_CONTAINS' => $present ? $this->contains($actual, $expected)->not() : Tri::UNKNOWN,
            'IN' => $present ? $this->inList($actual, (array) $expected) : Tri::UNKNOWN,
            'NOT_IN' => $present ? $this->inList($actual, (array) $expected)->not() : Tri::UNKNOWN,
            'GREATER_THAN' => $present ? $this->compare($actual, $expected, '>') : Tri::UNKNOWN,
            'LESS_THAN' => $present ? $this->compare($actual, $expected, '<') : Tri::UNKNOWN,
            'GREATER_THAN_OR_EQUAL' => $present ? $this->compare($actual, $expected, '>=') : Tri::UNKNOWN,
            'LESS_THAN_OR_EQUAL' => $present ? $this->compare($actual, $expected, '<=') : Tri::UNKNOWN,
            default => throw new InvalidArgumentException("Toán tử không hỗ trợ: {$operator}"),
        };

        if ($value === Tri::UNKNOWN && ! in_array($operator, ['EXISTS', 'NOT_EXISTS'], true)) {
            $missing[] = $field;
        }

        $trace[] = [
            'node' => $path,
            'kind' => 'condition',
            'field' => $field,
            'operator' => $operator,
            'expected' => $expected,
            'actual' => $actual,
            'result' => $value->value,
            'label' => $node['label'] ?? null,
            'missing_field' => $value === Tri::UNKNOWN ? $field : null,
        ];

        return $value;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value) && $value === []);
    }

    private function normalize(mixed $value): mixed
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            $lower = mb_strtolower($trimmed);

            return match ($lower) {
                'true' => true,
                'false' => false,
                default => is_numeric($trimmed) ? $trimmed + 0 : $lower,
            };
        }

        return is_bool($value) || is_int($value) || is_float($value) ? $value : $value;
    }

    private function equals(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual) && ! is_array($expected)) {
            return in_array($this->normalize($expected), array_map($this->normalize(...), $actual), true);
        }

        return $this->normalize($actual) === $this->normalize($expected);
    }

    private function contains(mixed $actual, mixed $expected): Tri
    {
        if (is_array($actual)) {
            return Tri::fromBool(in_array($this->normalize($expected), array_map($this->normalize(...), $actual), true));
        }

        if (is_string($actual)) {
            return Tri::fromBool(
                mb_stripos($actual, (string) $expected) !== false
                || mb_stripos($this->unaccent($actual), $this->unaccent((string) $expected)) !== false
            );
        }

        return Tri::UNKNOWN;
    }

    /** @param array<int, mixed> $expected */
    private function inList(mixed $actual, array $expected): Tri
    {
        $needles = array_map($this->normalize(...), $expected);

        if (is_array($actual)) {
            $haystack = array_map($this->normalize(...), $actual);

            return Tri::fromBool(array_intersect($haystack, $needles) !== []);
        }

        return Tri::fromBool(in_array($this->normalize($actual), $needles, true));
    }

    private function compare(mixed $actual, mixed $expected, string $op): Tri
    {
        $left = $this->comparable($actual);
        $right = $this->comparable($expected);

        if ($left === null || $right === null) {
            return Tri::UNKNOWN;
        }

        if (is_string($left) && is_string($right) && ! is_numeric($left)) {
            return Tri::fromBool(match ($op) {
                '>' => strtotime($left) !== false && strtotime($right) !== false && strtotime($left) > strtotime($right),
                '<' => strtotime($left) !== false && strtotime($right) !== false && strtotime($left) < strtotime($right),
                '>=' => strtotime($left) !== false && strtotime($right) !== false && strtotime($left) >= strtotime($right),
                '<=' => strtotime($left) !== false && strtotime($right) !== false && strtotime($left) <= strtotime($right),
            });
        }

        $a = is_numeric($left) ? (float) $left : $left;
        $b = is_numeric($right) ? (float) $right : $right;

        return Tri::fromBool(match ($op) {
            '>' => $a > $b,
            '<' => $a < $b,
            '>=' => $a >= $b,
            '<=' => $a <= $b,
        });
    }

    private function comparable(mixed $value): string|float|int|null
    {
        if (is_bool($value) || $value === null || is_array($value)) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $trimmed = trim((string) $value);

        return is_numeric($trimmed) ? (float) $trimmed : $trimmed;
    }

    private function unaccent(string $value): string
    {
        $map = [
            'a' => ['à', 'á', 'ạ', 'ả', 'ã', 'â', 'ầ', 'ấ', 'ậ', 'ẩ', 'ẫ', 'ă', 'ằ', 'ắ', 'ặ', 'ẳ', 'ẵ'],
            'e' => ['è', 'é', 'ẹ', 'ẻ', 'ẽ', 'ê', 'ề', 'ế', 'ệ', 'ể', 'ễ'],
            'i' => ['ì', 'í', 'ị', 'ỉ', 'ĩ'],
            'o' => ['ò', 'ó', 'ọ', 'ỏ', 'õ', 'ô', 'ồ', 'ố', 'ộ', 'ổ', 'ỗ', 'ơ', 'ờ', 'ớ', 'ợ', 'ở', 'ỡ'],
            'u' => ['ù', 'ú', 'ụ', 'ủ', 'ũ', 'ư', 'ừ', 'ứ', 'ự', 'ử', 'ữ'],
            'y' => ['ỳ', 'ý', 'ỵ', 'ỷ', 'ỹ'],
            'd' => ['đ'],
        ];

        $lower = mb_strtolower($value);
        foreach ($map as $plain => $chars) {
            $lower = str_replace($chars, $plain, $lower);
        }

        return $lower;
    }

    private function groupLabel(string $operator): string
    {
        return match ($operator) {
            'AND' => 'Tất cả điều kiện sau',
            'OR' => 'Một trong các điều kiện sau',
            'NOT' => 'Phủ định điều kiện',
        };
    }
}
