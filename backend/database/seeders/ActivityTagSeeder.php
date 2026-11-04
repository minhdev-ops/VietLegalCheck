<?php

namespace Database\Seeders;

use App\Models\ActivityTag;
use App\Models\ActivityTagRule;
use Illuminate\Database\Seeder;

/**
 * 11 thẻ hoạt động (SRS 3.3) + quy tắc gắn thẻ (FR-CLASS-01..04).
 * Quy tắc dạng Condition JSON của RuleEvaluator; mỗi thẻ cho lý do dẫn chứng câu hỏi.
 */
class ActivityTagSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::TAGS as $code => $tag) {
            ActivityTag::updateOrCreate(['code' => $code], $tag);
        }

        foreach (self::RULES as $rule) {
            ActivityTagRule::updateOrCreate(
                ['tag_code' => $rule['tag_code'], 'position' => $rule['position']],
                $rule,
            );
        }
    }

    private const TAGS = [
        'ECOMMERCE' => ['name_vi' => 'Thương mại điện tử', 'group' => 'BUSINESS', 'description' => 'Bán hàng qua website, ứng dụng, sàn giao dịch.', 'position' => 1],
        'SOCIAL_NETWORK' => ['name_vi' => 'Mạng xã hội', 'group' => 'BUSINESS', 'description' => 'Dịch vụ cho phép người dùng tạo nội dung và tương tác.', 'position' => 2],
        'CONTENT_MEDIA' => ['name_vi' => 'Báo chí - truyền thông', 'group' => 'BUSINESS', 'description' => 'Cung cấp thông tin, tin tức, nội dung số.', 'position' => 3],
        'INTERMEDIARY' => ['name_vi' => 'Trung gian thương mại điện tử', 'group' => 'BUSINESS', 'description' => 'Cung cấp dịch vụ trung gian kết nối giao dịch.', 'position' => 4],
        'FINTECH' => ['name_vi' => 'Dịch vụ tài chính số', 'group' => 'BUSINESS', 'description' => 'Thanh toán, ví điện tử, tài chính trực tuyến.', 'position' => 5],
        'HEALTHTECH' => ['name_vi' => 'Y tế số', 'group' => 'BUSINESS', 'description' => 'Dịch vụ y tế, hồ sơ bệnh án điện tử.', 'position' => 6],
        'EDTECH' => ['name_vi' => 'Giáo dục số', 'group' => 'BUSINESS', 'description' => 'Khóa học trực tuyến, nền tảng học tập.', 'position' => 7],
        'CROSS_BORDER' => ['name_vi' => 'Văn bản có yếu tố nước ngoài', 'group' => 'SCOPE', 'description' => 'Cung cấp dịch vụ ra thị trường quốc tế.', 'position' => 8],
        'PUBLIC_SECTOR' => ['name_vi' => 'Khu vực công', 'group' => 'SCOPE', 'description' => 'Phục vụ cơ quan nhà nước hoặc hệ thống thông tin quan trọng.', 'position' => 9],
        'HAS_APP' => ['name_vi' => 'Có ứng dụng di động', 'group' => 'TECH', 'description' => 'Cung cấp ứng dụng trên cửa hàng ứng dụng.', 'position' => 10],
        'SENSITIVE_DATA' => ['name_vi' => 'Xử lý dữ liệu nhạy cảm', 'group' => 'DATA', 'description' => 'Dữ liệu sức khỏe, tài chính, vị trí, sinh trắc học.', 'position' => 11],
    ];

    private const RULES = [
        [
            'tag_code' => 'ECOMMERCE',
            'condition_json' => ['operator' => 'EQUALS', 'field' => 'sells_online', 'value' => true],
            'reason_template' => 'Khai báo bán hàng trực tuyến ({questions}) → áp dụng quy định thương mại điện tử.',
            'position' => 1,
            'is_active' => true,
        ],
        [
            'tag_code' => 'SOCIAL_NETWORK',
            'condition_json' => ['operator' => 'EQUALS', 'field' => 'allows_user_content', 'value' => true],
            'reason_template' => 'Người dùng có thể tạo và chia sẻ nội dung ({questions}) → áp dụng quy định mạng xã hội.',
            'position' => 2,
            'is_active' => true,
        ],
        [
            'tag_code' => 'CONTENT_MEDIA',
            'condition_json' => ['operator' => 'EQUALS', 'field' => 'publishes_editorial_content', 'value' => true],
            'reason_template' => 'Có nội dung báo chí, thông tin ({questions}) → áp dụng quy định báo chí - truyền thông.',
            'position' => 3,
            'is_active' => true,
        ],
        [
            'tag_code' => 'INTERMEDIARY',
            'condition_json' => ['operator' => 'EQUALS', 'field' => 'is_intermediary', 'value' => true],
            'reason_template' => 'Sàn chỉ kết nối người mua - người bán ({questions}) → áp dụng quy định trung gian thương mại điện tử.',
            'position' => 4,
            'is_active' => true,
        ],
        [
            'tag_code' => 'FINTECH',
            'condition_json' => ['operator' => 'EQUALS', 'field' => 'handles_payments', 'value' => true],
            'reason_template' => 'Xử lý thanh toán, tài chính ({questions}) → áp dụng quy định dịch vụ tài chính.',
            'position' => 5,
            'is_active' => true,
        ],
        [
            'tag_code' => 'HEALTHTECH',
            'condition_json' => ['operator' => 'EQUALS', 'field' => 'processes_health_data', 'value' => true],
            'reason_template' => 'Xử lý dữ liệu y tế ({questions}) → áp dụng quy định y tế và dữ liệu nhạy cảm.',
            'position' => 6,
            'is_active' => true,
        ],
        [
            'tag_code' => 'EDTECH',
            'condition_json' => ['operator' => 'EQUALS', 'field' => 'provides_education', 'value' => true],
            'reason_template' => 'Cung cấp khóa học, đào tạo ({questions}) → áp dụng quy định giáo dục.',
            'position' => 7,
            'is_active' => true,
        ],
        [
            'tag_code' => 'CROSS_BORDER',
            'condition_json' => ['operator' => 'IN', 'field' => 'markets', 'value' => ['US', 'EU', 'SG', 'JP']],
            'reason_template' => 'Phục vụ khách hàng quốc tế ({questions}) → đối chiếu nghĩa vụ của thị trường đó.',
            'position' => 8,
            'is_active' => true,
        ],
        [
            'tag_code' => 'PUBLIC_SECTOR',
            'condition_json' => ['operator' => 'EQUALS', 'field' => 'serves_public_sector', 'value' => true],
            'reason_template' => 'Phục vụ cơ quan nhà nước ({questions}) → áp dụng quy định hệ thống thông tin quan trọng.',
            'position' => 9,
            'is_active' => true,
        ],
        [
            'tag_code' => 'HAS_APP',
            'condition_json' => ['operator' => 'EQUALS', 'field' => 'has_mobile_app', 'value' => true],
            'reason_template' => 'Có ứng dụng di động ({questions}) → áp dụng thêm quy định cửa hàng ứng dụng.',
            'position' => 10,
            'is_active' => true,
        ],
        [
            'tag_code' => 'SENSITIVE_DATA',
            'condition_json' => ['operator' => 'EQUALS', 'field' => 'processes_sensitive_data', 'value' => true],
            'reason_template' => 'Dữ liệu nhạy cảm ({questions}) → yêu cầu đánh giá tác động dữ liệu cá nhân.',
            'position' => 11,
            'is_active' => true,
        ],
    ];
}
