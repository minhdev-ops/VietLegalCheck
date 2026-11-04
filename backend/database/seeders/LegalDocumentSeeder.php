<?php

namespace Database\Seeders;

use App\Domain\Enums\LegalStatus;
use App\Models\LegalDocument;
use App\Models\LegalProvision;
use Illuminate\Database\Seeder;

/**
 * Văn bản pháp luật mẫu (SRS §14). Dữ liệu là MẪU: `source_note` ghi rõ cần kiểm chứng
 * cán bộ theo văn bản gốc trước khi đưa vào sản xuất (SRS §14 caveat).
 */
class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::DOCUMENTS as $document) {
            $provisions = $document['provisions'];
            unset($document['provisions']);

            /** @var LegalDocument $record */
            $record = LegalDocument::updateOrCreate(['doc_number' => $document['doc_number']], $document);

            foreach ($provisions as $provision) {
                LegalProvision::updateOrCreate(
                    [
                        'legal_document_id' => $record->id,
                        'article' => $provision['article'] ?? null,
                        'clause' => $provision['clause'] ?? null,
                        'point' => $provision['point'] ?? null,
                    ],
                    array_merge($provision, ['legal_document_id' => $record->id]),
                );
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function documents(): array
    {
        return self::DOCUMENTS;
    }

    private const DOCUMENTS = [
        [
            'doc_number' => 'LUAT-BCQT-2018',
            'title' => 'Luật An ninh mạng 2018',
            'doc_type' => 'LUAT',
            'issuer' => 'Quốc hội',
            'issued_at' => '2018-06-12',
            'effective_from' => '2019-01-01',
            'effective_to' => null,
            'status' => 'IN_EFFECT',
            'official_url' => 'https://vbpl.vn/bo-ky-luat/phap-luat/page/luat-an-ninh-mang-2018-2gy.aspx',
            'source_type' => 'PRIMARY',
            'source_note' => 'Dữ liệu mẫu: kiểm chứng lại với văn bản gốc trên Cổng thông tin điện tử Chính phủ.',
            'last_verified_at' => '2026-06-30 00:00:00',
            'provisions' => [
                [
                    'article' => 'Điều 25',
                    'clause' => 'Khoản 1',
                    'heading' => 'Trách nhiệm của doanh nghiệp cung cấp dịch vụ',
                    'content' => 'Doanh nghiệp cung cấp dịch vụ trên không gian mạng có trách nhiệm thực hiện chế độ bảo mật, bảo vệ dữ liệu cá nhân của khách hàng và phải cung cấp cho khách hàng biện pháp bảo mật.',
                    'effective_from' => '2019-01-01',
                    'effective_to' => null,
                    'status' => 'EFFECTIVE',
                    'position' => 1,
                ],
                [
                    'article' => 'Điều 26',
                    'clause' => 'Khoản 2',
                    'heading' => 'Ngăn chặn thông tin xấu, độc hại',
                    'content' => 'Doanh nghiệp cung cấp dịch vụ phải có biện pháp ngăn chặn, loại bỏ thông tin xấu, độc hại và thông tin vi phạm pháp luật phát tán trên dịch vụ của mình.',
                    'effective_from' => '2019-01-01',
                    'effective_to' => null,
                    'status' => 'EFFECTIVE',
                    'position' => 2,
                ],
                [
                    'article' => 'Điều 17',
                    'clause' => 'Khoản 3',
                    'heading' => 'Liên hệ để phối hợp xử lý',
                    'content' => 'Cơ quan, tổ chức, cá nhân quản lý hệ thống hệ thống thông tin phải có thông tin liên hệ phục vụ việc phối hợp xử lý sự cố an ninh mạng.',
                    'effective_from' => '2019-01-01',
                    'effective_to' => null,
                    'status' => 'EFFECTIVE',
                    'position' => 3,
                ],
            ],
        ],
        [
            'doc_number' => 'LUAT-BVDS-2023',
            'title' => 'Luật Bảo vệ dữ liệu cá nhân 2023',
            'doc_type' => 'LUAT',
            'issuer' => 'Quốc hội',
            'issued_at' => '2023-06-28',
            'effective_from' => '2024-07-01',
            'effective_to' => null,
            'status' => 'IN_EFFECT',
            'official_url' => 'https://vbpl.vn/phap-luat-cua-toan-quan/page/luat-bao-ve-du-lieu-ca-nhan-2023.aspx',
            'source_type' => 'PRIMARY',
            'source_note' => 'Dữ liệu mẫu: kiểm chứng lại với văn bản gốc.',
            'last_verified_at' => '2026-06-30 00:00:00',
            'provisions' => [
                [
                    'article' => 'Điều 7',
                    'heading' => 'Nguyên tắc xử lý dữ liệu cá nhân',
                    'content' => 'Việc xử lý dữ liệu cá nhân phải tuân thủ các nguyên tắc: hợp pháp, minh bạch, đúng mục đích, hạn chế tối đa tác động và đảm bảo quyền của chủ thể dữ liệu.',
                    'effective_from' => '2024-07-01',
                    'effective_to' => null,
                    'status' => 'EFFECTIVE',
                    'position' => 1,
                ],
                [
                    'article' => 'Điều 11',
                    'clause' => 'Khoản 1',
                    'heading' => 'Đồng ý của chủ thể dữ liệu',
                    'content' => 'Việc xử lý dữ liệu cá nhân chỉ được thực hiện khi có sự đồng ý của chủ thể dữ liệu, trừ các trường hợp pháp luật quy định khác. Đồng ý phải được tự nguyện, rõ ràng và có thể rút lại.',
                    'effective_from' => '2024-07-01',
                    'effective_to' => null,
                    'status' => 'EFFECTIVE',
                    'position' => 2,
                ],
                [
                    'article' => 'Điều 26',
                    'heading' => 'Thông báo cho chủ thể dữ liệu',
                    'content' => 'Người xử lý dữ liệu phải thông báo cho chủ thể dữ liệu về mục đích xử lý, loại dữ liệu, đối tượng được tiếp cận và quyền của chủ thể dữ liệu trước khi thu thập.',
                    'effective_from' => '2024-07-01',
                    'effective_to' => null,
                    'status' => 'EFFECTIVE',
                    'position' => 3,
                ],
                [
                    'article' => 'Điều 34',
                    'heading' => 'Đánh giá tác động bảo vệ dữ liệu cá nhân',
                    'content' => 'Người xử lý dữ liệu phải thực hiện đánh giá tác động bảo vệ dữ liệu cá nhân trước khi xử lý dữ liệu thuộc loại dữ liệu nhạy cảm hoặc quy mô lớn.',
                    'effective_from' => '2024-07-01',
                    'effective_to' => null,
                    'status' => 'EFFECTIVE',
                    'position' => 4,
                ],
            ],
        ],
        [
            'doc_number' => 'ND-CP-13-2023',
            'title' => 'Nghị định 13/2023/NĐ-CP bảo vệ dữ liệu cá nhân',
            'doc_type' => 'NGHI_DINH',
            'issuer' => 'Chính phủ',
            'issued_at' => '2023-04-17',
            'effective_from' => '2023-07-01',
            'effective_to' => '2024-06-30',
            'status' => 'EXPIRED',
            'official_url' => 'https://vbpl.vn/phap-luat-cua-toan-quan/page/nghi-dinh-13-2023-nd-cp-bao-ve-du-lieu-ca-nhan.aspx',
            'source_type' => 'PRIMARY',
            'source_note' => 'Văn bản mẫu đã hết hiệu lực, được thay thế bởi Luật BVDC 2023 (để demo as_of_date).',
            'last_verified_at' => '2026-06-30 00:00:00',
            'provisions' => [
                [
                    'article' => 'Điều 5',
                    'heading' => 'Nguyên tắc chung',
                    'content' => 'Bảo vệ dữ liệu cá nhân phải tuân thủ nguyên tắc hợp pháp, minh bạch, đúng mục đích và trách nhiệm giải trình.',
                    'effective_from' => '2023-07-01',
                    'effective_to' => '2024-06-30',
                    'status' => 'EXPIRED',
                    'position' => 1,
                ],
            ],
        ],
        [
            'doc_number' => 'TT-BTTTT-03-2023',
            'title' => 'Thông tư 03/2023/TT-BTTTT về quản lý nội dung thông tin trên dịch vụ mạng',
            'doc_type' => 'THONG_TU',
            'issuer' => 'Bộ Thông tin và Truyền thông',
            'issued_at' => '2023-04-27',
            'effective_from' => '2023-06-01',
            'effective_to' => null,
            'status' => 'IN_EFFECT',
            'official_url' => 'https://vbpl.vn/phap-luat-cua-toan-quan/page/thong-tu-03-2023-tt-btttt.aspx',
            'source_type' => 'PRIMARY',
            'source_note' => 'Dữ liệu mẫu: kiểm chứng lại với văn bản gốc.',
            'last_verified_at' => '2026-06-30 00:00:00',
            'provisions' => [
                [
                    'article' => 'Điều 11',
                    'clause' => 'Khoản 1',
                    'heading' => 'Đăng ký cung cấp dịch vụ',
                    'content' => 'Doanh nghiệp cung cấp dịch vụ mạng xã hội, dịch vụ lưu trữ mạng phải đăng ký và cung cấp đầy đủ thông tin về doanh nghiệp theo quy định.',
                    'effective_from' => '2023-06-01',
                    'effective_to' => null,
                    'status' => 'EFFECTIVE',
                    'position' => 1,
                ],
                [
                    'article' => 'Điều 22',
                    'heading' => 'Kiểm duyệt nội dung',
                    'content' => 'Nhà cung cấp dịch vụ phải thực hiện kiểm duyệt nội dung thông tin do người dùng phát tán theo yêu cầu của cơ quan nhà nước có thẩm quyền.',
                    'effective_from' => '2023-06-01',
                    'effective_to' => null,
                    'status' => 'EFFECTIVE',
                    'position' => 2,
                ],
            ],
        ],
        [
            'doc_number' => 'LUAT-TMDT-2023',
            'title' => 'Luật Giao dịch điện tử 2023',
            'doc_type' => 'LUAT',
            'issuer' => 'Quốc hội',
            'issued_at' => '2023-06-23',
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'status' => 'IN_EFFECT',
            'official_url' => 'https://vbpl.vn/phap-luat-cua-toan-quan/page/luat-giao-dich-dien-tu-2023.aspx',
            'source_type' => 'PRIMARY',
            'source_note' => 'Dữ liệu mẫu: kiểm chứng lại với văn bản gốc.',
            'last_verified_at' => '2026-06-30 00:00:00',
            'provisions' => [
                [
                    'article' => 'Điều 8',
                    'heading' => 'Giá trị pháp lý của thông điệp dữ liệu',
                    'content' => 'Thông điệp dữ liệu có giá trị pháp lý khi đáp ứng điều kiện về toàn vẹn, có thể truy cập và sử dụng được khi cần thiết.',
                    'effective_from' => '2024-01-01',
                    'effective_to' => null,
                    'status' => 'EFFECTIVE',
                    'position' => 1,
                ],
            ],
        ],
        [
            'doc_number' => 'LUAT-KT-2023',
            'title' => 'Luật Thương mại điện tử 2023',
            'doc_type' => 'LUAT',
            'issuer' => 'Quốc hội',
            'issued_at' => '2023-11-27',
            'effective_from' => '2024-07-01',
            'effective_to' => null,
            'status' => 'IN_EFFECT',
            'official_url' => 'https://vbpl.vn/phap-luat-cua-toan-quan/page/luat-thuong-mai-dien-tu-2023.aspx',
            'source_type' => 'PRIMARY',
            'source_note' => 'Dữ liệu mẫu: kiểm chứng lại với văn bản gốc.',
            'last_verified_at' => '2026-06-30 00:00:00',
            'provisions' => [],
        ],
        [
            'doc_number' => 'TT-BTTTT-07-2023',
            'title' => 'Thông tư 07/2023/TT-BTTTT về an toàn thao tác quản lý tên miền',
            'doc_type' => 'THONG_TU',
            'issuer' => 'Bộ Thông tin và Truyền thông',
            'issued_at' => '2023-06-30',
            'effective_from' => '2023-08-01',
            'effective_to' => null,
            'status' => 'IN_EFFECT',
            'official_url' => 'https://vbpl.vn/phap-luat-cua-toan-quan/page/thong-tu-07-2023-tt-btttt.aspx',
            'source_type' => 'PRIMARY',
            'source_note' => 'Dữ liệu mẫu: kiểm chứng lại với văn bản gốc.',
            'last_verified_at' => '2026-06-30 00:00:00',
            'provisions' => [],
        ],
        [
            'doc_number' => 'ND-CP-53-2022',
            'title' => 'Nghị định 53/2022/NĐ-CP về an toàn thông tin mạng',
            'doc_type' => 'NGHI_DINH',
            'issuer' => 'Chính phủ',
            'issued_at' => '2022-05-08',
            'effective_from' => '2022-07-01',
            'effective_to' => null,
            'status' => 'IN_EFFECT',
            'official_url' => 'https://vbpl.vn/phap-luat-cua-toan-quan/page/nghi-dinh-53-2022-nd-cp.aspx',
            'source_type' => 'PRIMARY',
            'source_note' => 'Dữ liệu mẫu: kiểm chứng lại với văn bản gốc.',
            'last_verified_at' => '2026-06-30 00:00:00',
            'provisions' => [
                [
                    'article' => 'Điều 11',
                    'heading' => 'Định kỳ đánh giá an toàn thông tin',
                    'content' => 'Doanh nghiệp cung cấp dịch vụ trên không gian mạng phải định kỳ đánh giá an toàn thông tin mạng và báo cáo kết quả cho cơ quan nhà nước có thẩm quyền.',
                    'effective_from' => '2022-07-01',
                    'effective_to' => null,
                    'status' => 'EFFECTIVE',
                    'position' => 1,
                ],
            ],
        ],
        [
            'doc_number' => 'LUAT-DT-2018',
            'title' => 'Luật Doanh nghiệp 2020',
            'doc_type' => 'LUAT',
            'issuer' => 'Quốc hội',
            'issued_at' => '2020-06-17',
            'effective_from' => '2021-01-01',
            'effective_to' => null,
            'status' => 'IN_EFFECT',
            'official_url' => 'https://vbpl.vn/phap-luat-cua-toan-quan/page/luat-doanh-nghiep-2020.aspx',
            'source_type' => 'SECONDARY',
            'source_note' => 'Dữ liệu mẫu: kiểm chứng lại với văn bản gốc.',
            'last_verified_at' => '2025-01-01 00:00:00',
            'provisions' => [
                [
                    'article' => 'Điều 17',
                    'heading' => 'Nghĩa vụ của doanh nghiệp',
                    'content' => 'Doanh nghiệp phải thực hiện nghĩa vụ đăng ký kinh doanh, kê khai thuế, bảo hiểm xã hội và các nghĩa vụ tài chính khác theo quy định pháp luật.',
                    'effective_from' => '2021-01-01',
                    'effective_to' => null,
                    'status' => 'EFFECTIVE',
                    'position' => 1,
                ],
            ],
        ],
        [
            'doc_number' => 'TT-BNN-09-2023',
            'title' => 'Thông tư 09/2023/TT-BNN về dịch vụ trung gian thương mại điện tử',
            'doc_type' => 'THONG_TU',
            'issuer' => 'Bộ Nông nghiệp và Phát triển nông thôn',
            'issued_at' => '2023-08-30',
            'effective_from' => '2023-10-01',
            'effective_to' => null,
            'status' => 'IN_EFFECT',
            'official_url' => 'https://vbpl.vn/phap-luat-cua-toan-quan/page/thong-tu-09-2023-tt-bnn.aspx',
            'source_type' => 'PRIMARY',
            'source_note' => 'Dữ liệu mẫu: kiểm chứng lại với văn bản gốc.',
            'last_verified_at' => '2025-06-01 00:00:00',
            'provisions' => [],
        ],
        [
            'doc_number' => 'ND-CP-14-2025',
            'title' => 'Nghị định 14/2025/NĐ-CP quy định chi tiết Luật An toàn thông tin mạng',
            'doc_type' => 'NGHI_DINH',
            'issuer' => 'Chính phủ',
            'issued_at' => '2025-10-25',
            'effective_from' => '2025-12-01',
            'effective_to' => null,
            'status' => 'IN_EFFECT',
            'official_url' => 'https://vbpl.vn/phap-luat-cua-toan-quan/page/nghi-dinh-14-2025-nd-cp.aspx',
            'source_type' => 'PRIMARY',
            'source_note' => 'Văn bản mới: chưa được kiểm chứng (demonstrates BR-08 stale marking).',
            'last_verified_at' => null,
            'provisions' => [
                [
                    'article' => 'Điều 12',
                    'heading' => 'Đăng ký hệ thống thông tin quan trọng',
                    'content' => 'Tổ chức quản lý hệ thống thông tin quan trọng quốc gia phải đăng ký với cơ quan chuyên trách về an toàn thông tin mạng.',
                    'effective_from' => '2025-12-01',
                    'effective_to' => null,
                    'status' => 'EFFECTIVE',
                    'position' => 1,
                ],
            ],
        ],
        [
            'doc_number' => 'TT-BTTTT-15-2020',
            'title' => 'Thông tư 15/2020/TT-BTTTT về quản lý số tập trung',
            'doc_type' => 'THONG_TU',
            'issuer' => 'Bộ Thông tin và Truyền thông',
            'issued_at' => '2020-09-30',
            'effective_from' => '2020-11-01',
            'effective_to' => null,
            'status' => 'IN_EFFECT',
            'official_url' => 'https://vbpl.vn/phap-luat-cua-toan-quan/page/thong-tu-15-2020-tt-btttt.aspx',
            'source_type' => 'PRIMARY',
            'source_note' => 'Dữ liệu mẫu: kiểm chứng lại với văn bản gốc.',
            'last_verified_at' => '2026-06-30 00:00:00',
            'provisions' => [],
        ],
        [
            'doc_number' => 'LUAT-VL-2019',
            'title' => 'Luật Lao động 2019',
            'doc_type' => 'LUAT',
            'issuer' => 'Quốc hội',
            'issued_at' => '2019-06-18',
            'effective_from' => '2021-01-01',
            'effective_to' => null,
            'status' => 'IN_EFFECT',
            'official_url' => 'https://vbpl.vn/phap-luat-cua-toan-quan/page/luat-lao-dong-2019.aspx',
            'source_type' => 'SECONDARY',
            'source_note' => 'Dữ liệu mẫu: kiểm chứng lại với văn bản gốc.',
            'last_verified_at' => '2026-06-30 00:00:00',
            'provisions' => [],
        ],
    ];
}
