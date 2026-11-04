<?php

namespace App\Domain\Enums;

/** FR-DOC-04. */
enum DocCheckStatus: string
{
    case PRESENT = 'PRESENT';
    case MISSING = 'MISSING';
    case UNREADABLE = 'UNREADABLE';
    case NEEDS_UPDATE = 'NEEDS_UPDATE';
    case NEEDS_EXPERT = 'NEEDS_EXPERT';

    public function label(): string
    {
        return match ($this) {
            self::PRESENT => 'Đã có',
            self::MISSING => 'Thiếu',
            self::UNREADABLE => 'Không đọc được',
            self::NEEDS_UPDATE => 'Cần cập nhật',
            self::NEEDS_EXPERT => 'Cần chuyên gia xác minh',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PRESENT => 'ok',
            self::MISSING => 'danger',
            self::NEEDS_EXPERT => 'expert',
            default => 'caution',
        };
    }
}
