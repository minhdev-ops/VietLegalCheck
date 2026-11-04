<?php

namespace App\Models;

use App\Domain\Enums\JobStatus;
use App\Domain\Enums\JobType;

class BackgroundJob extends BaseModel
{
    protected function casts(): array
    {
        return [
            'job_type' => JobType::class,
            'status' => JobStatus::class,
            'payload' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->status === JobStatus::CANCELLED;
    }
}
