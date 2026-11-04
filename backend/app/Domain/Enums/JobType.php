<?php

namespace App\Domain\Enums;

enum JobType: string
{
    case SCAN = 'SCAN';
    case AI_EXPLAIN = 'AI_EXPLAIN';
    case REPORT = 'REPORT';
    case ASSESSMENT = 'ASSESSMENT';
    case MONITOR = 'MONITOR';
}
