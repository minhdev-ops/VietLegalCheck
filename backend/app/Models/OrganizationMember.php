<?php

namespace App\Models;

use App\Domain\Enums\OrgRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMember extends BaseModel
{
    protected function casts(): array
    {
        return [
            'role' => OrgRole::class,
            'joined_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
