<?php

namespace App\Domain\Enums;

/**
 * Hai trường trạng thái tách biệt của một finding (SRS 4.7, FR-OBL-02).
 */
enum Applicability: string
{
    case APPLICABLE = 'APPLICABLE';
    case NOT_APPLICABLE = 'NOT_APPLICABLE';
    case NEED_MORE_INFO = 'NEED_MORE_INFO';
    case NEED_EXPERT_REVIEW = 'NEED_EXPERT_REVIEW';

    public function label(): string
    {
        return match ($this) {
            self::APPLICABLE => 'Có khả năng áp dụng',
            self::NOT_APPLICABLE => 'Không thuộc phạm vi áp dụng',
            self::NEED_MORE_INFO => 'Cần bổ sung thông tin',
            self::NEED_EXPERT_REVIEW => 'Cần chuyên gia xác nhận',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::APPLICABLE => 'caution',
            self::NOT_APPLICABLE => 'neutral',
            self::NEED_MORE_INFO => 'info',
            self::NEED_EXPERT_REVIEW => 'expert',
        };
    }
}
