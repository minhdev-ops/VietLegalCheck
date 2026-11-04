<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRetrieval extends BaseModel
{
    protected function casts(): array
    {
        return [
            'score' => 'float',
        ];
    }

    public function provision(): BelongsTo
    {
        return $this->belongsTo(LegalProvision::class);
    }
}
