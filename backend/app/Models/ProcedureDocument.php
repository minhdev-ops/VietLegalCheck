<?php

namespace App\Models;

use App\Domain\Enums\DocCheckStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedureDocument extends BaseModel
{
    protected function casts(): array
    {
        return [
            'status' => DocCheckStatus::class,
            'checked_at' => 'datetime',
        ];
    }

    public function projectProcedure(): BelongsTo
    {
        return $this->belongsTo(ProjectProcedure::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ProcedureRequirement::class, 'procedure_requirement_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(DocumentFile::class, 'file_id');
    }
}
