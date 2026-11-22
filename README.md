# Sex Việt — web PHP thuần + bản tĩnh cho GitHub Pages

Thư viện video theo chuyên mục, chạy trên Apache/PHP (InfinityFree...) và có thêm
**bản tĩnh** để xem công khai trên GitHub Pages.

Nguồn dữ liệu video là **`link.txt`** — thêm/xóa dòng là web cập nhật theo.

## 1. Chạy bản PHP (đầy đủ tính năng)

Cần Apache + PHP 8 + MySQL.

1. Sao chép `config.example.php` thành `config.local.php`.
2. Điền thông tin MySQL và OpenRouter API key vào `config.local.php`
   (`config.php` sẽ tự đọc các giá trị này).
3. Mở trang chủ một lần để web tự tạo bảng, hoặc chạy `install.php` để tạo
   tài khoản admin + video mẫu rồi xóa file đó đi.

`config.local.php` nằm trong `.gitignore` — **tuyệt đối không commit** (chứa mật khẩu DB/API key).

Tính năng đầy đủ: đăng nhập, like, bình luận, upload, đổi ảnh đại diện, sinh mô tả AI.
Xem `HUONGDAN-DEPLOY.md` để deploy lên InfinityFree.

## 2. Bản tĩnh cho GitHub Pages

GitHub Pages **không chạy PHP và không có MySQL**, nên repo có thêm script
build tĩnh: đọc `link.txt`, sinh ra HTML/CSS/JS thuần.

```bash
node tools/build-static.mjs
# tuỳ chọn: BASE_URL=https://<user>.github.io/<repo> node tools/build-static.mjs
```

Kết quả nằm trong `dist/` (đã gitignore):

| File | Nội dung |
|---|---|
| `dist/index.html` | lưới video, tìm kiếm + lọc chuyên mục + sắp xếp bằng JS |
| `dist/watch/<slug>.html` | trang xem từng video (player HLS/iframe, mô tả, video liên quan) |
| `dist/404.html` | trang không tìm thấy |
| `dist/sitemap.xml`, `robots.txt`, `llms.txt` | cho search engine / AI |
| `dist/static/...` | CSS/JS, thumbnail SVG tự sinh cho video thiếu ảnh |

Mở `dist/index.html` bằng trình duyệt để xem thử.

### Deploy lên GitHub Pages

Workflow `.github/workflows/pages.yml` tự build và deploy mỗi khi push lên `main`.

1. Vào **Settings → Pages**.
2. Mục **Source**, chọn **GitHub Actions**.
3. Push code lên `main` (hoặc bấm **Run workflow** ở tab Actions).

Sau khoảng 1 phút, web chạy tại `https://<user>.github.io/<repo>/`.

### Khác biệt so với bản PHP

| Có trên bản PHP | Bản tĩnh |
|---|---|
| Like, bình luận, đăng nhập | ✗ |
| Upload video, đổi ảnh đại diện | ✗ |
| Sinh mô tả bằng AI | ✗ |
| Lượt xem, số like | ✗ (không có database) |
| Xem video, tìm kiếm, lọc, sitemap/SEO | ✓ |

Cổng xác nhận 18+ ở bản tĩnh dùng `localStorage` (nhớ trên từng thiết bị).

## 3. Cấu trúc

| Đường dẫn | Vai trò |
|---|---|
| `index.php`, `watch.php` | trang chủ và trang xem (bản PHP) |
| `link.txt` | **nguồn dữ liệu video**: `ảnh\|tiêu đề\|mô tả\|w\|h\|<embed>` |
| `includes/helpers.php` | helper dùng chung (session, CSRF, parse/sync link.txt, JSON-LD...) |
| `includes/db.php` | PDO + tự tạo bảng `users`, `videos`, `likes`, `comments` |
| `tools/build-static.mjs` | build bản tĩnh từ `link.txt` |
| `static/` | CSS/JS/ảnh dùng chung cho cả hai bản |
| `data/` | video/thumbnail tải lên, log (không commit) |

Quy ước code chi tiết: xem `AGENTS.md`.
