<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\RuleVersionStatus;
use App\Domain\RuleEngine\RuleTestRunner;
use App\Models\AdministrativeProcedure;
use App\Models\AuditLog;
use App\Models\ExpertReview;
use App\Models\LegalDocument;
use App\Models\LegalRuleVersion;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Khu vực quản trị (SRS 4.15): dashboard, chuyên gia, nhật ký, cấu hình, thủ tục.
 */
class AdminController extends ApiController
{
    public function __construct(private AuditLogger $audit) {}

    public function dashboard(): JsonResponse
    {
        return $this->ok([
            'pending_rule_approvals' => LegalRuleVersion::where('status', RuleVersionStatus::IN_REVIEW)->count(),
            'stale_documents' => LegalDocument::where(fn ($query) => $query->whereNull('last_verified_at')->orWhere('last_verified_at', '<', now()->subDays(365)))->count(),
            'open_expert_reviews' => ExpertReview::whereIn('status', ['REQUESTED', 'IN_PROGRESS'])->count(),
            'documents' => LegalDocument::count(),
            'users' => User::count(),
        ]);
    }

    public function expertReviews(Request $request): JsonResponse
    {
        $reviews = ExpertReview::query()
            ->with(['project', 'reviewer'])
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20);

        return $this->collection($reviews->items(), [
            'meta' => ['page' => $reviews->currentPage(), 'last_page' => $reviews->lastPage(), 'total' => $reviews->total()],
        ]);
    }

    public function storeExpertReview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'uuid', 'exists:projects,id'],
            'question' => ['required', 'string', 'max:2000'],
            'priority' => ['nullable', 'string', 'in:LOW,MEDIUM,HIGH'],
        ]);

        $review = ExpertReview::create([
            'project_id' => $data['project_id'],
            'reviewer_id' => $request->user()->id,
            'question' => $data['question'],
            'status' => 'REQUESTED',
            'priority' => $data['priority'] ?? 'MEDIUM',
        ]);

        $this->audit->log('EXPERT_REVIEW_REQUESTED', $review);

        return $this->ok(['review' => $review], 201);
    }

    public function updateExpertReview(Request $request, ExpertReview $review): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:REQUESTED,IN_PROGRESS,ANSWERED,CLOSED'],
            'answer' => ['nullable', 'string', 'max:5000'],
        ]);

        $review->update($data);
        $this->audit->log('EXPERT_REVIEW_UPDATED', $review);

        return $this->ok(['review' => $review->fresh()]);
    }

    public function auditLog(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->with('actor')
            ->when($request->string('action')->toString(), fn ($query, $action) => $query->where('action', $action))
            ->latest()
            ->paginate(50);

        return $this->collection($logs->items(), [
            'meta' => ['page' => $logs->currentPage(), 'last_page' => $logs->lastPage(), 'total' => $logs->total()],
        ]);
    }

    public function settings(): JsonResponse
    {
        return $this->ok(['settings' => SystemSetting::all(['key', 'value'])->keyBy('key')]);
    }

    public function updateSetting(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'value' => ['required'],
        ]);

        $setting = SystemSetting::put($data['key'], $data['value']);
        $this->audit->log('SETTING_UPDATED', $setting, ['key' => $data['key']]);

        return $this->ok(['setting' => $setting]);
    }

    public function procedures(Request $request): JsonResponse
    {
        $procedures = AdministrativeProcedure::query()
            ->when($request->string('q')->toString(), fn ($query, $term) => $query->where('name', 'ilike', "%{$term}%"))
            ->latest()
            ->paginate(20);

        return $this->collection($procedures->items(), [
            'meta' => ['page' => $procedures->currentPage(), 'last_page' => $procedures->lastPage(), 'total' => $procedures->total()],
        ]);
    }

    public function users(): JsonResponse
    {
        return $this->collection(User::with('organizations')->latest()->paginate(20)->items());
    }
}
