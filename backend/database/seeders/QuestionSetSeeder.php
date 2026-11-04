<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuestionSet;
use Illuminate\Database\Seeder;

/**
 * Bộ câu hỏi khai báo v1 (SRS 3.2 + §14): 15 câu, gồm 3 câu mới về dữ liệu nhạy cảm,
        * thị trường quốc tế và dịch vụ công. Câu trả lời là dữ kiện đầu vào của Rule Engine.
 */
class QuestionSetSeeder extends Seeder
{
    public function run(): void
    {
        /** @var QuestionSet $set */
        $set = QuestionSet::updateOrCreate(['version' => 1], [
            'status' => 'PUBLISHED',
            'published_at' => now(),
        ]);

        foreach (self::QUESTIONS as $index => $question) {
            Question::updateOrCreate(
                ['question_set_id' => $set->id, 'code' => $question['code']],
                array_merge($question, ['question_set_id' => $set->id, 'position' => $index + 1]),
            );
        }
    }

    private const QUESTIONS = [
        [
            'code' => 'sells_online',
            'section' => 'MÔ HÌNH HOẠT ĐỘNG',
            'prompt' => 'Bạn có bán hàng, cung cấp dịch vụ trực tuyến không?',
            'help' => 'Bao gồm website, ứng dụng, sàn giao dịch hoặc kênh mạng xã hội.',
            'type' => 'BOOL',
            'options' => null,
            'show_if' => null,
            'activity_tags' => ['ECOMMERCE'],
            'importance' => 'REQUIRED',
        ],
        [
            'code' => 'is_intermediary',
            'section' => 'MÔ HÌNH HOẠT ĐỘNG',
            'prompt' => 'Nền tảng của bạn chỉ kết nối người mua và người bán, không trực tiếp bán hàng?',
            'help' => 'Trả lời "Có" nếu bạn là trung gian thương mại điện tử.',
            'type' => 'BOOL',
            'options' => null,
            'show_if' => ['operator' => 'EQUALS', 'field' => 'sells_online', 'value' => true],
            'activity_tags' => ['INTERMEDIARY'],
            'importance' => 'REQUIRED',
        ],
        [
            'code' => 'allows_user_content',
            'section' => 'MÔ HÌNH HOẠT ĐỘNG',
            'prompt' => 'Người dùng có thể đăng tải, bình luận hoặc chia sẻ nội dung?',
            'help' => 'Bao gồm bài viết, hình ảnh, video, đánh giá.',
            'type' => 'BOOL',
            'options' => null,
            'show_if' => null,
            'activity_tags' => ['SOCIAL_NETWORK'],
            'importance' => 'REQUIRED',
        ],
        [
            'code' => 'publishes_editorial_content',
            'section' => 'MÔ HÌNH HOẠT ĐỘNG',
            'prompt' => 'Bạn có xuất bản nội dung báo chí, thông tin tổng hợp?',
            'help' => 'Trả lời "Có" nếu có ban biên tập hoặc nội dung tin tức.',
            'type' => 'BOOL',
            'options' => null,
            'show_if' => null,
            'activity_tags' => ['CONTENT_MEDIA'],
            'importance' => 'OPTIONAL',
        ],
        [
            'code' => 'handles_payments',
            'section' => 'MÔ HÌNH HOẠT ĐỘNG',
            'prompt' => 'Nền tảng có xử lý thanh toán, ví điện tử hoặc dịch vụ tài chính?',
            'help' => 'Bao gồm thanh toán trực tiếp, đối soát, cấp tín dụng số.',
            'type' => 'BOOL',
            'options' => null,
            'show_if' => null,
            'activity_tags' => ['FINTECH'],
            'importance' => 'REQUIRED',
        ],
        [
            'code' => 'provides_education',
            'section' => 'MÔ HÌNH HOẠT ĐỘNG',
            'prompt' => 'Bạn có cung cấp khóa học, đào tạo trực tuyến?',
            'help' => 'Bao gồm e-learning, lớp học trực tuyến, chứng chỉ số.',
            'type' => 'BOOL',
            'options' => null,
            'show_if' => null,
            'activity_tags' => ['EDTECH'],
            'importance' => 'OPTIONAL',
        ],
        [
            'code' => 'processes_health_data',
            'section' => 'DỮ LIỆU',
            'prompt' => 'Bạn có xử lý dữ liệu y tế, hồ sơ bệnh án?',
            'help' => 'Dữ liệu sức khỏe là dữ liệu nhạy cảm theo pháp luật hiện hành.',
            'type' => 'BOOL',
            'options' => null,
            'show_if' => null,
            'activity_tags' => ['HEALTHTECH', 'SENSITIVE_DATA'],
            'importance' => 'REQUIRED',
        ],
        [
            'code' => 'processes_sensitive_data',
            'section' => 'DỮ LIỆU',
            'prompt' => 'Bạn có xử lý dữ liệu nhạy cảm khác (tài chính, vị trí, sinh trắc học)?',
            'help' => 'Câu hỏi mới trong phiên bản này (SRS §14): dữ liệu nhạy cảm yêu cầu đánh giá tác động.',
            'type' => 'BOOL',
            'options' => null,
            'show_if' => null,
            'activity_tags' => ['SENSITIVE_DATA'],
            'importance' => 'REQUIRED',
        ],
        [
            'code' => 'collects_marketing_consent',
            'section' => 'DỮ LIỆU',
            'prompt' => 'Bạn có thu thập dữ liệu cho mục đích quảng cáo, tiếp thị?',
            'help' => 'Mục đích phụ cần đồng ý riêng của chủ thể dữ liệu.',
            'type' => 'BOOL',
            'options' => null,
            'show_if' => null,
            'activity_tags' => null,
            'importance' => 'OPTIONAL',
        ],
        [
            'code' => 'markets',
            'section' => 'PHẠM VI',
            'prompt' => 'Bạn phục vụ những thị trường nào?',
            'help' => 'Chọn các quốc gia/khu vực mà dịch vụ của bạn hướng tới.',
            'type' => 'MULTI',
            'options' => [
                ['value' => 'VN', 'label' => 'Việt Nam'],
                ['value' => 'US', 'label' => 'Hoa Kỳ'],
                ['value' => 'EU', 'label' => 'Liên minh Châu âu'],
                ['value' => 'SG', 'label' => 'Singapore'],
                ['value' => 'JP', 'label' => 'Nhật Bản'],
            ],
            'show_if' => null,
            'activity_tags' => null,
            'importance' => 'REQUIRED',
        ],
        [
            'code' => 'serves_public_sector',
            'section' => 'PHẠM VI',
            'prompt' => 'Bạn có cung cấp dịch vụ cho cơ quan nhà nước hoặc hệ thống thông tin quan trọng?',
            'help' => 'Câu hỏi mới trong phiên bản này (SRS §14): ảnh hưởng nghĩa vụ an toàn thông tin.',
            'type' => 'BOOL',
            'options' => null,
            'show_if' => null,
            'activity_tags' => ['PUBLIC_SECTOR'],
            'importance' => 'REQUIRED',
        ],
        [
            'code' => 'has_mobile_app',
            'section' => 'KỸ THUẬT',
            'prompt' => 'Bạn có ứng dụng di động trên cửa hàng ứng dụng?',
            'help' => 'Cửa hàng ứng dụng có quy định riêng về nội dung và dữ liệu.',
            'type' => 'BOOL',
            'options' => null,
            'show_if' => null,
            'activity_tags' => ['HAS_APP'],
            'importance' => 'REQUIRED',
        ],
        [
            'code' => 'stores_user_content',
            'section' => 'KỸ THUẬT',
            'prompt' => 'Bạn lưu trữ nội dung, tệp của người dùng?',
            'help' => 'Lưu trữ đi kèm trách nhiệm bảo mật và giữ bí mật dữ liệu.',
            'type' => 'BOOL',
            'options' => null,
            'show_if' => null,
            'activity_tags' => null,
            'importance' => 'REQUIRED',
        ],
        [
            'code' => 'monthly_users',
            'section' => 'QUY MÔ',
            'prompt' => 'Số người dùng hoạt động hàng tháng khoảng bao nhiêu?',
            'help' => 'Quy mô ảnh hưởng đến nghĩa vụ báo cáo và đánh giá tác động.',
            'type' => 'SINGLE',
            'options' => [
                ['value' => 'SMALL', 'label' => 'Dưới 10.000'],
                ['value' => 'MEDIUM', 'label' => '10.000 - 100.000'],
                ['value' => 'LARGE', 'label' => 'Trên 100.000'],
            ],
            'show_if' => null,
            'activity_tags' => null,
            'importance' => 'REQUIRED',
        ],
        [
            'code' => 'register_entity',
            'section' => 'TỔ CHỨC',
            'prompt' => 'Doanh nghiệp của bạn đã đăng ký kinh doanh tại Việt Nam?',
            'help' => 'Câu hỏi mới trong phiên bản này (SRS §14): quyết định tư cách chủ thể.',
            'type' => 'BOOL',
            'options' => null,
            'show_if' => null,
            'activity_tags' => null,
            'importance' => 'REQUIRED',
        ],
    ];
}
