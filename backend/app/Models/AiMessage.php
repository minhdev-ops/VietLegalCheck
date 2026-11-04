<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiMessage extends BaseModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'citations' => 'array',
            'categories' => 'array',
            'refused' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function retrievals(): HasMany
    {
        return $this->hasMany(AiRetrieval::class, 'message_id');
    }
}
