<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends BaseModel
{
    protected function casts(): array
    {
        return [
            'as_of_date' => 'date',
            'rule_set_snapshot' => 'array',
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

    public function isReady(): bool
    {
        return $this->status === 'READY';
    }
}
