<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedureRequirement extends BaseModel
{
    protected function casts(): array
    {
        return [
            'required' => 'boolean',
        ];
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(AdministrativeProcedure::class, 'procedure_id');
    }
}
