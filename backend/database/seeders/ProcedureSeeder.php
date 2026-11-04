<?php

namespace Database\Seeders;

use App\Models\AdministrativeProcedure;
use App\Models\ProcedureRequirement;
use Illuminate\Database\Seeder;

/**
 * Thủ tục hành chính mẫu (SRS §14): đăng ký cung cấp dịch vụ mạng xã hội, thông báo
 * lưu trữ, đăng ký hệ thống thông tin quan trọng, cấp phép thanh toán.
 */
class ProcedureSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::PROCEDURES as $procedure) {
            $requirements = $procedure['requirements'];
            unset($procedure['requirements']);

            /** @var AdministrativeProcedure $record */
            $record = AdministrativeProcedure::updateOrCreate(['code' => $procedure['code']], $procedure);

            foreach ($requirements as $position => $requirement) {
                ProcedureRequirement::updateOrCreate(
                    ['procedure_id' => $record->id, 'code' => $requirement['code']],
                    array_merge($requirement, ['procedure_id' => $record->id, 'position' => $position]),
                );
            }
        }
    }

    private const PROCEDURES = [
        [
            'code' => 'TT-DV-MXH',
            'name' => 'Đăng ký cung cấp dịch vụ mạng xã hội, dịch vụ lưu trữ mạng',
            'authority' => 'Bộ Thông tin và Truyền thông',
            'subject' => 'Doanh nghiệp, tổ chức cung cấp dịch vụ mạng xã hội hoặc dịch vụ lưu trữ mạng',
            'conditions' => 'Doanh nghiệp đăng ký kinh doanh, có hệ thống kiểm duyệt nội dung và thông tin liên hệ.',
            'steps' => [
                ['title' => 'Chuẩn bị hồ sơ', 'detail' => 'Giấy chứng nhận đăng ký doanh nghiệp, mô tả dịch vụ, quy trình kiểm duyệt.'],
                ['title' => 'Nộp hồ sơ trực tuyến', 'detail' => 'Nộp qua Cổng dịch vụ công của Bộ Thông tin và Truyền thông.'],
                ['title' => 'Bổ sung, giải trình', 'detail' => 'Trả lời yêu cầu giải trình trong thời hạn 05 ngày làm việc.'],
                ['title' => 'Nhận kết quả', 'detail' => 'Giấy xác nhận đăng ký cung cấp dịch vụ.'],
            ],
            'submission_methods' => ['ONLINE'],
            'processing_time' => '10 ngày làm việc',
            'fee' => 'Không',
            'result' => 'Giấy xác nhận đã đăng ký cung cấp dịch vụ',
            'portal_url' => 'https://dichvucong.mic.gov.vn',
            'legal_basis' => [
                ['provision_id' => null, 'citation' => 'Điều 11', 'document_number' => 'TT-BTTTT-03-2023'],
                ['provision_id' => null, 'citation' => 'Điều 25', 'document_number' => 'LUAT-BCQT-2018'],
            ],
            'activity_tags' => ['SOCIAL_NETWORK', 'INTERMEDIARY'],
            'obligation_codes' => ['R-CONTENT-01'],
            'version' => 1,
            'last_verified_at' => '2026-06-30 00:00:00',
            'requirements' => [
                ['code' => 'DK-01', 'name' => 'Giấy chứng nhận đăng ký doanh nghiệp', 'description' => 'Bản sao y hợp lệ.', 'required' => true],
                ['code' => 'DK-02', 'name' => 'Mô tả dịch vụ', 'description' => 'Tài liệu mô tả tính năng, phạm vi cung cấp.', 'required' => true],
                ['code' => 'DK-03', 'name' => 'Quy trình kiểm duyệt nội dung', 'description' => 'Quy trình xử lý, gỡ bỏ nội dung vi phạm.', 'required' => true],
                ['code' => 'DK-04', 'name' => 'Thông tin liên hệ', 'description' => 'Tên đơn vị, địa chỉ, điện thoại, email phục vụ phối hợp.', 'required' => true],
            ],
        ],
        [
            'code' => 'TT-HT-TT',
            'name' => 'Đăng ký hệ thống thông tin quan trọng quốc gia',
            'authority' => 'Ban An toàn thông tin mạng quốc gia',
            'subject' => 'Cơ quan, tổ chức quản lý hệ thống thông tin quan trọng',
            'conditions' => 'Hệ thống thuộc danh mục hệ thống thông tin quan trọng quốc gia.',
            'steps' => [
                ['title' => 'Lập hồ sơ', 'detail' => 'Xác định phân cấp, biện pháp an toàn thông tin.'],
                ['title' => 'Nộp hồ sơ', 'detail' => 'Nộp cho cơ quan chuyên trách an toàn thông tin.'],
                ['title' => 'Đánh giá, phê duyệt', 'detail' => 'Hồ sơ được đánh giá và phê duyệt.'],
            ],
            'submission_methods' => ['IN_PERSON'],
            'processing_time' => '30 ngày',
            'fee' => 'Không',
            'result' => 'Quyết định phê duyệt',
            'portal_url' => 'https://ats.vn',
            'legal_basis' => [
                ['provision_id' => null, 'citation' => 'Điều 12', 'document_number' => 'ND-CP-14-2025'],
            ],
            'activity_tags' => ['PUBLIC_SECTOR'],
            'obligation_codes' => ['R-CYBER-01'],
            'version' => 1,
            'last_verified_at' => null,
            'requirements' => [
                ['code' => 'HT-01', 'name' => 'Tờ trình đề nghị', 'description' => 'Theo mẫu.', 'required' => true],
                ['code' => 'HT-02', 'name' => 'Mô tả hệ thống', 'description' => 'Kiến trúc, phạm vi, biện pháp an toàn.', 'required' => true],
            ],
        ],
        [
            'code' => 'TT-GP-TT',
            'name' => 'Cấp phép cung cấp dịch vụ thanh toán trung gian',
            'authority' => 'Ngân hàng Nhà nước Việt Nam',
            'subject' => 'Tổ chức muốn cung cấp dịch vụ thanh toán trung gian',
            'conditions' => 'Có vốn điều lệ theo quy định, hệ thống đáp ứng tiêu chuẩn an toàn.',
            'steps' => [
                ['title' => 'Chuẩn bị hồ sơ', 'detail' => 'Phương án hoạt động, chứng minh năng lực tài chính.'],
                ['title' => 'Nộp hồ sơ', 'detail' => 'Nộp trực tiếp hoặc qua bưu điện cho NHNN.'],
                ['title' => 'Xem xét, phê duyệt', 'detail' => 'NHNN thẩm định và ra quyết định.'],
            ],
            'submission_methods' => ['IN_PERSON', 'POST'],
            'processing_time' => '45 ngày',
            'fee' => 'Không',
            'result' => 'Giấy phép cung cấp dịch vụ thanh toán trung gian',
            'portal_url' => 'https://sbv.gov.vn',
            'legal_basis' => [
                ['provision_id' => null, 'citation' => 'Điều 8', 'document_number' => 'LUAT-TMDT-2023'],
            ],
            'activity_tags' => ['FINTECH'],
            'obligation_codes' => ['R-FIN-01'],
            'version' => 1,
            'last_verified_at' => '2026-06-30 00:00:00',
            'requirements' => [
                ['code' => 'GP-01', 'name' => 'Đơn xin cấp phép', 'description' => 'Đơn xin cấp phép theo mẫu.', 'required' => true],
                ['code' => 'GP-02', 'name' => 'Phương án hoạt động', 'description' => 'Mô hình, quy trình, kiểm soát rủi ro.', 'required' => true],
                ['code' => 'GP-03', 'name' => 'Chứng nhận an toàn thông tin', 'description' => 'Đạt tiêu chuẩn quy định.', 'required' => true],
            ],
        ],
        [
            'code' => 'TT-DK-KD',
            'name' => 'Đăng ký kinh doanh doanh nghiệp',
            'authority' => 'Sở Kế hoạch và Đầu tư',
            'subject' => 'Tổ chức, cá nhân thành lập doanh nghiệp',
            'conditions' => 'Có ngành nghề kinh doanh hợp pháp, địa chỉ trụ sở rõ ràng.',
            'steps' => [
                ['title' => 'Nộp hồ sơ', 'detail' => 'Qua Cổng thông tin quốc gia về đăng ký doanh nghiệp.'],
                ['title' => 'Nhận kết quả', 'detail' => 'Giấy chứng nhận đăng ký doanh nghiệp sau 03 ngày làm việc.'],
            ],
            'submission_methods' => ['ONLINE'],
            'processing_time' => '03 ngày làm việc',
            'fee' => 'Theo quy định',
            'result' => 'Giấy chứng nhận đăng ký doanh nghiệp',
            'portal_url' => 'https://dangkykinhdoanh.gov.vn',
            'legal_basis' => [
                ['provision_id' => null, 'citation' => 'Điều 17', 'document_number' => 'LUAT-DT-2018'],
            ],
            'activity_tags' => ['ECOMMERCE'],
            'obligation_codes' => ['R-CORP-01'],
            'version' => 1,
            'last_verified_at' => '2026-06-30 00:00:00',
            'requirements' => [
                ['code' => 'KK-01', 'name' => 'Đơn đăng ký', 'description' => 'Theo mẫu thông tư.', 'required' => true],
                ['code' => 'KK-02', 'name' => 'Điều lệ công ty', 'description' => 'Bản ký điện tử.', 'required' => true],
            ],
        ],
    ];
}
