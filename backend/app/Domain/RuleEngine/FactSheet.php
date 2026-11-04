<?php

namespace App\Domain\RuleEngine;

/**
 * Dữ kiện đưa vào bộ máy quy tắc.
 *
 * Một field không có trong dữ kiện là "chưa biết" (UNKNOWN), khác với giá trị null/false
 * người dùng khai báo rõ ràng.
 */
final class FactSheet
{
    /** @param array<string, mixed> $facts */
    public function __construct(private array $facts) {}

    public static function fromAnswers(array $answers, array $derived = []): self
    {
        return new self(array_merge($answers, $derived));
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->facts) && $this->facts[$field] !== null;
    }

    public function get(string $field): mixed
    {
        return $this->facts[$field] ?? null;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->facts;
    }

    public function with(string $field, mixed $value): self
    {
        $next = $this->facts;
        $next[$field] = $value;

        return new self($next);
    }
}
