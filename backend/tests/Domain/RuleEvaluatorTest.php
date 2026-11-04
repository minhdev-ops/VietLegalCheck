<?php

namespace Tests\Domain;

use App\Domain\RuleEngine\FactSheet;
use App\Domain\RuleEngine\RuleEvaluator;
use App\Domain\RuleEngine\Tri;
use Tests\TestCase;

/**
 * Bộ máy quy tắc 3 giá trị (FR-RULE-02/03/07, BR-07).
 */
class RuleEvaluatorTest extends TestCase
{
    private RuleEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new RuleEvaluator();
    }

    public function test_equals_true(): void
    {
        $result = $this->evaluator->evaluate(
            ['field' => 'sells_online', 'operator' => 'EQUALS', 'value' => true],
            FactSheet::fromAnswers(['sells_online' => true]),
        );

        $this->assertSame(Tri::TRUE, $result->value);
        $this->assertSame([], $result->missingFields);
    }

    public function test_missing_field_is_unknown_and_collected(): void
    {
        $result = $this->evaluator->evaluate(
            ['field' => 'sells_online', 'operator' => 'EQUALS', 'value' => true],
            FactSheet::fromAnswers([]),
        );

        $this->assertSame(Tri::UNKNOWN, $result->value);
        $this->assertSame(['sells_online'], $result->missingFields);
    }

    public function test_and_group_weak_unknown_when_false_present(): void
    {
        $result = $this->evaluator->evaluate(
            ['operator' => 'AND', 'operands' => [
                ['field' => 'a', 'operator' => 'EQUALS', 'value' => true],
                ['field' => 'b', 'operator' => 'EQUALS', 'value' => true],
            ]],
            FactSheet::fromAnswers(['a' => true, 'b' => false]),
        );

        $this->assertSame(Tri::FALSE, $result->value);
    }

    public function test_or_group_true_wins_over_unknown(): void
    {
        $result = $this->evaluator->evaluate(
            ['operator' => 'OR', 'operands' => [
                ['field' => 'a', 'operator' => 'EQUALS', 'value' => true],
                ['field' => 'missing', 'operator' => 'EQUALS', 'value' => true],
            ]],
            FactSheet::fromAnswers(['a' => true]),
        );

        $this->assertSame(Tri::TRUE, $result->value);
    }

    public function test_not_group(): void
    {
        $result = $this->evaluator->evaluate(
            ['operator' => 'NOT', 'operands' => [['field' => 'a', 'operator' => 'EQUALS', 'value' => true]]],
            FactSheet::fromAnswers(['a' => false]),
        );

        $this->assertSame(Tri::TRUE, $result->value);
    }

    public function test_in_operator_on_array_answer(): void
    {
        $result = $this->evaluator->evaluate(
            ['field' => 'markets', 'operator' => 'IN', 'value' => ['EU', 'US']],
            FactSheet::fromAnswers(['markets' => ['VN', 'EU']]),
        );

        $this->assertSame(Tri::TRUE, $result->value);
    }

    public function test_vietnamese_contains_is_accent_insensitive(): void
    {
        $result = $this->evaluator->evaluate(
            ['field' => 'bio', 'operator' => 'CONTAINS', 'value' => 'thanh toan'],
            FactSheet::fromAnswers(['bio' => 'Dịch vụ thanh toán trung gian']),
        );

        $this->assertSame(Tri::TRUE, $result->value);
    }

    public function test_group_shortcut_syntax(): void
    {
        $result = $this->evaluator->evaluate(
            ['all' => [
                ['field' => 'a', 'operator' => 'EQUALS', 'value' => true],
            ]],
            FactSheet::fromAnswers(['a' => true]),
        );

        $this->assertSame(Tri::TRUE, $result->value);
    }

    public function test_trace_records_conditions_and_groups(): void
    {
        $result = $this->evaluator->evaluate(
            ['operator' => 'OR', 'operands' => [
                ['field' => 'a', 'operator' => 'EQUALS', 'value' => true],
                ['field' => 'b', 'operator' => 'EQUALS', 'value' => true],
            ]],
            FactSheet::fromAnswers(['a' => false, 'b' => true]),
        );

        $this->assertCount(1, $result->trace);
        $this->assertSame('OR', $result->trace[0]['operator']);
        $this->assertCount(2, $result->trace[0]['children']);
    }

    public function test_greater_than_compares_numbers(): void
    {
        $result = $this->evaluator->evaluate(
            ['field' => 'users', 'operator' => 'GREATER_THAN', 'value' => 100],
            FactSheet::fromAnswers(['users' => 500]),
        );

        $this->assertSame(Tri::TRUE, $result->value);
    }

    public function test_exists_operator(): void
    {
        $result = $this->evaluator->evaluate(
            ['field' => 'markets', 'operator' => 'EXISTS'],
            FactSheet::fromAnswers(['markets' => ['VN']]),
        );

        $this->assertSame(Tri::TRUE, $result->value);

        $missing = $this->evaluator->evaluate(
            ['field' => 'markets', 'operator' => 'EXISTS'],
            FactSheet::fromAnswers([]),
        );

        $this->assertSame(Tri::FALSE, $missing->value);
    }

    public function test_invalid_operator_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->evaluator->evaluate(
            ['field' => 'a', 'operator' => 'BANANA', 'value' => 1],
            FactSheet::fromAnswers(['a' => 1]),
        );
    }
}
