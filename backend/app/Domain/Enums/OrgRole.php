<?php

namespace App\Domain\Enums;

enum OrgRole: string
{
    case OWNER = 'OWNER';
    case ADMIN = 'ADMIN';
    case MEMBER = 'MEMBER';
    case VIEWER = 'VIEWER';

    public function rank(): int
    {
        return match ($this) {
            self::OWNER => 4,
            self::ADMIN => 3,
            self::MEMBER => 2,
            self::VIEWER => 1,
        };
    }

    public function atLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    public function canWrite(): bool
    {
        return $this->atLeast(self::MEMBER);
    }

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Chủ sở hữu',
            self::ADMIN => 'Quản trị tổ chức',
            self::MEMBER => 'Thành viên',
            self::VIEWER => 'Chỉ xem',
        };
    }
}
