<?php

namespace App\Domain\Enums;

enum ScanMode: string
{
    case LIGHT = 'LIGHT';
    case DEEP = 'DEEP';

    public function label(): string
    {
        return match ($this) {
            self::LIGHT => 'Quét nhẹ',
            self::DEEP => 'Quét sâu',
        };
    }
}
