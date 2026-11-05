<?php

namespace App\Models;

use App\Domain\Enums\ScanMode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scan extends BaseModel
{
    /**
     * Phiên bản bộ tiêu chí đánh giá (FR-SCAN-05).
     * Tăng khi thay đổi bộ tiêu chí kiểm tra website; scans cũ giữ phiên bản của chúng.
     */
    public const CRITERIA_VERSION = 1;

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
