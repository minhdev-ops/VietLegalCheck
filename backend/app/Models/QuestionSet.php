<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionSet extends BaseModel
{
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }

    public static function published(): ?self
    {
        return static::where('status', 'PUBLISHED')->orderByDesc('version')->first();
    }
}
