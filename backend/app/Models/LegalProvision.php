<?php

namespace App\Models;

use App\Domain\Enums\LegalStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalProvision extends BaseModel
{
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'status' => LegalStatus::class,
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    public function isEffectiveOn(\DateTimeInterface $date): bool
    {
        $day = $date->format('Y-m-d');
        $from = $this->effective_from?->format('Y-m-d') ?? $this->document?->effective_from?->format('Y-m-d');
        $to = $this->effective_to?->format('Y-m-d') ?? $this->document?->effective_to?->format('Y-m-d');

        if (($from !== null && $from > $day) || ($to !== null && $to < $day)) {
            return false;
        }

        return $this->status->canBeCurrentBasis();
    }

    public function citation(): string
    {
        $document = $this->document;

        $parts = array_filter([
            $this->article,
            $this->clause,
            $this->point !== null ? 'Điểm '.$this->point : null,
        ]);

        return trim(($parts === [] ? '' : implode(', ', $parts).' ').'— '.($document?->doc_number ?? ''));
    }

    public function label(): string
    {
        return trim(implode(' ', array_filter([$this->article, $this->clause, $this->point]))) ?: ($this->heading ?? 'Điều khoản');
    }
}
