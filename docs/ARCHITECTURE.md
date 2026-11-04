# Kiến trúc triển khai

Đọc `docs/SRS VietLegal Check – Phiên bản 1.1 (bản điều chỉnh).md` (SRS) và `DESIGN.md` trước file này.

## Sơ đồ

```
Trình duyệt ──HTTP──▶ Nginx :8080 ──▶ (tĩnh) frontend/dist (Vue 3 SPA)
                        │
                        └─fastcgi──▶ php-fpm (Laravel 12, modular monolith)
                                        │
                                        ├──▶ PostgreSQL 16 (+pgvector image, unaccent, pg_trgm)
                                        ├──▶ Redis (cache + queue)
                                        └──HTTP nội bộ (HMAC)──▶ Python FastAPI :8100
                                                                   │
                                                                   └──▶ Internet (qua HTTP) chỉ khi gọi nhà cung cấp AI
Laravel queue worker + scheduler chạy cùng image; crawler cô lập trong dịch vụ Python
(tài khoản không đặc quyền, không truy cập DB, nội dung trang là dữ liệu không đáng tin).
```

## Module Laravel

`app/Domain` chứa logic thuần (Rule Engine, phân loại) — không phụ thuộc HTTP/Eloquent ngoài việc
nhận dữ liệu vào. `app/Services` chứa dịch vụ ứng dụng (gọi dịch vụ Python, tạo PDF, xử lý file).
`app/Http/Controllers/Api/V1` chỉ validate, ủy quyền và đóng gói phản hồi.

## Lựa chọn kỹ thuật có lý do

| Quyết định | Lý do |
| --- | --- |
| SeaweedFS thay MinIO trong compose | Registry mirror hiện tại từ chối `minio/minio`; SeaweedFS cùng API S3, cùng driver Laravel, cấu hình `.env` không đổi. Đổi lại `minio/minio` được khi pull thành công (SRS 6.1). |
| `pg_trgm` + `unaccent` cho tìm kiếm; pgvector cài nhưng để dự phòng | MVP không cần embedding; tìm kiếm từ khoá không dấu đáp ứng FR-LAW-06/FR-AI-02 mà không thêm phụ thuộc mô hình. |
| TOTP tự viết (RFC 6238) | Phụ thuộc duy nhất là `hash_hmac`/`sodium`; tránh thêm gói chỉ để sinh 6 chữ số (FR-AUTH-04). |
| DomPDF + DejaVu Sans cho PDF tiếng Việt | SRS cho phép "trình render dựa trên Chromium không đầu" nếu DomPDF lỗi font; DejaVu hỗ trợ đầy đủ tiếng Việt, kiểm thử trong `tests/Feature/ReportGenerationTest.php`. |
| Token Sanctum (Bearer) thay vì cookie SPA | Tránh phải chia sẻ tên miền giữa dev server Vite và API; CSRF không áp dụng cho bearer (SRS 5.2 vẫn yêu cầu CSRF *khi* dùng cookie). |
| Dịch vụ Python không truy cập DB | Kỷ luật SRS 6.3/6.5: Laravel chọn nguồn (lọc hiệu lực `as_of_date`), Python chỉ xử lý dữ liệu được truyền. |

## Chạy

```
cp .env.docker.example .env        # tạo APP_KEY
docker compose up -d --build
docker compose exec app php artisan test
```

Frontend dev: `cd frontend && npm run dev` (proxy `/api` → `127.0.0.1:8080`).
