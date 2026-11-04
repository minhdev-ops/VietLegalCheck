<?php

namespace App\Domain\Enums;

enum ProcedureStatus: string
{
    case NOT_STARTED = 'NOT_STARTED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case WAITING_DOCUMENTS = 'WAITING_DOCUMENTS';
    case READY_TO_SUBMIT = 'READY_TO_SUBMIT';
    case SUBMITTED_BY_USER = 'SUBMITTED_BY_USER';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::NOT_STARTED => 'Chưa bắt đầu',
            self::IN_PROGRESS => 'Đang chuẩn bị',
            self::WAITING_DOCUMENTS => 'Chờ bổ sung hồ sơ',
            self::READY_TO_SUBMIT => 'Sẵn sàng nộp',
            self::SUBMITTED_BY_USER => 'Đã tự nộp (chưa xác minh)',
            self::PROCESSING => 'Đang được xử lý',
            self::COMPLETED => 'Đã hoàn thành',
            self::REJECTED => 'Bị từ chối',
            self::CANCELLED => 'Đã hủy',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::COMPLETED => 'ok',
            self::REJECTED, self::CANCELLED => 'danger',
            self::SUBMITTED_BY_USER, self::PROCESSING => 'info',
            self::WAITING_DOCUMENTS, self::READY_TO_SUBMIT => 'caution',
            default => 'muted',
        };
    }
}
