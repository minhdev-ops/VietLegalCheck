<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleApproval extends BaseModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LegalRuleVersion::class, 'rule_version_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
