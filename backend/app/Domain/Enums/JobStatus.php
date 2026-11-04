<?php

namespace App\Domain\Enums;

enum JobStatus: string
{
    case QUEUED = 'QUEUED';
    case RUNNING = 'RUNNING';
    case SUCCEEDED = 'SUCCEEDED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';

    public function isTerminal(): bool
    {
        return in_array($this, [self::SUCCEEDED, self::FAILED, self::CANCELLED], true);
    }
}
