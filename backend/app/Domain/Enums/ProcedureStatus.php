<?php

namespace App\Domain\Enums;

enum ProcedureStatus: string
{
    case NOT_STARTED = 'NOT_STARTED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case SUBMITTED = 'SUBMITTED';
    case COMPLETED = 'COMPLETED';
    case ABANDONED = 'ABANDONED';

    public function label(): string
    {
        return match ($this) {
            self::NOT_STARTED => 'Chưa bắt đầu',
            self::IN_PROGRESS => 'Đang thực hiện',
            self::SUBMITTED => 'Đã nộp hồ sơ',
            self::COMPLETED => 'Đã hoàn thành',
            self::ABANDONED => 'Không áp dụng',
        };
    }
}
