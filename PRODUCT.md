# VietLegal Check — PRODUCT.md

## What this is

VietLegal Check là nền tảng SaaS giúp cá nhân và doanh nghiệp Việt Nam xác định **nghĩa vụ pháp lý
có khả năng áp dụng** cho website/ứng dụng của họ, kiểm tra các dấu hiệu công khai, chuẩn bị hồ sơ và
theo dõi thay đổi pháp luật. Sản phẩm **không** cấp phép, không chứng nhận, không thay thế luật sư.

Nguồn sự thật: `docs/SRS VietLegal Check – Phiên bản 1.1 (bản điều chỉnh).md` (gọi tắt: SRS).
Mọi hành vi, mã yêu cầu (FR-*, BR-*, AC-*) trong tài liệu này trỏ về SRS.

## Who uses it and on what

| Vai trò | Việc họ làm trong một ngày | Thiết bị / bối cảnh |
| --- | --- | --- |
| Chủ shop, founder, kế toán | Khai báo sản phẩm, đọc nghĩa vụ, chuẩn bị hồ sơ | Laptop văn phòng, 1366–1920 px; điện thoại khi cần |
| Nhân viên pháp chế / vận hành | Soát lại kết quả quét, tải tài liệu, chạy báo cáo | Desktop, nhiều tab, cần bảng dày |
| Chuyên gia pháp lý | Hàng đợi ca cần xác nhận, viết nhận xét | Desktop, đọc nhiều văn bản dài |
| Quản trị pháp lý / hệ thống | Nhập văn bản, soạn quy tắc, duyệt, xem audit log | Desktop, thao tác nhiều bước có rủi ro |

Ngôn ngữ giao diện: tiếng Việt. Người dùng **không** có nền tảng pháp lý phải hiểu được kết quả.

## Jobs to be done (MVP, SRS 15.1)

1. Khai báo sản phẩm và nhận các **thẻ hoạt động** (ECOM_SELLING, PAYMENT, PERSONAL_DATA, …) kèm lý do.
2. Nhận danh sách nghĩa vụ **có khả năng áp dụng**, mỗi nghĩa vụ có căn cứ điều khoản, hiệu lực tại
   `as_of_date`, và vết giải thích TRUE/FALSE/UNKNOWN.
3. Xem hướng dẫn thủ tục hành chính thật (cơ quan, hồ sơ, trình tự, link dịch vụ công) và checklist hồ sơ.
4. Quét website công khai, thấy bằng chứng (URL, thời điểm, trích đoạn, ảnh chụp) — không kết luận
   chỉ vì có/không có một trang.
5. Hỏi trợ lý AI và nhận câu trả lời **có trích dẫn**, hoặc bị từ chối khi thiếu căn cứ.
6. Xuất báo cáo PDF tiếng Việt bất biến (hash, `as_of_date`, phiên bản bộ quy tắc).
7. Quản trị văn bản/quy tắc với phê duyệt hai người, test bắt buộc trước phát hành.

## Non-goals (SRS 1.4, 15.2)

Cấp phép/chứng nhận; nộp hồ sơ thay khách; ký tài liệu; kết luận thay luật sư; cam kết "chắc chắn hợp pháp";
truy cập khu vực nội bộ của site được kiểm tra. Ngoài MVP: OCR, email/reminder (P1), loại hình ngoài TMĐT (P2).

## Product truth that shapes the interface

- **Bộ máy quy tắc quyết định, AI chỉ giải thích** (SRS 6.3). Giao diện không được trình bày nội dung AI
  như kết luận pháp lý: nội dung AI luôn có nhãn "do AI tạo" và tách khỏi nghĩa vụ do quy tắc sinh ra.
- **Hai trường trạng thái**: `applicability` và `fulfillment` là hai trục độc lập (SRS 4.7). Một nghĩa vụ
  vừa "có khả năng áp dụng" vừa "đã đính kèm bằng chứng" — UI phải hiển thị được cả hai cùng lúc.
- **Từ ngữ trung tính** (SRS 9.3): "có khả năng áp dụng", "chưa tìm thấy bằng chứng", "cần xác minh".
  Không dùng "vi phạm", "hợp pháp", "đạt chuẩn" làm nhãn kết quả.
- **Mọi kết quả truy vết được**: căn cứ điều khoản, dữ kiện, phiên bản quy tắc, `as_of_date`.
- **Dữ liệu cũ phải nói rõ nó cũ** (quá hạn xác minh → cảnh báo, không trình bày như thông tin hiện hành).
- Tuyên bố giới hạn (FR-REPORT-05) xuất hiện ở đầu trang kết quả và trong báo cáo.

## Constraints

- WCAG 2.1 AA cho màn hình chính; responsive từ 360 px; điều hướng bàn phím; không mất dữ liệu
  giữa chừng wizard khi mất mạng (nháp lưu sau mỗi bước).
- Tiếng Việt có dấu ở mọi nơi, kể cả PDF.
- Mỗi trạng thái có **biểu tượng + chữ**, không chỉ màu (SRS 9.3).
- Tác vụ dài (`202 + job_id`): UI có tiến độ, có thể rời trang, có thể huỷ.

## What we will not claim

Không dùng ảnh/checkmark "đã được duyệt", không huy hiệu "compliant", không số liệu bịa. Mọi giá trị
minh hoạ trong bản dựng này là dữ liệu mẫu và được ghi rõ là dữ liệu mẫu (SRS 14: cần chuyên gia xác minh
nguồn chính thức trước khi dùng làm căn cứ).

## Assumptions (recorded because the SRS leaves them open)

1. MVP chạy trên Docker Compose; dev/test trên một máy. Không có quyết định hạ tầng production.
2. Nhà cung cấp AI là tuỳ chọn: khi chưa cấu hình, trợ lý chạy ở chế độ trích dẫn nguyên văn (extractive)
   thay vì bịa câu trả lời.
3. Dữ liệu pháp lý và quy tắc trong `database/seeders` là **mẫu kỹ thuật**, đánh dấu `last_verified_at`
   và cảnh báo cần xác minh; không phải kết luận pháp lý.
4. 2FA bắt buộc cho ba vai trò quản trị (SRS 15.3 điều kiện 15) và tuỳ chọn cho người dùng.
