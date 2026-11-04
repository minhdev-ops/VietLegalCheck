# API hợp đồng (SRS 8)

REST, tiền tố `/api/v1`. Xác thực bằng Bearer token (Sanctum). Quyền trong cột là vai trò tối thiểu;
mọi endpoint theo ID tài nguyên khách hàng còn kiểm tra quyền sở hữu tổ chức (chống IDOR, SRS 5.2).

Quy ước (SRS 8.1): tác vụ dài trả `202 Accepted` kèm `job_id`; client poll `GET /jobs/{id}`.
Request tạo tác vụ nhận header `Idempotency-Key`. Lỗi: `{ "error": { "code", "message", "details", "request_id" } }`.
Phân trang: `page`, `per_page` (mặc định 20, tối đa 100). Mọi endpoint pháp luật nhận `as_of_date`.

Danh sách endpoint đầy đủ nằm ở `backend/routes/api.php` (nguồn sự thật khi viết code). File này giữ
hợp đồng dạng đọc cho frontend; thay đổi phải cập nhật cả hai.

## Thông báo (SRS 8.9)

| Method | Endpoint | Chức năng | Quyền |
| --- | --- | --- | --- |
| GET | /notifications | Danh sách + `unread` | User |
| PATCH | /notifications/{id}/read | Đánh dấu đã đọc | User |
| POST | /notifications/read-all | Đánh dấu tất cả đã đọc | User |
| GET | /jobs/{id} | Trạng thái tác vụ | Người tạo |

## Hành vi chung quan trọng

- `POST /projects/{id}/assessments` → 202 + `{ assessment_id, job_id }`; poll `/assessments/{id}`.
- Tạo tác vụ lặp lại với cùng `Idempotency-Key` trả lại tác vụ cũ, không tạo bản sao (AC-25).
- `GET /legal-documents` public; `/admin/legal-documents` yêu cầu Legal Admin.
- AI: `POST /ai/conversations/{id}/messages` trả `text/event-stream` khi body có `stream: true`.
