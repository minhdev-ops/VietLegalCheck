<?php

namespace App\Models;

use App\Domain\Enums\LegalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegalDocument extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'status' => LegalStatus::class,
            'last_verified_at' => 'datetime',
        ];
    }

    public function provisions(): HasMany
    {
        return $this->hasMany(LegalProvision::class)->orderBy('position');
    }

    public function outgoingRelationships(): HasMany
    {
        return $this->hasMany(LegalRelationship::class, 'from_document_id');
    }

    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(LegalRelationship::class, 'to_document_id');
    }

    public function isVerifiedStale(int $days = 90): bool
    {
        return $this->last_verified_at === null || $this->last_verified_at->lt(now()->subDays($days));
    }

    /** Có hiệu lực tại ngày cho trước (không dựa vào cột status đơn thuần — BR-18). */
    public function isEffectiveOn(\DateTimeInterface $date): bool
    {
        $day = $date->format('Y-m-d');
        $from = $this->effective_from?->format('Y-m-d');
        $to = $this->effective_to?->format('Y-m-d');

        return ($from === null || $from <= $day) && ($to === null || $to >= $day);
    }

    /**
     * Lọc văn bản có hiệu lực tại ngày pháp lý cho trước (BR-18).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeEffectiveAt(Builder $query, \DateTimeInterface|string $date): Builder
    {
        $day = \Carbon\Carbon::parse($date)->toDateString();

        return $query->where(fn (Builder $builder) => $builder
            ->whereNull('effective_from')
            ->orWhere('effective_from', '<=', $day))
            ->where(fn (Builder $builder) => $builder
                ->whereNull('effective_to')
                ->orWhere('effective_to', '>=', $day));
    }
}
