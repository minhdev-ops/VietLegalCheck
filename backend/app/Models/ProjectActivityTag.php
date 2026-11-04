<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectActivityTag extends BaseModel
{
    protected function casts(): array
    {
        return [
            'question_codes' => 'array',
            'confidence' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(ActivityTag::class, 'tag_code', 'code');
    }
}
