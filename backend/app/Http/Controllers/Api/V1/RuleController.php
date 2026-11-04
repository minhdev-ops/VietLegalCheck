<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\RuleVersionStatus;
use App\Domain\RuleEngine\RuleTestRunner;
use App\Models\LegalRule;
use App\Models\LegalRuleVersion;
use App\Models\RuleApproval;
use App\Models\RuleTestCase;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Vòng đời quy tắc (SRS 4.14): soạn → kiểm thử → gửi duyệt → hai người phê duyệt
 * (BR-19/20) → có hiệu lực. Người phê duyệt không được là tác giả.
 */
class RuleController extends ApiController
{
    public function __construct(private RuleTestRunner $tests, private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $rules = LegalRule::query()
            ->with(['latestVersion'])
            ->when($request->string('q')->toString(), fn ($query, $term) => $query->where('name', 'ilike', "%{$term}%")->orWhere('code', 'ilike', "%{$term}%"))
            ->latest()
            ->paginate(20);

        return $this->collection($rules->items(), [
            'pending_approval' => LegalRuleVersion::where('status', RuleVersionStatus::IN_REVIEW)->count(),
            'failing_tests' => $this->tests->failingTestCount(),
            'meta' => ['page' => $rules->currentPage(), 'last_page' => $rules->lastPage(), 'total' => $rules->total()],
        ]);
    }

    public function show(LegalRule $rule): JsonResponse
    {
        return $this->ok(['rule' => $rule->load(['versions.provisions.document', 'versions.testCases', 'versions.approvals.approver'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:legal_rules,code'],
            'name' => ['required', 'string', 'max:200'],
            'legal_group' => ['required', 'string', 'max:48'],
            'description' => ['nullable', 'string'],
        ]);

        $rule = LegalRule::create($data);
        $this->audit->log('RULE_CREATED', $rule);

        return $this->ok(['rule' => $rule], 201);
    }

    public function storeVersion(Request $request, LegalRule $rule): JsonResponse
    {
        $data = $request->validate([
            'version' => ['nullable', 'integer', 'min:1'],
            'condition_json' => ['required', 'array'],
            'result_json' => ['required', 'array'],
            'severity' => ['required', 'string', 'in:CRITICAL,HIGH,MEDIUM,LOW,INFO'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'change_summary' => ['nullable', 'string', 'max:500'],
        ]);

        $versionNumber = $data['version'] ?? ($rule->versions()->max('version') + 1);
        $version = $rule->versions()->create([
            'version' => $versionNumber,
            'condition_json' => $data['condition_json'],
            'result_json' => $data['result_json'],
            'severity' => $data['severity'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'status' => RuleVersionStatus::DRAFT,
            'authored_by' => $request->user()->id,
            'change_summary' => $data['change_summary'] ?? null,
        ]);

        $this->audit->log('RULE_VERSION_CREATED', $version);

        return $this->ok(['version' => $version], 201);
    }

    /** Lưu test case cho phiên bản (FR-RULE-08). */
    public function storeTestCase(Request $request, LegalRuleVersion $version): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'facts' => ['required', 'array'],
            'expected_applicability' => ['required', 'string', 'in:APPLICABLE,NOT_APPLICABLE,NEED_MORE_INFO'],
        ]);

        $case = $version->testCases()->create($data);
        $this->audit->log('RULE_TEST_CREATED', $case);

        return $this->ok(['test_case' => $case], 201);
    }

    public function runTests(LegalRuleVersion $version): JsonResponse
    {
        $result = $this->tests->runVersion($version);
        $this->audit->log('RULE_TESTS_RUN', $version, ['passed' => $result['passed']]);

        return $this->ok($result);
    }

    /**
     * Gửi phê duyệt (BR-19): phải có ít nhất 1 test case và test phải đạt.
     */
    public function submit(LegalRuleVersion $version): JsonResponse
    {
        abort_if($version->status !== RuleVersionStatus::DRAFT, 422, 'Chỉ gửi duyệt phiên bản đang soạn.');
        abort_if($version->testCases()->count() === 0, 422, 'Phiên bản phải có ca kiểm thử trước khi gửi duyệt (BR-20).');

        if (! $this->tests->passes($version)) {
            return $this->ok(['passed' => false, 'message' => 'Ca kiểm thử chưa đạt; sửa điều kiện trước khi gửi duyệt.'], 422);
        }

        $version->update(['status' => RuleVersionStatus::IN_REVIEW, 'submitted_at' => now()]);
        $this->audit->log('RULE_VERSION_SUBMITTED', $version);

        return $this->ok(['version' => $version->fresh()]);
    }

    /**
     * Phê duyệt (BR-19): hai người, không trùng tác giả.
     */
    public function approve(Request $request, LegalRuleVersion $version): JsonResponse
    {
        abort_if($version->status !== RuleVersionStatus::IN_REVIEW, 422, 'Phiên bản không đang chờ duyệt.');
        abort_if($version->authored_by === $request->user()->id, 403, 'Người soạn không được phê duyệt phiên bản của chính mình (BR-19).');

        $data = $request->validate(['comment' => ['nullable', 'string', 'max:1000']]);

        $approved = DB::transaction(function () use ($version, $request, $data) {
            RuleApproval::create([
                'rule_version_id' => $version->id,
                'actor_id' => $request->user()->id,
                'decision' => 'APPROVED',
                'note' => $data['comment'] ?? null,
            ]);

            $count = $version->approvals()->where('decision', 'APPROVED')->count();
            if ($count >= 2) {
                $version->update([
                    'status' => RuleVersionStatus::APPROVED,
                    'approved_at' => now(),
                    'approved_by' => $request->user()->id,
                ]);
            }

            return $version;
        });

        $this->audit->log('RULE_VERSION_APPROVED', $approved, ['approvals' => $approved->approvals()->count()]);

        return $this->ok(['version' => $approved->fresh('approvals')]);
    }

    public function reject(Request $request, LegalRuleVersion $version): JsonResponse
    {
        abort_if($version->status !== RuleVersionStatus::IN_REVIEW, 422, 'Phiên bản không đang chờ duyệt.');

        $data = $request->validate(['comment' => ['required', 'string', 'max:1000']]);
        $version->update(['status' => RuleVersionStatus::REJECTED, 'approved_at' => now()]);

        RuleApproval::create([
            'rule_version_id' => $version->id,
            'actor_id' => $request->user()->id,
            'decision' => 'REJECTED',
            'note' => $data['comment'],
        ]);

        $this->audit->log('RULE_VERSION_REJECTED', $version);

        return $this->ok(['version' => $version->fresh()]);
    }
}
