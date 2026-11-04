<?php

namespace App\Models;

class ActivityTagRule extends BaseModel
{
    protected function casts(): array
    {
        return [
            'condition_json' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
