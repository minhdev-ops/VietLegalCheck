<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends BaseModel
{
    protected function casts(): array
    {
        return [
            'as_of_date' => 'date',
            'rule_set_snapshot' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AssessmentFinding::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ExpertReview::class);
    }
}
