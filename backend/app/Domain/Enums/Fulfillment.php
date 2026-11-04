<?php

namespace App\Domain\Enums;

enum Fulfillment: string
{
    case NOT_STARTED = 'NOT_STARTED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case SELF_DECLARED_DONE = 'SELF_DECLARED_DONE';
    case EVIDENCE_ATTACHED = 'EVIDENCE_ATTACHED';
    case EXPERT_VERIFIED = 'EXPERT_VERIFIED';

    public function label(): string
    {
        return match ($this) {
            self::NOT_STARTED => 'Chưa bắt đầu',
            self::IN_PROGRESS => 'Đang thực hiện',
            self::SELF_DECLARED_DONE => 'Tự xác nhận hoàn thành',
            self::EVIDENCE_ATTACHED => 'Đã đính kèm bằng chứng',
            self::EXPERT_VERIFIED => 'Chuyên gia đã xác minh',
        };
    }

    /** BR-22: chỉ Legal Expert được đặt EXPERT_VERIFIED. */
    public static function userSettable(): array
    {
        return [self::NOT_STARTED, self::IN_PROGRESS, self::SELF_DECLARED_DONE, self::EVIDENCE_ATTACHED];
    }
}
