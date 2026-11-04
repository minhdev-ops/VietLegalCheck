<?php

namespace App\Domain\Enums;

enum Severity: string
{
    case CRITICAL = 'CRITICAL';
    case HIGH = 'HIGH';
    case MEDIUM = 'MEDIUM';
    case LOW = 'LOW';
    case INFO = 'INFO';

    public function rank(): int
    {
        return match ($this) {
            self::CRITICAL => 5,
            self::HIGH => 4,
            self::MEDIUM => 3,
            self::LOW => 2,
            self::INFO => 1,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CRITICAL => 'Nghiêm trọng',
            self::HIGH => 'Cao',
            self::MEDIUM => 'Trung bình',
            self::LOW => 'Thấp',
            self::INFO => 'Thông tin',
        };
    }
}
