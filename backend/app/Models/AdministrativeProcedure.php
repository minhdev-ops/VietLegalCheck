<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdministrativeProcedure extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'submission_methods' => 'array',
            'legal_basis' => 'array',
            'activity_tags' => 'array',
            'obligation_codes' => 'array',
            'last_verified_at' => 'datetime',
        ];
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ProcedureRequirement::class, 'procedure_id')->orderBy('position');
    }

    public function isVerifiedStale(int $days = 90): bool
    {
        return $this->last_verified_at === null || $this->last_verified_at->lt(now()->subDays($days));
    }
}
