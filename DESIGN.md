# DESIGN.md — ngôn ngữ thiết kế (locked)

Đăng ký: **product**. Xây trên nền tảng không dùng framework component có sẵn ngoài Tailwind +
`reka-ui` (primitive), component lớp vật dụng (`App/Components/ui/*`) viết quanh gu `shadcn-vue`
đúng như SRS 6.1 yêu cầu. **Không** thay thế容貌 bằng thư viện UI khác.

## Thiết kế đọc (Design Read)

"Cầm trong tay một tờ Công báo đã được đánh dấu: giấy mỏng, mực đậm, lề phải, số điều khoản in dứt khoát —
ta muốn công cụ pháp lý có sự tin cậy yên tĩnh của một văn bản hành chính, không phải vẻ hào nhoáng của SaaS."

Hướng thẩm mỹ đã chốt: **"Công báo" (Official Gazette)** — sổ đăng ký pháp lý. Bề mặt giấy ấm,
đường kẻ tóc, chữ số ở cột (tabular numerals), số điều khoản là chip nhãn, sắc đỏ "con dấu" chỉ
xuất hiện ở trích dẫn pháp lý và mức nghiêm trọng. Bù lại: mọi thông tin dày đặc vẫn thoáng vì
khoảng trắng lớn giữa nhóm và hairline rõ ràng.

Kiểm tra mặc định (counterfactual): hướng này được chọn vì đối tượng là văn bản pháp luật Việt Nam và
cảm giác "đăng ký công" — không phải câu trả lời mặc định cho mọi dashboard. Nó phản ứng đúng với brief.

## Bảng token (OKLCH, Wagtail-neutral)

Sắc độ sáng (mặc định):

```css
:root {
  --paper:         oklch(0.978 0.006 90);
  --surface:       oklch(1 0 0);
  --surface-2:     oklch(0.952 0.008 90);
  --surface-3:     oklch(0.918 0.010 90);
  --line:          oklch(0.890 0.008 90);
  --line-strong:   oklch(0.800 0.010 90);
  --ink:           oklch(0.245 0.019 265);
  --ink-muted:     oklch(0.490 0.016 265);
  --ink-faint:     oklch(0.620 0.013 265);
  --accent:        oklch(0.470 0.095 250);   /* mực: navigation, tương tác */
  --accent-strong: oklch(0.390 0.110 250);
  --seal:          oklch(0.500 0.160 27);    /* trích dẫn pháp lý, CRITICAL */
  --ok:            oklch(0.520 0.100 152);
  --caution:       oklch(0.620 0.130 75);
  --info:          oklch(0.550 0.070 235);
  --expert:        oklch(0.500 0.100 305);   /* cần chuyên gia: khác hẳn đỏ/xanh */
  --radius-sm: 6px; --radius-md: 10px; --radius-lg: 14px; --radius-pill: 999px;
  --shadow-1: 0 1px 2px oklch(0.245 0.019 265 / 0.06);
  --shadow-2: 0 2px 4px oklch(0.245 0.019 265 / 0.05), 0 10px 24px oklch(0.245 0.019 265 / 0.07);
  --measure: 68ch;
}
```

Tối (`prefers-color-scheme: dark`), giữ nguyên bản sắc (giấy tối mực sáng):

```css
@media (prefers-color-scheme: dark) {
  :root {
    --paper: oklch(0.205 0.012 265); --surface: oklch(0.245 0.012 265);
    --surface-2: oklch(0.280 0.012 265); --surface-3: oklch(0.330 0.012 265);
    --line: oklch(0.330 0.012 265); --line-strong: oklch(0.420 0.014 265);
    --ink: oklch(0.965 0.006 90); --ink-muted: oklch(0.735 0.010 265); --ink-faint: oklch(0.600 0.012 265);
    --accent: oklch(0.700 0.090 250); --accent-strong: oklch(0.780 0.080 250);
    --seal: oklch(0.680 0.130 27); --ok: oklch(0.700 0.090 152);
    --caution: oklch(0.750 0.100 75); --info: oklch(0.720 0.060 235); --expert: oklch(0.720 0.080 305);
  }
}
```

Quy tắc màu:

- 60/30/10: giấy + các bề mặt trung tính là 60, mực ink là 30, màu sắc (mực xanh-accent) ≤10.
- Sắc **seal** chỉ dùng cho: chip trích dẫn điều khoản, dấu chấm trích dẫn,CRITICAL, logo con dấu.
  Không bao giờ cho nút chính. Không bao giờ làm nền khối lớn.
- `--expert` (tím) dành riêng cho "cần chuyên gia"; `--info` cho NEED_MORE_INFO; `--caution` cho
  APPLICABLE/warning. Mỗi trạng thái **có icon chữ + glyph**, màu chỉ phụ (SRS 9.3).
- Text trên nền màu: luôn `--ink` hoặc tint từ cùng sắc độ, không bao giờ xám phẳng.

## Type

| Vai trò | Font | Size điển hình | Tracking | Leading |
| --- | --- | --- | --- | --- |
| Display / H1 trang | Be Vietnam Pro 600 | clamp(1.5rem, 3vw, 2rem) | -0.02em | 1.15 |
| H2 mục | Be Vietnam Pro 600 | 1.125rem | -0.01em | 1.3 |
| H3 / tiêu đề card | Be Vietnam Pro 600 | 1rem | -0.005em | 1.35 |
| Body | Be Vietnam Pro 400 | 0.9375rem (15px) | 0 | 1.55 |
| Nhãn bảng, số liệu | Be Vietnam Pro 500, `font-variant-numeric: tabular-nums` | 0.8125rem | 0 | 1.4 |
| Micro (eyebrow bị cấm) — chỉ cho nhãn cột/chip | Be Vietnam Pro 600, uppercase, 0.6875rem | +0.06em | 1.2 |
| Trích dẫn pháp lý / văn bản dài | Source Serif 4 (400, italic 400) | 0.9375rem | 0 | 1.65 |

- Không bao giờ dùng system font làm giọng display. Không dùng gradient text.
- Tự host `Be Vietnam Pro` (400/500/600/700) + `Source Serif 4` (400, 400i, 600) tại
  `frontend/public/fonts`. Không tải font từ CDN khi chạy.
- Body measure ≤ `--measure` (68ch) cho khối đọc; bảng và dashboard không giới hạn.

## Ký hiệu (Signature): "chip điều khoản"

Trích dẫn căn cứ pháp lý luôn render theo một hình duy nhất — cả trên web và PDF:

```
┌───────────────────────────────────────────────────────────────┐
│ Điều 12 · Khoản 2          122/2025/QH15   ✓ hiệu lực 01/07/2026 │
└───────────────────────────────────────────────────────────────┘
   “nguyên văn điều khoản, Source Serif 4, leading 1.65”
```

- Chip trái: nhãn vị trí (Điều/Khoản/Điểm) uppercase +0.06em, nền `--surface-2`, viền `--line`.
- Phải: số hiệu văn bản Be Vietnam Pro 600, dấu chấm seal 3px trước nhãn hiệu lực (seal chỉ 3px).
- Khối trích dẫn bên dưới: hairline 1px trên (`--line-strong`) — không bao giờ viền trái dày.
- Số và ngày luôn `tabular-nums`.

## Bố cục & nhịp

- Lưới cố định 12 cột, gutter 24px, margin ≥24px; sidebar 248px.
- Khoảng cách **khít trong nhóm, rộng giữa nhóm**; heading có khoảng trống phía trên nhiều hơn phía dưới.
- Card: radius 14px, 1px viền `--line`, shadow chỉ khi nổi (menu, dialog) — `--shadow-2`.
  Không card lồng card. Không viền trái màu >1px.
- Bảng dữ liệu: hairline ngang `--line` 1px, header chip micro, số tabular, hover đổi nền `--surface-2`,
  focus ring `--accent` offset 2px.
- Khối trống (empty state): hình minh hoạ chính mình (SVG hình học nhỏ, 2 nét), tiêu đề là câu hành
  động, nút chính — không emoji, không minh hoạ phác họa người.
- Nút: pill nhỏ cho secondary (radius pill, 32px cao), nút chính radius 10px 36px.

## Motion (apple-design, dịch sang web)

- Mặc định spring critically damped: `duration 0.32, bounce 0` (mapping damping 1.0 / response 0.32).
  Chỉ tác vụ có momentum (thả tay sau khi kéo, kéo đóng sheet/dialog) dùng `bounce 0.18`, `duration 0.3`
  (SRS drawer damping 0.8 / response 0.3).
- Phản hồi tức thì trên `pointerdown` (scale 0.98, 90ms), không đợi `click`.
- Kéo: Pointer Events + `setPointerCapture`, tôn trọng offset chỗ bấm, track velocity 5 frame cuối.
- Ngắt giữa chừng luôn chạy từ giá trị đang hiển thị (presentation value), không từ target.
- Cửa sổ/nav: vào và ra cùng đường (in-right → out-right), `transform-origin` tại điểm kích hoạt.
- Biên giới mềm (rubber-band) cho vùng kéo quá giới hạn: `f(x) = x·d·0.55/(d + 0.55·|x|)`.
- Chuyển động dài (wizard → kết quả): một khoảnh khắc tác giả — thanh tiến độ assessment chạy theo
  từng bước, rồi kết quả "mở" từ mép trên (clip-path), không entrance đồng loạt cho mọi section.
- `@media (prefers-reduced-motion: reduce)`: chỉ cross-fade 200ms, không transform/spring.
  `@media (prefers-reduced-transparency: reduce)`: nền đặc, không blur.
- Chỉ animate `transform`/`opacity` (+ clip-path/blur khi cần); `will-change` chỉ khi sắp chuyển động.

## Vật liệu & độ sâu

- Không dùng `backdrop-filter` trang trí. Chỉ toolbar dính và popover được làm "giấy mica":
  nền `color-mix(in oklab, var(--surface) 88%, transparent)` + blur 16px, và chỉ khi nội dung
  cuộn phía dưới thực sự. Kéo theo: ở vùng cuối cuộn, fade gradient `--paper` 24px thay viền cứng.
- Bề mặt lớn (sidebar) đậm hơn một bậc (`--surface-2`); bề mặt nhỏ nổi hơn (`--surface`).
- Không xếp hai lớp mờ lên nhau.

## Giọng văn (voice)

- Trực tiếp, hành động, tiếng Việt: "Xem nghĩa vụ khả thi", "Bắt đầu quét", "Tạo đánh giá mới".
- Trạng thái là cụm từ không phải từ đơn: "Có khả năng áp dụng", "Chưa tìm thấy bằng chứng",
  "Cần chuyên gia xác nhận".
- Lỗi nêu vấn đề + cách khắc phục: "Chưa quét được trang này — kiểm tra robots.txt hoặc thử lại sau 5 phút."
- Không dùng "hợp pháp", "vi phạm", "đạt chuẩn" làm kết luận. Không tiếng Anh kỹ thuật khi có từ Việt.

## Phạm vi tài liệu này

Áp dụng cho mọi màn hình (public, app, admin) và báo cáo PDF (PDF tái tạo chip, bảng hairline,
font tự host, hash + as_of_date). `docs/API.md` và PRODUCT.md là tài liệu kèm, không ghi đè DESIGN.md.
Biến thể trong bản sắc được phép; bản sắc khác nhau giữa các màn hình thì không.
