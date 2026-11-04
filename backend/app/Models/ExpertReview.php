<?php

namespace App\Models;

use App\Domain\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpertReview extends BaseModel
{
    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'requested_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ExpertReviewComment::class, 'expert_review_id')->orderBy('created_at');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
