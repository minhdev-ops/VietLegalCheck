<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceItem extends BaseModel
{
    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(AssessmentFinding::class, 'finding_id');
    }
}
