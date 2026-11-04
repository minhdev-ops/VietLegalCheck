<?php

namespace App\Domain\Enums;

enum RuleVersionStatus: string
{
    case DRAFT = 'DRAFT';
    case IN_REVIEW = 'IN_REVIEW';
    case APPROVED = 'APPROVED';
    case RETIRED = 'RETIRED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Bản nháp',
            self::IN_REVIEW => 'Chờ phê duyệt',
            self::APPROVED => 'Đã phát hành',
            self::RETIRED => 'Ngừng áp dụng',
        };
    }
}
