<?php

namespace App\Services;

use App\Models\LegalDocument;
use App\Models\LegalProvision;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Nghiệp vụ văn bản pháp luật (SRS 4.13): hiệu lực tại ngày, đánh dấu cần kiểm chứng lại
 * khi `last_verified_at` quá stale (BR-08/09). Lớp này chứa Macen truy vấn dùng chung.
 */
final class LegalService
{
    private const STALE_DAYS = 365;

    /** Văn bản có hiệu lực tại ngày (BR-18): hiệu lực chính + hiệu lực điều khoản riêng. */
    public function isEffectiveOn(LegalDocument $document, Carbon $asOfDate): bool
    {
        if ($document->effective_from && $document->effective_from->isAfter($asOfDate)) {
            return false;
        }

        return $document->effective_to === null || ! $document->effective_to->isBefore($asOfDate);
    }

    public function isVerifiedStale(LegalDocument $document, ?int $staleDays = null): bool
    {
        if (! $document->last_verified_at) {
            return true;
        }

        return $document->last_verified_at->diffInDays(now()) > ($staleDays ?? self::STALE_DAYS);
    }

    /** Mỗi lần context đổi (cập nhật văn bản) thì log (FR-LAW-05). */
    public function logUpdate(LegalDocument $document, string $changeSummary, string $actorId): void
    {
        $document->updateLogs()->create([
            'change_summary' => $changeSummary,
            'actor_id' => $actorId,
        ]);
    }

    /** Scope Eloquent dùng chung cho controller. */
    public function scopeEffectiveAt(Builder $query, Carbon $asOfDate): Builder
    {
        return $query->where(function (Builder $builder) use ($asOfDate) {
            $builder
                ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $asOfDate->toDateString()))
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $asOfDate->toDateString()));
        });
    }

    public function staleDays(): int
    {
        return self::STALE_DAYS;
    }
}
