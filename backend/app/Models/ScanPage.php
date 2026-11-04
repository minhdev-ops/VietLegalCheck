<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanPage extends BaseModel
{
    protected function casts(): array
    {
        return [
            'signals' => 'array',
            'evidence' => 'array',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }
}
