<?php

namespace App\Domain\Enums;

enum SystemRole: string
{
    case USER = 'USER';
    case LEGAL_EXPERT = 'LEGAL_EXPERT';
    case LEGAL_ADMIN = 'LEGAL_ADMIN';
    case SYS_ADMIN = 'SYS_ADMIN';

    public function isAdministrative(): bool
    {
        return $this !== self::USER;
    }

    public function label(): string
    {
        return match ($this) {
            self::USER => 'Người dùng',
            self::LEGAL_EXPERT => 'Chuyên gia pháp lý',
            self::LEGAL_ADMIN => 'Quản trị pháp lý',
            self::SYS_ADMIN => 'Quản trị hệ thống',
        };
    }
}
