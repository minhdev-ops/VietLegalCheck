<?php

namespace App\Domain\Enums;

enum ReviewStatus: string
{
    case QUEUED = 'QUEUED';
    case IN_REVIEW = 'IN_REVIEW';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'Đang chờ',
            self::IN_REVIEW => 'Đang xem xét',
            self::COMPLETED => 'Đã kết luận',
            self::CANCELLED => 'Đã hủy',
        };
    }
}
