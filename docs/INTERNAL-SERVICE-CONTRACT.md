# Hợp đồng dịch vụ nội bộ (Laravel ⇄ Python)

SRS mục 6.4: Laravel ghi `background_jobs` rồi gọi API nội bộ của dịch vụ Python. Dịch vụ Python
không nhận kết nối từ Internet (không publish port trong `docker-compose.yml`).

## Xác thực hai chiều

Mọi request gửi tới dịch vụ Python phải có:

| Header | Giá trị |
| --- | --- |
| `X-VL-Timestamp` | Unix time (giây) tại thời điểm gửi |
| `X-VL-Signature` | `hex(hmac_sha256(AI_SERVICE_KEY, f"{timestamp}.{raw_body}"))` |

Dịch vụ trả `401` khi thiếu header, sai chữ ký, hoặc lệch giờ quá 300 giây.
`AI_SERVICE_KEY` được xoay vòng bằng biến môi trường (SRS 6.4).

## `GET /health`

`{"status":"ok","ai_provider":"none|openai-compatible","version":"1"}`

## `POST /internal/scan`

Bắt đầu một lần quét. Gọi đồng bộ trong worker (tối đa 280 giây).

```json
{
  "job_id": "uuid",
  "url": "https://shop.example.vn",
  "mode": "LIGHT",
  "criteria_version": 1,
  "limits": { "max_pages": 15, "max_bytes": 3000000, "timeout_seconds": 20 }
}
```

`mode` = `LIGHT` (chưa xác minh sở hữu, chỉ trang chủ + trang chính sách) hoặc `DEEP`
(đã xác minh sở hữu, ≤ `max_pages`).

Kết quả `200`:

```json
{
  "status": "COMPLETED",
  "blocked_reason": null,
  "final_url": "https://shop.example.vn/",
  "resolved_ip": "203.0.113.10",
  "robots_checked": true,
  "pages": [
    {
      "url": "https://shop.example.vn/chinh-sach-bao-mat",
      "http_status": 200,
      "title": "Chính sách bảo mật",
      "fetch_error": null,
      "content_hash": "sha256-hex",
      "snapshot_html": "<!doctype html>… (≤ 200 000 ký tự)",
      "signals": {
        "is_policy_page": true,
        "policy_kinds": ["PRIVACY"],
        "has_data_subject_rights": true,
        "has_processing_purpose": true,
        "has_controller_identity": true,
        "has_contact_channel": true,
        "collects_personal_data": true,
        "has_tax_code": false,
        "has_business_identity": true
      },
      "evidence": [
        { "kind": "PRIVACY_POLICY", "excerpt": "…", "selector": "h1" }
      ]
    }
  ],
  "site": {
    "https": true,
    "tls_valid": true,
    "security_headers": { "strict-transport-security": false, "content-security-policy": false, "x-content-type-options": true, "x-frame-options": false, "referrer-policy": false },
    "cookie_banner": false,
    "cookie_policy_found": false,
    "has_login_form": false,
    "links_checked": 24,
    "broken_links": ["https://shop.example.vn/old-page"]
  },
  "findings": [
    {
      "code": "SCAN_PRIVACY_POLICY_MISSING",
      "severity": "HIGH",
      "title": "Không tìm thấy chính sách bảo mật",
      "detail": "Không có trang hoặc liên kết chính sách bảo mật trong số trang đã quét.",
      "evidence_url": "https://shop.example.vn/"
    }
  ]
}
```

`status` ∈ `COMPLETED | BLOCKED | FAILED`. `BLOCKED` khi URL không an toàn (FR-SCAN-06, FR-SCAN-08);
`blocked_reason` ∈ `PRIVATE_ADDRESS | METADATA_ADDRESS | UNSUPPORTED_SCHEME | UNSUPPORTED_PORT |
DNS_REBINDING | REDIRECT_LIMIT | INVALID_URL`. Khi `BLOCKED` hoặc `FAILED`, `pages` và `findings` rỗng.
`findings` (`code`, `severity`, `title`, `detail`, `evidence_url`) do dịch vụ Python tính theo bộ tiêu chí
`criteria_version`; mọi phát hiện phải kèm `evidence_url` (FR-SCAN-05).

An toàn bắt buộc (SRS FR-SCAN-06, 07, 08, 09):

- Chỉ `http`/`https`, chỉ cổng 80/443.
- Phân giải DNS một lần, ghim IP cho kết nối (`sni_hostname` + `Host`), kiểm tra lại IP sau mỗi redirect,
  tối đa 5 redirect, tự xử lý redirect (không để thư viện tự làm).
- Chặn loopback, private, link-local, CGNAT, IPv6 ULA/link-local, multicast, `0.0.0.0/8`,
  `169.254.169.254` và tương đương IPv6 mapped.
- Tuân thủ `robots.txt`, `User-Agent: VietLegalCheckBot/1.0 (+https://vietlegal.local/bot)`.
- Không thực thi JavaScript, không đăng nhập, không byte-range, giới hạn dung lượng phản hồi và thời gian.
- Nội dung trang là dữ liệu không đáng tin; không có đường nào để nội dung trang điều khiển dịch vụ.

## `POST /internal/scan/{job_id}/cancel`

Đặt cờ huỷ (hợp tác). `{"cancelled": true}`.

## `POST /internal/ai/answer`

```json
{
  "question": "Tôi bán hàng online có phải thông báo website với Bộ Công Thương không?",
  "as_of_date": "2026-11-04",
  "stream": false,
  "sources": [
    {
      "id": "prov-uuid",
      "doc_number": "122/2025/QH15",
      "doc_title": "Luật Thương mại điện tử",
      "article": "Điều 12",
      "clause": "Khoản 2",
      "point": null,
      "content": "…nguyên văn điều khoản…",
      "effective_from": "2026-07-01",
      "official_url": "https://vbpl.moj.gov.vn/…"
    }
  ]
}
```

`as_of_date` do Laravel chọn; dịch vụ chỉ dùng `sources` được truyền vào, không truy cập cơ sở dữ liệu.

Kết quả `200` (`stream=false`):

```json
{
  "answer": "…tiếng Việt, có dẫn nguồn dạng [1], [2]…",
  "cited_source_ids": ["prov-uuid"],
  "refused": false,
  "refusal_reason": null,
  "model": "extractive|gpt-4o-mini",
  "categories": {
    "MANDATORY": ["…quy định bắt buộc…"],
    "GUIDANCE": ["…hướng dẫn tham khảo…"],
    "TECHNICAL": ["…khuyến nghị kỹ thuật…"],
    "EXPERT": ["…nhận định cần chuyên gia…"]
  }
}
```

Quy tắc bắt buộc (SRS FR-AI-02, 03, 05, 06, 07, 08):

- Không có `sources`, hoặc không nguồn nào liên quan tới câu hỏi ⇒ `refused: true`,
  `answer` là thông báo chưa đủ căn cứ bằng tiếng Việt, `cited_source_ids` rỗng. Không đoán.
- Mọi khẳng định phải gắn `[n]` tương ứng chỉ số nguồn trong danh sách được truyền; `cited_source_ids`
  chỉ chứa id thật đã dùng.
- Không tự tạo số hiệu văn bản. Không dùng nguồn có `effective_from` > `as_of_date` như quy định hiện hành
  (Laravel đã lọc, dịch vụ kiểm tra lại và loại).
- Bốn nhóm thông tin tách biệt như `categories`; nội dung cần chuyên gia nằm ở `EXPERT`.
- Nội dung `sources` và câu hỏi được đóng gói là dữ liệu trong prompt; chỉ thị nằm ở system prompt.
  Nếu `AI_PROVIDER_URL` trống ⇒ chế độ `extractive`: trả lời bằng cách trích dẫn nguyên văn điều khoản
  liên quan (chấm điểm từ khoá), vẫn tiếng Việt, vẫn `[n]`.

Khi `stream=true`: `text/event-stream`, mỗi dòng `data: {"type":"delta","text":"…"}`, kết thúc bằng
`data: {"type":"final","cited_source_ids":[],"refused":false,"model":"…","categories":{…}}`.

## `POST /internal/probe`

Kiểm tra trang chủ có chứa thẻ meta xác minh quyền sở hữu hay không (FR-SCAN-07).
Body: `{"url": "https://example.vn/", "expect": "meta:<token>"}`. Phản hồi `{"found": true|false}`.
Chỉ tải về tối đa 256 KB, áp dụng đúng các quy tắc an toàn của `/internal/scan`.
