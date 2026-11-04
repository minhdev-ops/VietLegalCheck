# VietLegal Check

Nền tảng đánh giá nghĩa vụ pháp lý cho website/ứng dụng theo pháp luật Việt Nam
(SRS "VietLegal Check – Phiên bản 1.1"). Kiến trúc: Laravel 12 API (modular monolith),
Vue 3 + TypeScript + Tailwind, PostgreSQL (+pg_trgm/unaccent), Redis, dịch vụ Python
FastAPI nội bộ, SeaweedFS (S3), Docker Compose + Nginx.

## Chạy bằng Docker Compose

Yêu cầu: Docker ≥ 24, ~4 GB RAM trống. Registry mirror có thể không có `minio/minio`
nên dùng SeaweedFS làm S3 gateway (thay thế tương thích S3).

```bash
docker compose up -d --build
```

Sau ~3–5 phút (build lần đầu), truy cập: <http://localhost:8080>

Dữ liệu khởi tạo (migration + seed) tự chạy khi `RUN_MIGRATIONS=true` (mặc định).

### Tài khoảndemo

| Vai trò | Email | Mật khẩu |
| --- | --- | --- |
| Quản trị hệ thống | admin@vietlegal.local | admin-password-change-me |
| Quản trị pháp lý | legal.admin@vietlegal.local | legal-admin-password-change-me |
| Chuyên gia pháp lý | expert@vietlegal.local | expert-password-change-me |

Tất cả đều bật xác thực hai bước (TOTP); đặt mật khẩu mới trước khi đưa lên production.
Mã 2FA dùng chung cho demo: `JBSWY3DPEHPK3PXP` (sinh mã bằng app TOTP bất kỳ).

### Xác minh AC-19 (chọn phiên bản quy tắc theo ngày hiệu lực)

```bash
# Đăng nhập lấy token
TOKEN=$(curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@vietlegal.local","password":"admin-password-change-me","two_factor_code":"MÃ_TOTP_HIỆN_TẠI"}' \
  | python3 -c 'import json,sys; print(json.load(sys.stdin)["token"])')

# Tạo dự án + hồ sơ rồi chạy đánh giá với hai ngày khác nhau:
# as_of_date=2026-06-30 → R-DATA-01 v1 (Nghị định 13/2023)
# as_of_date=2026-07-01 → R-DATA-01 v2 (Luật Bảo vệ dữ liệu cá nhân 2025)
```

`rule_set_snapshot` của mỗi kết quả chứa `{rule_code, version}`; findings mang
`legal_basis` (citation + document_number + effective_from + official_url) và
`obligation_json`.

## Kiểm thử

### Backend (PHP, chạy trong container `app`)

```bash
docker compose exec app ./vendor/bin/phpunit
```

Suite SQLite trong bộ nhớ (22 test: Unit 14, Feature 8). `tests/TestCase` ép
sqlite/:memory: + queue sync, migrate qua `migrate:fresh --force` — không đụng
PostgreSQL dùng chung.

Lý do cấu hình này nằm trong comment của `backend/tests/TestCase.php`: biến môi trường
container vượt qua `<env>` của `phpunit.xml`, nên trait `RefreshDatabase` mặc định
bị `ConfirmableTrait` hủy lệnh migrate vì APP_ENV=production.

### Python AI service

```bash
cd services/ai && python3 -m pytest tests -q
```

15 test: HMAC accept/reject/stale, SSRF chặn private/loopback/metadata, trích dẫn
phải có nguồn, seam transport offline.

### Frontend

```bash
cd frontend && npm install && npx vue-tsc --noEmit -p tsconfig.app.json && npm run build
```

type-check + build (`dist/` do Nginx serve).

## Cấu trúc thư mục

```
backend/        Laravel 12 API — app/Domain (RuleEngine, Enums, ...), app/Http/Controllers/Api/V1
frontend/       Vue 3 SPA — views/public, views/admin, views/app
services/ai/    FastAPI nội bộ — trích dẫn pháp lý + SSRF guard (HMAC)
docker/         Dockerfile (app), nginx conf, entrypoint
docs/           SRS, hợp đồng dịch vụ nội bộ (INTERNAL-SERVICE-CONTRACT.md)
DESIGN.md       Ngôn ngữ thiết kế (LOCKED): bảng màu giấy "Công báo", OKLCH, Be Vietnam Pro + Source Serif 4
PRODUCT.md      Định vị sản phẩm
```

## Bảng màu nền giấy

`DESIGN.md` khóa hệ màu giấycream (`oklch(0.98 0.008 75)`) — chủ ý thẩm mỹ
"Công báo" chứ không phải mặc định AI. Các token cảnh báo/OK đã được chỉnh để đạt
WCAG AA trên nền này.

## Ghi chú vận hành

- `migrate:fresh --seed` sẽ xóa dữ liệu smoke; chạy lại `/tmp/smoke.py` (nếu cần).
- Worker `queue` xử lý assessment async (HTTP POST trả 202 + `job_id`).
- Xoá `frontend/dist` ngoài host khi Nginx bind-mount: phải
  `docker compose up -d --force-recreate nginx` để mount khớp lại.
- Nhật ký backend: `docker compose exec app tail -f storage/logs/laravel.log`.
