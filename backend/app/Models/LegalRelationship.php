<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalRelationship extends BaseModel
{
    public function from(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'from_document_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'to_document_id');
    }
}
