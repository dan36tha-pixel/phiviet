# AGENTS.md — hướng dẫn cho AI khi làm việc với repo này

Repo là web PHP thuần (không framework, không Composer) chạy trên Apache/InfinityFree.
Đọc file này trước khi sửa code để tránh phá vỡ các luồng hiện có.

## Cấu trúc

| Đường dẫn | Vai trò |
|---|---|
| `index.php` | Trang chủ: gọi `sync_link_file()`, lọc/tìm/sắp xếp, in lưới video + JSON-LD `ItemList` |
| `watch.php` | Trang xem video: player, like/comment, đổi thumbnail, JSON-LD `VideoObject` |
| `link.txt` | **Nguồn dữ liệu duy nhất** cho danh sách video (format: `ảnh|tiêu đề|mô tả|w|h|<embed>`) |
| `includes/helpers.php` | Mọi helper dùng chung: session, CSRF, flash, cổng 18+, parse/import/sync link.txt, URL & JSON-LD |
| `includes/db.php` | Kết nối PDO + tự tạo 4 bảng `users`, `videos`, `likes`, `comments` |
| `includes/header.php` | `<head>` dùng chung: meta/OpenGraph/canonical, JSON-LD, topbar. Đọc các biến `$page_*`, `$og_*`, `$ld_json` |
| `includes/footer.php` | Đóng `</main>`, footer, cờ `APP_RENDERED` |
| `config.php` | Cấu hình site (`SITE_NAME`, `CATEGORIES`, `AI_CRAWLER_BYPASS`, OpenRouter model/auto/limit); secrets đọc từ `config.local.php` (bị .gitignore) |
| `ai-settings.php` | Trang admin: nhập API key, test, tạo mô tả hàng loạt (lưu vào bảng `settings`) |
| `ai-generate.php` | POST tạo lại mô tả cho 1 video (admin) |
| `llms.php`, `robots.php`, `sitemap.php` | File sinh động, rewrite từ `/llms.txt`, `/robots.txt`, `/sitemap.xml` |
| `data/` | `videos/`, `thumbs/`, `error.log`, mốc đồng bộ `link.txt.mtime` + `link.txt.titles.json` |

## Quy ước quan trọng

- **Không đổi credentials trong `config.php`** trừ khi user yêu cầu.
- Mọi trang đều `require_once __DIR__ . '/includes/helpers.php'` ở đầu file. `helpers.php` tự gọi `age_gate_guard()`.
- Cổng 18+: bot AI/search được miễn qua `is_ai_crawler()` (bật/tắt bằng `AI_CRAWLER_BYPASS`).
- URL sạch: dùng `./` cho link/redirect về trang chủ, **không** dùng `index.php`.
- Đồng bộ `link.txt`: dùng `sync_link_file()` — thêm dòng → thêm, đổi link → update, xóa dòng → xóa video.
  Mốc tiêu đề nằm ở `data/link.txt.titles.json`. Không tự ý xóa file mốc.
- Xóa video luôn đi qua `delete_video_row()` để dọn cả likes/comments/thumbnail.
- AI: gọi OpenRouter chat completions qua `ai_request()` (`gemini_request()` là alias cũ).
  Key lưu ở bảng `settings` (ưu tiên) hoặc hằng số `OPENROUTER_API_KEY` trong config.php.
  Chỉ gọi ở phía server, **không bao giờ** in key ra HTML/JS.
  Không đổi tiêu đề video khi sinh nội dung — tiêu đề là khóa đồng bộ `link.txt`.
- Dữ liệu đưa vào JSON-LD phải qua `ld_json_script()` (đã mã hóa `<`, `&`...). Không tự nối chuỗi JSON vào HTML.
- Mọi bản ghi thời gian hiển thị dùng `<time datetime="<?= h(iso_datetime(...)) ?>">`.

## Khi thêm tính năng

1. Thêm helper vào `includes/helpers.php`, không lặp logic vào từng trang.
2. Set biến `$page_title`, `$page_description`, `$og_image`, `$ld_json` **trước** `require header.php`.
3. Form POST phải có `csrf_field()` và gọi `csrf_check()`.
4. Dùng `h()` khi in bất cứ dữ liệu nào ra HTML.
5. Chạy trên PHP 8 (`str_contains`, `str_starts_with`, arrow function đã được dùng).

## Kiểm tra nhanh

- Mở `/` → không thấy `index.php` trên thanh địa chỉ.
- Sửa `link.txt` (thêm/xóa một dòng) → mở `/` → video hiện/mất tương ứng.
- `/llms.txt`, `/robots.txt`, `/sitemap.xml` trả về nội dung đúng, không bị chặn bởi cổng 18+.
- Xem `data/error.log` nếu trang trắng (lỗi được ghi ở đó, `APP_DEBUG=false`).
