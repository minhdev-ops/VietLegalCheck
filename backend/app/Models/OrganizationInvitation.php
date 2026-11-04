<?php

namespace App\Models;

use App\Domain\Enums\OrgRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationInvitation extends BaseModel
{
    protected function casts(): array
    {
        return [
            'role' => OrgRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
