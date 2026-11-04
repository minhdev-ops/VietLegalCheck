<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalRule extends BaseModel
{
    protected function casts(): array
    {
        return [
            'activity_tags' => 'array',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LegalRuleVersion::class, 'rule_id')->orderByDesc('version');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(LegalRuleVersion::class, 'current_version_id');
    }

    public function latestVersion(): ?LegalRuleVersion
    {
        return $this->versions->first();
    }
}
