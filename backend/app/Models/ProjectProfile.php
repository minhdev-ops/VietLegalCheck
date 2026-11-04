<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectProfile extends BaseModel
{
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'derived_tags' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
