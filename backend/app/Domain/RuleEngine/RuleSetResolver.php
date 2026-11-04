<?php

namespace App\Domain\RuleEngine;

use App\Domain\Enums\Applicability;
use App\Domain\Enums\RuleVersionStatus;
use App\Models\AssessmentFinding;
use App\Models\LegalProvision;
use App\Models\LegalRuleVersion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Chọn phiên bản quy tắc có hiệu lực tại ngày đánh giá và kiểm tra căn cứ pháp lý.
 *
 * SRS FR-LAW-06 / FR-RULE-05 / BR-06 / BR-18: mọi truy vấn pháp lý dùng `as_of_date`.
 * Văn bản hết hiệu lực không làm căn cứ hiện hành, trừ phi có phạm vi áp dụng phù hợp
 * (điều khoản chuyển tiếp được đánh dấu bằng `effective_to` riêng của điều khoản).
 */
final class RuleSetResolver
{
    /**
     * @return array{versions: Collection<int, LegalRuleVersion>, snapshot: array<int, array{rule_code: string, version: int}>}
     */
    public function resolve(Carbon $asOfDate): array
    {
        $day = $asOfDate->format('Y-m-d 00:00:00');

        $versions = LegalRuleVersion::query()
            ->whereIn('status', [RuleVersionStatus::APPROVED])
            ->where('effective_from', '<=', $day)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $day))
            ->with(['rule', 'provisions.document'])
            ->get()
            ->filter(fn (LegalRuleVersion $version) => $version->rule !== null)
            ->sortBy(fn (LegalRuleVersion $version) => $version->legal_group);

        $snapshot = $versions
            ->map(fn (LegalRuleVersion $version) => [
                'rule_code' => $version->rule->code,
                'version' => $version->version,
            ])
            ->values()
            ->all();

        return ['versions' => $versions->values(), 'snapshot' => $snapshot];
    }

    /**
     * Căn cứ của phiên bản quy tắc tại ngày đánh giá.
     *
     * @return array{
     *   provisions: array<int, array{provision_id: string, citation: string, document_number: string, document_title: string, effective_from: ?string, official_url: ?string, content: ?string}>,
     *   stale: bool,
     *   reason: ?string
     * }
     */
    public function basisFor(LegalRuleVersion $version, Carbon $asOfDate): array
    {
        $provisions = [];
        $stale = false;
        $reason = null;

        foreach ($version->provisions as $provision) {
            $effective = $provision->isEffectiveOn($asOfDate);
            if (! $effective) {
                $stale = true;
                $reason = 'Một điều khoản căn cứ không có hiệu lực tại ngày đánh giá; cần chuyên gia xác nhận phạm vi áp dụng.';

                continue;
            }

            $provisions[] = $this->serializeProvision($provision);
        }

        return ['provisions' => $provisions, 'stale' => $stale, 'reason' => $reason];
    }

    /**
     * @param  array<string, mixed>  $provision
     */
    private function serializeProvision(LegalProvision $provision): array
    {
        $document = $provision->document;

        return [
            'provision_id' => $provision->id,
            'citation' => $provision->citation(),
            'document_number' => $document?->doc_number,
            'document_title' => $document?->title,
            'effective_from' => $provision->effective_from?->toDateString() ?? $document?->effective_from?->toDateString(),
            'official_url' => $document?->official_url,
            'content' => $provision->content,
        ];
    }

    /** Điều kiện "cần chuyên gia" khi căn cứ không xác định (BR-08). */
    public static function applicabilityForStaleBasis(Applicability $fallback): Applicability
    {
        return $fallback === Applicability::NOT_APPLICABLE ? Applicability::NEED_EXPERT_REVIEW : $fallback;
    }

    /** Map giá trị chuỗi từ test case / kết quả quy tắc sang enum. */
    public static function applicabilityFromString(string $value): Applicability
    {
        return Applicability::tryFrom($value) ?? Applicability::NEED_MORE_INFO;
    }
}
