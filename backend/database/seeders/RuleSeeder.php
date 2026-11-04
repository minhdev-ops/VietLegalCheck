<?php

namespace Database\Seeders;

use App\Domain\Enums\RuleVersionStatus;
use App\Domain\Enums\Severity;
use App\Models\LegalProvision;
use App\Models\LegalRule;
use App\Models\LegalRuleVersion;
use App\Models\RuleTestCase;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Bộ quy tắc MVP (SRS 3.4, §14). ~10 quy tắc; hai phiên bản của quy tắc dữ liệu cá nhân
 * nằm về hai phía của mốc 2026-07-01 để AC-19 (as_of_date) chứng minh được bằng dữ liệu.
 *
 * Ý tưởng: phiên bản cũ dựa trên Nghị định 13 (hết hiệu lực 2024-06-30), phiên bản mới
 * dựa trên Luật BVDC 2023 — Với as_of_date trước/sau 2026-07-01, kết quả khác nhau.
 * Ở đây mốc 2026-07-01 là ngày phiên bản mới có hiệu lực (giả định để test logic phiên bản).
 */
class RuleSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'legal.admin@vietlegal.local')->first();

        foreach (self::RULES as $rule) {
            $versions = $rule['versions'];
            unset($rule['versions']);

            $record = LegalRule::updateOrCreate(['code' => $rule['code']], $rule);

            foreach ($versions as $version) {
                $provisions = $version['provision_doc_numbers'];
                $testCases = $version['test_cases'] ?? [];
                unset($version['provision_doc_numbers'], $version['test_cases']);

                $existing = LegalRuleVersion::where('rule_id', $record->id)->where('version', $version['version'])->first();

                if (! $existing) {
                    $existing = LegalRuleVersion::create(array_merge($version, [
                        'rule_id' => $record->id,
                        'authored_by' => $admin?->id,
                        'approved_by' => $admin?->id,
                        'submitted_at' => now(),
                        'approved_at' => now(),
                    ]));
                } else {
                    $existing->update($version);
                }

                $provisionIds = LegalProvision::query()
                    ->whereIn('legal_documents.doc_number', $provisions)
                    ->join('legal_documents', 'legal_documents.id', '=', 'legal_provisions.legal_document_id')
                    ->where('legal_provisions.status', 'EFFECTIVE')
                    ->pluck('legal_provisions.id')
                    ->all();

                $existing->provisions()->sync($provisionIds);

                foreach ($testCases as $case) {
                    RuleTestCase::updateOrCreate(
                        ['rule_version_id' => $existing->id, 'name' => $case['name']],
                        array_merge($case, ['rule_version_id' => $existing->id]),
                    );
                }
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function rules(): array
    {
        return self::RULES;
    }

    private const RULES = [
        [
            'code' => 'R-DATA-01',
            'name' => 'Đồng ý của chủ thể dữ liệu',
            'legal_group' => 'BẢO VỆ DỮ LIỆU',
            'description' => 'Xin đồng ý rõ ràng, tự nguyện trước khi thu thập dữ liệu cá nhân.',
            'activity_tags' => ['ECOMMERCE', 'SOCIAL_NETWORK', 'HAS_APP'],
            'versions' => [
                [
                    'version' => 1,
                    'condition_json' => ['operator' => 'EQUALS', 'field' => 'sells_online', 'value' => true],
                    'result_json' => [
                        'applicability' => 'APPLICABLE',
                        'explanation_when_applicable' => 'Nghị định 13/2023 yêu cầu đồng ý của chủ thể dữ liệu khi xử lý dữ liệu cá nhân (phiên bản cũ).',
                        'recommendation' => 'Thêm cơ chế xin đồng ý rõ ràng trên website.',
                        'obligation' => ['name' => 'Xin đồng ý xử lý dữ liệu', 'authority' => 'Bộ Công an'],
                    ],
                    'severity' => 'HIGH',
                    'status' => 'APPROVED',
                    'effective_from' => '2023-07-01',
                    'effective_to' => '2026-06-30',
                    'provision_doc_numbers' => ['ND-CP-13-2023'],
                    'test_cases' => [
                        ['name' => 'Bán hàng online', 'facts' => ['sells_online' => true], 'expected_applicability' => 'APPLICABLE'],
                        ['name' => 'Không bán hàng online', 'facts' => ['sells_online' => false], 'expected_applicability' => 'NOT_APPLICABLE'],
                    ],
                ],
                [
                    'version' => 2,
                    'condition_json' => ['operator' => 'EQUALS', 'field' => 'sells_online', 'value' => true],
                    'result_json' => [
                        'applicability' => 'APPLICABLE',
                        'explanation_when_applicable' => 'Luật Bảo vệ dữ liệu cá nhân 2023 yêu cầu đồng ý tự nguyện, có thể rút lại; nghiêm ngặt hơn Nghị định 13.',
                        'recommendation' => 'Xin đồng ý từng mục đích; cung cấp cơ chế rút lại đồng ý.',
                        'obligation' => ['name' => 'Xin đồng ý xử lý dữ liệu (theo Luật BVDC)', 'authority' => 'Bộ Công an'],
                    ],
                    'severity' => 'CRITICAL',
                    'status' => 'APPROVED',
                    'effective_from' => '2026-07-01',
                    'effective_to' => null,
                    'provision_doc_numbers' => ['LUAT-BVDS-2023'],
                    'test_cases' => [
                        ['name' => 'Bán hàng online', 'facts' => ['sells_online' => true], 'expected_applicability' => 'APPLICABLE'],
                        ['name' => 'Không bán hàng online', 'facts' => ['sells_online' => false], 'expected_applicability' => 'NOT_APPLICABLE'],
                    ],
                ],
            ],
        ],
        [
            'code' => 'R-DATA-02',
            'name' => 'Đánh giá tác động bảo vệ dữ liệu cá nhân',
            'legal_group' => 'BẢO VỆ DỮ LIỆU',
            'description' => 'Bắt buộc khi xử lý dữ liệu nhạy cảm hoặc quy mô lớn.',
            'activity_tags' => ['SENSITIVE_DATA', 'HEALTHTECH'],
            'versions' => [
                [
                    'version' => 1,
                    'condition_json' => [
                        'operator' => 'OR',
                        'operands' => [
                            ['operator' => 'EQUALS', 'field' => 'processes_sensitive_data', 'value' => true],
                            ['operator' => 'EQUALS', 'field' => 'processes_health_data', 'value' => true],
                        ],
                    ],
                    'result_json' => [
                        'applicability' => 'APPLICABLE',
                        'explanation_when_applicable' => 'Xử lý dữ liệu nhạy cảm → phải đánh giá tác động trước khi xử lý.',
                        'recommendation' => 'Lập báo cáo đánh giá tác động và lưu giữ hồ sơ.',
                        'obligation' => ['name' => 'Đánh giá tác động dữ liệu cá nhân', 'authority' => 'Bộ Công an'],
                    ],
                    'severity' => 'CRITICAL',
                    'status' => 'APPROVED',
                    'effective_from' => '2024-07-01',
                    'effective_to' => null,
                    'provision_doc_numbers' => ['LUAT-BVDS-2023'],
                    'test_cases' => [
                        ['name' => 'Dữ liệu nhạy cảm', 'facts' => ['processes_sensitive_data' => true], 'expected_applicability' => 'APPLICABLE'],
                        ['name' => 'Dữ liệu y tế', 'facts' => ['processes_health_data' => true, 'processes_sensitive_data' => false], 'expected_applicability' => 'APPLICABLE'],
                        ['name' => 'Không nhạy cảm', 'facts' => ['processes_sensitive_data' => false, 'processes_health_data' => false], 'expected_applicability' => 'NOT_APPLICABLE'],
                    ],
                ],
            ],
        ],
        [
            'code' => 'R-DATA-03',
            'name' => 'Thông báo cho chủ thể dữ liệu',
            'legal_group' => 'BẢO VỆ DỮ LIỆU',
            'description' => 'Cung cấp thông báo về mục đích xử lý trước khi thu thập.',
            'activity_tags' => ['ECOMMERCE', 'HAS_APP'],
            'versions' => [
                [
                    'version' => 1,
                    'condition_json' => ['operator' => 'EQUALS', 'field' => 'sells_online', 'value' => true],
                    'result_json' => [
                        'applicability' => 'APPLICABLE',
                        'explanation_when_applicable' => 'Phải thông báo mục đích, loại dữ liệu và quyền của chủ thể dữ liệu.',
                        'recommendation' => 'Đăng chính sách quyền riêng tư và hiển thị tại điểm thu thập.',
                        'obligation' => ['name' => 'Thông báo chính sách quyền riêng tư'],
                    ],
                    'severity' => 'HIGH',
                    'status' => 'APPROVED',
                    'effective_from' => '2024-07-01',
                    'effective_to' => null,
                    'provision_doc_numbers' => ['LUAT-BVDS-2023'],
                    'test_cases' => [
                        ['name' => 'Có bán hàng', 'facts' => ['sells_online' => true], 'expected_applicability' => 'APPLICABLE'],
                    ],
                ],
            ],
        ],
        [
            'code' => 'R-CONTENT-01',
            'name' => 'Kiểm duyệt và gỡ bỏ nội dung vi phạm',
            'legal_group' => 'NỘI DUNG',
            'description' => 'Áp dụng khi người dùng có thể đăng tải nội dung.',
            'activity_tags' => ['SOCIAL_NETWORK', 'INTERMEDIARY'],
            'versions' => [
                [
                    'version' => 1,
                    'condition_json' => ['operator' => 'EQUALS', 'field' => 'allows_user_content', 'value' => true],
                    'result_json' => [
                        'applicability' => 'APPLICABLE',
                        'explanation_when_applicable' => 'Phải có cơ chế kiểm duyệt, gỡ bỏ nội dung xấu, độc hại và cung cấp kênh liên hệ.',
                        'recommendation' => 'Đăng ký cung cấp dịch vụ và bổ sung quy trình kiểm duyệt 24 giờ.',
                        'obligation' => ['name' => 'Kiểm duyệt nội dung người dùng', 'authority' => 'Bộ Thông tin và Truyền thông'],
                    ],
                    'severity' => 'CRITICAL',
                    'status' => 'APPROVED',
                    'effective_from' => '2023-06-01',
                    'effective_to' => null,
                    'provision_doc_numbers' => ['LUAT-BCQT-2018', 'TT-BTTTT-03-2023'],
                    'test_cases' => [
                        ['name' => 'Có nội dung người dùng', 'facts' => ['allows_user_content' => true], 'expected_applicability' => 'APPLICABLE'],
                        ['name' => 'Không có', 'facts' => ['allows_user_content' => false], 'expected_applicability' => 'NOT_APPLICABLE'],
                    ],
                ],
            ],
        ],
        [
            'code' => 'R-CONTENT-02',
            'name' => 'Thông tin liên hệ phục vụ phối hợp xử lý sự cố',
            'legal_group' => 'NỘI DUNG',
            'description' => 'Cung cấp thông tin liên hệ đầy đủ (Luật ANM Điều 17).',
            'activity_tags' => ['ECOMMERCE', 'SOCIAL_NETWORK'],
            'versions' => [
                [
                    'version' => 1,
                    'condition_json' => [
                        'operator' => 'OR',
                        'operands' => [
                            ['operator' => 'EQUALS', 'field' => 'sells_online', 'value' => true],
                            ['operator' => 'EQUALS', 'field' => 'allows_user_content', 'value' => true],
                        ],
                    ],
                    'result_json' => [
                        'applicability' => 'APPLICABLE',
                        'explanation_when_applicable' => 'Phải công khai thông tin liên hệ để cơ quan chức năng phối hợp xử lý sự cố.',
                        'recommendation' => 'Thêm trang liên hệ đầy đủ tên đơn vị, địa chỉ, điện thoại, email.',
                        'obligation' => ['name' => 'Công khai thông tin liên hệ'],
                    ],
                    'severity' => 'MEDIUM',
                    'status' => 'APPROVED',
                    'effective_from' => '2019-01-01',
                    'effective_to' => null,
                    'provision_doc_numbers' => ['LUAT-BCQT-2018'],
                    'test_cases' => [
                        ['name' => 'Chỉ bán hàng', 'facts' => ['sells_online' => true, 'allows_user_content' => false], 'expected_applicability' => 'APPLICABLE'],
                        ['name' => 'Không hoạt động', 'facts' => ['sells_online' => false, 'allows_user_content' => false], 'expected_applicability' => 'NOT_APPLICABLE'],
                    ],
                ],
            ],
        ],
        [
            'code' => 'R-CYBER-01',
            'name' => 'Bảo mật hệ thống và đánh giá an toàn thông tin định kỳ',
            'legal_group' => 'AN NINH MẠNG',
            'description' => 'Áp dụng cho dịch vụ lưu trữ, mạng xã hội; định kỳ đánh giá.',
            'activity_tags' => ['SOCIAL_NETWORK', 'PUBLIC_SECTOR'],
            'versions' => [
                [
                    'version' => 1,
                    'condition_json' => [
                        'operator' => 'OR',
                        'operands' => [
                            ['operator' => 'EQUALS', 'field' => 'stores_user_content', 'value' => true],
                            ['operator' => 'EQUALS', 'field' => 'serves_public_sector', 'value' => true],
                        ],
                    ],
                    'result_json' => [
                        'applicability' => 'APPLICABLE',
                        'explanation_when_applicable' => 'Hệ thống lưu trữ nội dung người dùng hoặc phục vụ khu vực công → đánh giá an toàn thông tin định kỳ.',
                        'recommendation' => 'Lên kế hoạch đánh giá định kỳ 06 tháng/lần và lưu kết quả.',
                        'obligation' => ['name' => 'Đánh giá an toàn thông tin định kỳ', 'authority' => 'Bộ Thông tin và Truyền thông'],
                    ],
                    'severity' => 'HIGH',
                    'status' => 'APPROVED',
                    'effective_from' => '2022-07-01',
                    'effective_to' => null,
                    'provision_doc_numbers' => ['ND-CP-53-2022'],
                    'test_cases' => [
                        ['name' => 'Lưu trữ nội dung', 'facts' => ['stores_user_content' => true], 'expected_applicability' => 'APPLICABLE'],
                        ['name' => 'Phục vụ khu vực công', 'facts' => ['stores_user_content' => false, 'serves_public_sector' => true], 'expected_applicability' => 'APPLICABLE'],
                        ['name' => 'Không áp dụng', 'facts' => ['stores_user_content' => false, 'serves_public_sector' => false], 'expected_applicability' => 'NOT_APPLICABLE'],
                    ],
                ],
            ],
        ],
        [
            'code' => 'R-CYBER-02',
            'name' => 'Bảo mật dữ liệu khách hàng của doanh nghiệp dịch vụ mạng',
            'legal_group' => 'AN NINH MẠNG',
            'description' => 'Chế độ bảo mật, cung cấp biện pháp bảo mật cho khách hàng.',
            'activity_tags' => ['ECOMMERCE', 'HAS_APP'],
            'versions' => [
                [
                    'version' => 1,
                    'condition_json' => ['operator' => 'EQUALS', 'field' => 'sells_online', 'value' => true],
                    'result_json' => [
                        'applicability' => 'APPLICABLE',
                        'explanation_when_applicable' => 'Luật An ninh mạng yêu cầu doanh nghiệp dịch vụ mạng có chế độ bảo mật dữ liệu khách hàng.',
                        'recommendation' => 'Ban hành chính sách bảo mật, mã hóa truyền tải và kiểm soát truy cập.',
                        'obligation' => ['name' => 'Chế độ bảo mật dữ liệu khách hàng'],
                    ],
                    'severity' => 'HIGH',
                    'status' => 'APPROVED',
                    'effective_from' => '2019-01-01',
                    'effective_to' => null,
                    'provision_doc_numbers' => ['LUAT-BCQT-2018'],
                    'test_cases' => [],
                ],
            ],
        ],
        [
            'code' => 'R-FIN-01',
            'name' => 'Cấp phép dịch vụ tài chính số',
            'legal_group' => 'TÀI CHÍNH',
            'description' => 'Cung cấp dịch vụ thanh toán trung gian cần giấy phép.',
            'activity_tags' => ['FINTECH'],
            'versions' => [
                [
                    'version' => 1,
                    'condition_json' => ['operator' => 'EQUALS', 'field' => 'handles_payments', 'value' => true],
                    'result_json' => [
                        'applicability' => 'APPLICABLE',
                        'explanation_when_applicable' => 'Cung cấp dịch vụ thanh toán trung gian, ví điện tử → xin giấy phép Ngân hàng Nhà nước.',
                        'recommendation' => 'Chuẩn bị hồ sơ xin cấp phép và đối soát tài chính.',
                        'obligation' => ['name' => 'Xin giấy phép dịch vụ thanh toán', 'authority' => 'Ngân hàng Nhà nước'],
                    ],
                    'severity' => 'CRITICAL',
                    'status' => 'APPROVED',
                    'effective_from' => '2024-01-01',
                    'effective_to' => null,
                    'provision_doc_numbers' => ['LUAT-TMDT-2023'],
                    'test_cases' => [
                        ['name' => 'Xử lý thanh toán', 'facts' => ['handles_payments' => true], 'expected_applicability' => 'APPLICABLE'],
                        ['name' => 'Không thanh toán', 'facts' => ['handles_payments' => false], 'expected_applicability' => 'NOT_APPLICABLE'],
                    ],
                ],
            ],
        ],
        [
            'code' => 'R-CORP-01',
            'name' => 'Đăng ký kinh doanh và nghĩa vụ tài chính',
            'legal_group' => 'DOANH NGHIỆP',
            'description' => 'Doanh nghiệp đã đăng ký phải thực hiện nghĩa vụ tài chính, thuế.',
            'activity_tags' => ['ECOMMERCE'],
            'versions' => [
                [
                    'version' => 1,
                    'condition_json' => ['operator' => 'EQUALS', 'field' => 'register_entity', 'value' => true],
                    'result_json' => [
                        'applicability' => 'APPLICABLE',
                        'explanation_when_applicable' => 'Đã đăng ký kinh doanh → thực hiện nghĩa vụ kê khai, nộp thuế và bảo hiểm.',
                        'recommendation' => 'Đối chiếu lại lịch kê khai thuế và bảo hiểm xã hội.',
                        'obligation' => ['name' => 'Nghĩa vụ thuế - bảo hiểm', 'authority' => 'Cơ quan thuế'],
                    ],
                    'severity' => 'HIGH',
                    'status' => 'APPROVED',
                    'effective_from' => '2021-01-01',
                    'effective_to' => null,
                    'provision_doc_numbers' => ['LUAT-DT-2018'],
                    'test_cases' => [
                        ['name' => 'Đã đăng ký', 'facts' => ['register_entity' => true], 'expected_applicability' => 'APPLICABLE'],
                        ['name' => 'Chưa đăng ký', 'facts' => ['register_entity' => false], 'expected_applicability' => 'NEED_MORE_INFO'],
                    ],
                ],
            ],
        ],
        [
            'code' => 'R-INTL-01',
            'name' => 'Đối chiếu nghĩa vụ thị trường quốc tế',
            'legal_group' => 'QUỐC TẾ',
            'description' => 'Phục vụ thị trường nước ngoài → tra cứu nghĩa vụ tương ứng.',
            'activity_tags' => ['CROSS_BORDER'],
            'versions' => [
                [
                    'version' => 1,
                    'condition_json' => [
                        'operator' => 'EXISTS',
                        'field' => 'markets',
                    ],
                    'result_json' => [
                        'applicability' => 'APPLICABLE',
                        'unknown_applicability' => 'NEED_MORE_INFO',
                        'explanation_when_applicable' => 'Phục vụ khách hàng quốc tế → kiểm tra nghĩa vụ GDPR (EU), PDPA (Singapore).',
                        'recommendation' => 'Liệt thị trường và đối chiếu nghĩa vụ từng thị trường.',
                        'obligation' => ['name' => 'Đối chiếu nghĩa vụ thị trường'],
                    ],
                    'severity' => 'MEDIUM',
                    'status' => 'APPROVED',
                    'effective_from' => '2024-01-01',
                    'effective_to' => null,
                    'provision_doc_numbers' => ['LUAT-BVDS-2023'],
                    'test_cases' => [
                        ['name' => 'Có thị trường', 'facts' => ['markets' => ['EU']], 'expected_applicability' => 'APPLICABLE'],
                        ['name' => 'Chưa khai báo', 'facts' => [], 'expected_applicability' => 'NEED_MORE_INFO'],
                    ],
                ],
            ],
        ],
    ];
}
