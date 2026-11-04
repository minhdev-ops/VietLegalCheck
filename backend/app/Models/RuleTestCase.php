<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleTestCase extends BaseModel
{
    protected function casts(): array
    {
        return [
            'facts' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LegalRuleVersion::class, 'rule_version_id');
    }
}
