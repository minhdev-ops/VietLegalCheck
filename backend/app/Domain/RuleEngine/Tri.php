<?php

namespace App\Domain\RuleEngine;

/**
 * Logic ba giá trị (SRS FR-RULE-03): TRUE, FALSE, UNKNOWN.
 *
 * UNKNOWN không bao giờ bị ép về FALSE: nếu kết quả phụ thuộc điều kiện UNKNOWN thì
 * bộ máy trả NEED_MORE_INFO thay vì đoán (BR-07).
 */
enum Tri: string
{
    case TRUE = 'TRUE';
    case FALSE = 'FALSE';
    case UNKNOWN = 'UNKNOWN';

    public static function fromBool(bool $value): self
    {
        return $value ? self::TRUE : self::FALSE;
    }

    public function not(): self
    {
        return match ($this) {
            self::TRUE => self::FALSE,
            self::FALSE => self::TRUE,
            self::UNKNOWN => self::UNKNOWN,
        };
    }

    /** @param  Tri[]  $values */
    public static function all(array $values): self
    {
        if (in_array(self::FALSE, $values, true)) {
            return self::FALSE;
        }

        return in_array(self::UNKNOWN, $values, true) ? self::UNKNOWN : self::TRUE;
    }

    /** @param  Tri[]  $values */
    public static function any(array $values): self
    {
        if (in_array(self::TRUE, $values, true)) {
            return self::TRUE;
        }

        return in_array(self::UNKNOWN, $values, true) ? self::UNKNOWN : self::FALSE;
    }

    public function isDeterminate(): bool
    {
        return $this !== self::UNKNOWN;
    }
}
