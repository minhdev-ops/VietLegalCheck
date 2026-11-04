<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpertReviewComment extends BaseModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(ExpertReview::class, 'expert_review_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(AssessmentFinding::class, 'finding_id');
    }
}
