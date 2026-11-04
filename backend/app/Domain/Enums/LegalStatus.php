<?php

namespace App\Domain\Enums;

/** FR-LAW-03. */
enum LegalStatus: string
{
    case DRAFT = 'DRAFT';
    case NOT_YET_EFFECTIVE = 'NOT_YET_EFFECTIVE';
    case EFFECTIVE = 'EFFECTIVE';
    case PARTIALLY_EFFECTIVE = 'PARTIALLY_EFFECTIVE';
    case AMENDED = 'AMENDED';
    case EXPIRED = 'EXPIRED';
    case REPLACED = 'REPLACED';
    case SUSPENDED = 'SUSPENDED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Dự thảo',
            self::NOT_YET_EFFECTIVE => 'Chưa có hiệu lực',
            self::EFFECTIVE => 'Đang có hiệu lực',
            self::PARTIALLY_EFFECTIVE => 'Có hiệu lực một phần',
            self::AMENDED => 'Đã được sửa đổi',
            self::EXPIRED => 'Hết hiệu lực',
            self::REPLACED => 'Bị thay thế',
            self::SUSPENDED => 'Tạm ngưng hiệu lực',
        };
    }

    /** Trạng thái có thể làm căn cứ hiện hành tại một thời điểm (BR-06). */
    public function canBeCurrentBasis(): bool
    {
        return in_array($this, [self::EFFECTIVE, self::PARTIALLY_EFFECTIVE], true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::EFFECTIVE => 'ok',
            self::PARTIALLY_EFFECTIVE => 'caution',
            self::NOT_YET_EFFECTIVE => 'info',
            self::DRAFT => 'muted',
            default => 'danger',
        };
    }
}
