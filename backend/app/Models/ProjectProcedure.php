<?php

namespace App\Models;

use App\Domain\Enums\ProcedureStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectProcedure extends BaseModel
{
    protected function casts(): array
    {
        return [
            'status' => ProcedureStatus::class,
            'snapshot' => 'array',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(AdministrativeProcedure::class, 'procedure_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProcedureDocument::class);
    }
}
