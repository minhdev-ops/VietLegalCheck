<?php

namespace App\Models;

use App\Domain\Enums\ScanMode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scan extends BaseModel
{
    protected function casts(): array
    {
        return [
            'mode' => ScanMode::class,
            'site' => 'array',
            'findings' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(ScanPage::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(BackgroundJob::class, 'job_id');
    }
}
