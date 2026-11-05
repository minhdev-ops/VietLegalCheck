<?php

namespace Tests\Feature;

use App\Domain\Enums\Applicability;
use App\Domain\Enums\SystemRole;
use App\Domain\RuleEngine\AssessmentEngine;
use App\Models\Assessment;
use App\Models\LegalDocument;
use App\Models\LegalProvision;
use App\Models\LegalRule;
use App\Models\LegalRuleVersion;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Luồng đánh giá đầu cuối (SRS 4.4–4.7): tạo dự án → lưu hồ sơ → chạy AssessmentEngine
 * tại hai ngày pháp lý khác nhau (AC-19: as_of_date).
 */
class AssessmentFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRules();
    }

    public function test_assessment_picks_version_effective_at_as_of_date(): void
    {
        $project = $this->project();
        $answers = ['sells_online' => true, 'register_entity' => true];

        $engine = app(AssessmentEngine::class);

        $before = $engine->run($project, $answers, Carbon::parse('2026-06-30'));
        $after = $engine->run($project, $answers, Carbon::parse('2026-07-01'));

        $beforeVersion = collect($before['rule_set_snapshot'])->firstWhere('rule_code', 'R-DATA-01');
        $afterVersion = collect($after['rule_set_snapshot'])->firstWhere('rule_code', 'R-DATA-01');

        $this->assertSame(1, $beforeVersion['version'], 'Đúng phiên bản cũ trước mốc 2026-07-01');
        $this->assertSame(2, $afterVersion['version'], 'Đúng phiên bản mới từ 2026-07-01');
    }

    public function test_findings_carry_obligation_and_basis(): void
    {
        $project = $this->project();
        $answers = ['sells_online' => true];

        $engine = app(AssessmentEngine::class);
        $result = $engine->run($project, $answers, Carbon::parse('2026-07-01'));

        $applicable = $result['findings']->first(fn ($finding) => $finding->rule_code === 'R-DATA-01');

        $this->assertNotNull($applicable, 'Có finding cho R-DATA-01');
        $this->assertSame(Applicability::APPLICABLE, $applicable->applicability);
        $this->assertNotEmpty($applicable->legal_basis, 'Căn cứ điều khoản phải đi kèm');
        $this->assertSame('91/2025/QH15', $applicable->legal_basis[0]['document_number']);
    }

    public function test_missing_answer_produces_need_more_info(): void
    {
        $project = $this->project();
        $engine = app(AssessmentEngine::class);

        $result = $engine->run($project, [], Carbon::parse('2026-07-01'));

        $unknown = $result['findings']->first(fn ($finding) => $finding->applicability === Applicability::NEED_MORE_INFO);
        $this->assertNotNull($unknown, 'Thiếu dữ kiện → NEED_MORE_INFO');
        $this->assertNotNull($unknown->required_info);
        $this->assertContains('sells_online', collect($unknown->required_info)->pluck('field')->all());
    }

    public function test_tags_assigned_from_answers(): void
    {
        $project = $this->project();
        $engine = app(AssessmentEngine::class);

        $result = $engine->run($project, ['sells_online' => true, 'allows_user_content' => true], Carbon::today());

        $codes = collect($result['tags'])->pluck('tag_code')->all();

        $this->assertContains('ECOMMERCE', $codes);
        $this->assertContains('SOCIAL_NETWORK', $codes);
        $this->assertNotEmpty(collect($result['tags'])->firstWhere('tag_code', 'ECOMMERCE')['reason']);
    }

    public function test_stale_basis_flags_need_expert_review(): void
    {
        $project = $this->project();
        $engine = app(AssessmentEngine::class);

        // Văn bản căn cứ đã hết hiệu lực tại ngày đánh giá → NEED_EXPERT_REVIEW.
        $result = $engine->run($project, ['sells_online' => true], Carbon::parse('2026-06-30'));

        $stale = $result['findings']->first(fn ($finding) => $finding->applicability === Applicability::NEED_EXPERT_REVIEW);
        $this->assertNotNull($stale, 'Căn cứ lỗi thời → NEED_EXPERT_REVIEW');
    }

    public function test_project_isolation_between_organizations(): void
    {
        $otherUser = User::factory()->create(['system_role' => SystemRole::USER]);
        $otherOrg = Organization::create(['name' => 'Khac']);
        $otherOrg->members()->create(['user_id' => $otherUser->id, 'role' => 'OWNER']);
        $otherProject = Project::create(['organization_id' => $otherOrg->id, 'name' => 'Của người khác']);

        $this->actingAs($otherUser)
            ->getJson("/api/v1/projects/{$otherProject->id}")
            ->assertOk();

        $intruder = User::factory()->create(['system_role' => SystemRole::USER]);
        $this->actingAs($intruder)
            ->getJson("/api/v1/projects/{$otherProject->id}")
            ->assertForbidden();
    }

    private function project(): Project
    {
        $user = User::factory()->create(['system_role' => SystemRole::USER]);
        $organization = Organization::create(['name' => 'Test org']);
        $organization->members()->create(['user_id' => $user->id, 'role' => 'OWNER']);

        return Project::create([
            'organization_id' => $organization->id,
            'name' => 'Dự án kiểm thử',
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Hai phiên bản quy tắc R-DATA-01 về hai phía mốc 2026-07-01 (AC-19).
     */
    private function seedRules(): void
    {
        $document = LegalDocument::create([
            'doc_number' => '91/2025/QH15',
            'title' => 'Luật Bảo vệ dữ liệu cá nhân 2023',
            'doc_type' => 'LUAT',
            'effective_from' => '2024-07-01',
            'status' => 'IN_EFFECT',
            'source_type' => 'PRIMARY',
        ]);

        $provision = LegalProvision::create([
            'legal_document_id' => $document->id,
            'article' => 'Điều 11',
            'heading' => 'Đồng ý của chủ thể dữ liệu',
            'content' => 'Xử lý dữ liệu cá nhân phải có đồng ý tự nguyện, rõ ràng.',
            'effective_from' => '2024-07-01',
            'status' => 'EFFECTIVE',
        ]);

        $expired = LegalDocument::create([
            'doc_number' => '13/2023/NĐ-CP',
            'title' => 'Nghị định 13/2023',
            'doc_type' => 'NGHI_DINH',
            'effective_from' => '2023-07-01',
            'effective_to' => '2026-05-31',
            'status' => 'EXPIRED',
            'source_type' => 'PRIMARY',
        ]);

        $expiredProvision = LegalProvision::create([
            'legal_document_id' => $expired->id,
            'article' => 'Điều 5',
            'content' => 'Nguyên tắc chung.',
            'effective_from' => '2023-07-01',
            'effective_to' => '2026-05-31',
            'status' => 'EFFECTIVE',
        ]);

        $rule = LegalRule::create([
            'code' => 'R-DATA-01',
            'name' => 'Đồng ý của chủ thể dữ liệu',
            'legal_group' => 'BẢO VỆ DỮ LIỆU',
        ]);

        $old = LegalRuleVersion::create([
            'rule_id' => $rule->id,
            'version' => 1,
            'condition_json' => ['field' => 'sells_online', 'operator' => 'EQUALS', 'value' => true],
            'result_json' => ['applicability' => 'APPLICABLE'],
            'severity' => 'HIGH',
            'status' => 'APPROVED',
            'effective_from' => '2023-07-01',
            'effective_to' => '2026-06-30',
        ]);
        $old->provisions()->sync([$expiredProvision->id]);

        $new = LegalRuleVersion::create([
            'rule_id' => $rule->id,
            'version' => 2,
            'condition_json' => ['field' => 'sells_online', 'operator' => 'EQUALS', 'value' => true],
            'result_json' => ['applicability' => 'APPLICABLE'],
            'severity' => 'CRITICAL',
            'status' => 'APPROVED',
            'effective_from' => '2026-07-01',
        ]);
        $new->provisions()->sync([$provision->id]);
    }
}
