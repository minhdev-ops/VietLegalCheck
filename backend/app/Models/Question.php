<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends BaseModel
{
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'show_if' => 'array',
            'activity_tags' => 'array',
        ];
    }

    public function questionSet(): BelongsTo
    {
        return $this->belongsTo(QuestionSet::class);
    }

    public function isRequired(): bool
    {
        return $this->importance === 'REQUIRED';
    }

    /**
     * Câu hỏi có hiển thị với bộ câu trả lời hiện tại không (FR-PROJ-03: điều kiện hiển thị).
     */
    public function isVisibleFor(array $answers): bool
    {
        if (! $this->show_if) {
            return true;
        }

        $field = $this->show_if['field'] ?? null;
        if ($field === null) {
            return true;
        }

        $expected = $this->show_if['value'] ?? true;
        $actual = $answers[$field] ?? null;

        if (is_array($actual)) {
            return in_array($expected, $actual, false);
        }

        if (is_array($expected)) {
            return in_array($actual, $expected, false);
        }

        return $actual == $expected;
    }
}
