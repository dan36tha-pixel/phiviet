# Deploy VuTube PHP lên InfinityFree — sexviet.cc.cd

Phiên bản web: PHP thuần + MySQL, chạy trực tiếp trên InfinityFree (không cần Python).
Toàn bộ file cần đưa lên hosting nằm trong thư mục `php/`.
Bản nén sẵn để upload: **`sexviet-php-upload.zip`** (ở thư mục gốc dự án).

---

## 1. Vì sao trước đó bị "HTTP ERROR 500"?

Đã kiểm tra trực tiếp trên hosting (13/11/2026):

| Hạng mục | Kết quả |
|---|---|
| `check.php` | ✅ chạy được, PHP 8.4.27, đủ `pdo_mysql`, ghi được `data/` |
| Kết nối MySQL | ✅ OK tới `if0_xxxxxxx_dbname` @ `sqlxxx.infinityfree.com` |
| Bảng của web | ❌ **chưa có** bảng `users`, `videos`, `likes`, `comments` |
| `index.php`, `watch.php`, trang chủ `/` | ❌ 500, body rỗng |

Nguyên nhân: database mới chỉ có bảng WordPress cũ (`wpwk_*`), chưa chạy
`install.php` nên `index.php` truy vấn bảng `videos` → PDO ném lỗi
`Table '...videos' doesn't exist` → PHP fatal → hosting trả **500 trắng**.

## 2. Đã sửa những gì

| File | Thay đổi |
|---|---|
| `includes/db.php` | `db()` tự kiểm tra & **tự tạo 4 bảng** nếu database còn trống → không còn 500 do thiếu bảng |
| `includes/helpers.php` | Thêm bộ bắt lỗi toàn cục: lỗi được ghi vào `data/error.log`, người xem thấy trang "Trang tạm thời bị lỗi" thay vì màn hình trắng |
| `includes/footer.php` | Đánh dấu trang đã render xong (tránh chèn trang lỗi vào cuối trang bình thường) |
| `config.php` | Thêm `APP_DEBUG` (mặc định `false`; đổi `true` khi cần xem chi tiết lỗi) |
| `check.php` | Thêm `charset=UTF-8` để tiếng Việt không bị lỗi font |
| `.htaccess` | Thêm `DirectoryIndex index.php`, chặn tải `error.log`, ẩn `index.php` trên thanh địa chỉ (`/index.php?...` → `/?...`) |

Sau khi cập nhật: mở trang chủ là web chạy ngay (kể cả khi chưa chạy `install.php`).

**URL sạch:** địa chỉ trang chủ luôn là `https://tenmien/` (không có `index.php`).
Mọi link/redirect nội bộ dùng `./` nên khi bấm bộ lọc, tìm kiếm, đăng nhập... thanh
địa chỉ vẫn là `tenmien/?q=...`. Ai mở trực tiếp `tenmien/index.php` sẽ tự động bị
chuyển (301) về `tenmien/` và vẫn giữ nguyên query string.

---

## 3. Upload lên hosting

### Cách A — FileZilla (khuyên dùng, thay toàn bộ)

1. Mở FileZilla → kết nối FTP:
   - Host: `ftpupload.net` · Port: `21`
   - User / Pass: lấy ở panel InfinityFree → **FTP Details**
2. Vào thư mục `htdocs`.
3. Giải nén `sexviet-php-upload.zip` ra một thư mục tạm trên máy.
4. Kéo **toàn bộ nội dung bên trong** (không kéo thư mục cha) vào `htdocs`,
   chọn **Overwrite** khi được hỏi.
   - Nhớ bật *Server → Force showing hidden files* để `.htaccess` được upload.
5. Bật lại **Remote → Server → Refresh**.

### Cách B — File Manager trong panel (nhanh, chỉ sửa lỗi 500)

Panel InfinityFree → **File Manager** → `htdocs` → upload 6 file sau (ghi đè):

```
.htaccess
config.php
check.php
includes/db.php
includes/helpers.php
includes/footer.php
```

Chỉ cần 6 file này là trang chủ hết 500.

> Không dùng được cách upload nào? Nhắn mình thông tin FTP (Host/User/Pass),
> mình up trực tiếp giúp.

---

## 4. Sau khi upload — chạy 1 lần

1. Mở `https://sexviet.cc.cd/install.php`
2. Điền tên admin + mật khẩu (tự chọn) → tick "Thêm 6 video mẫu" → **Cài đặt**.
3. Vào `https://sexviet.cc.cd/` kiểm tra lưới video.
4. **XÓA `install.php` và `check.php`** khỏi `htdocs` (rất quan trọng).
5. Đăng nhập, đổi mật khẩu nếu cần, rồi thêm video thật.
6. Nếu database còn video mang chuyên mục cũ, mở
   `https://sexviet.cc.cd/migrate-categories.php` một lần rồi xóa file đó.

Nếu không muốn tạo admin qua `install.php`: mở `register.php` đăng ký tài khoản
đầu tiên rồi đăng nhập — bảng đã được tạo tự động.

---

## 4.1. Cổng xác nhận 18+

- `verify.php` là trang chặn: khách chưa xác nhận bị chuyển tới đây trước khi xem
  bất kỳ trang nào. Bấm **Tôi đủ 18 tuổi** → ghi cookie `sv_age_ok` (30 ngày) rồi
  vào tiếp đúng trang họ muốn; bấm **Tôi chưa đủ 18** → chuyển sang Google.
- Đổi thời gian ghi nhớ: sửa `AGE_GATE_DAYS` trong `config.php`.
- Tắt cổng này (không khuyên): thêm `define('SKIP_AGE_GATE', true);` vào `config.php`.
- Cookie chỉ là xác nhận của trình duyệt, không kiểm tra được tuổi thật — nó giúp
  đúng quy trình cảnh báo, không thay thế việc kiểm duyệt nội dung.

---

## 4.2. Trang chủ tự đọc `link.txt`

- Mỗi lần mở trang chủ, web so nội dung `link.txt` với file mốc
  `data/link.txt.mtime` (lưu hash nội dung). File thay đổi → đồng bộ database;
  không đổi → không làm gì (không tốn query, không nhập trùng).
- **Thêm dòng** → thêm video mới. **Xóa dòng** → video tương ứng tự động bị xóa
  khỏi database (kèm lượt thích, bình luận, thumbnail tự sinh), nên trang chủ
  cập nhật ngay. **Sửa URL / ảnh** giữ nguyên tiêu đề → cập nhật tại chỗ.
  Đổi tiêu đề thì hiểu là video mới (video cũ bị xóa, video mới được thêm).
- Video nhập từ `link.txt` chạy như video bình thường: có trang xem, lượt xem,
  thích, bình luận, thumbnail tự sinh.
- Tiêu đề đã đồng bộ được lưu ở `data/link.txt.titles.json` làm mốc để biết dòng
  nào bị gỡ. Lần đầu nâng cấp lên phiên bản này, mốc được lấy từ database nên
  các video `link.txt` cũ vẫn được tính đúng.
- Chỉ các video nhập từ danh sách mới bị xóa tự động; video tải lên tay qua
  `upload.php` không bị ảnh hưởng.
- `link.txt` phải nằm cùng thư mục `index.php`. Các file `data/*.mtime`,
  `data/*.json` bị `.htaccess` chặn truy cập, không lộ ra ngoài.

---

## 4.3. Tối ưu cho AI / công cụ tìm kiếm

Web đã tự sinh các file sau (không cần tạo tay, URL tự đúng theo domain):

| Địa chỉ | File thật | Nội dung |
|---|---|---|
| `/llms.txt` | `llms.php` | Hướng dẫn cho LLM: tên site, chuyên mục, cách đọc video, tham số lọc |
| `/robots.txt` | `robots.php` | Cho phép crawler (kể cả GPTBot, ClaudeBot, PerplexityBot...) + đường dẫn sitemap |
| `/sitemap.xml` | `sitemap.php` | Danh sách mọi video (`watch.php?id=...`) kèm ngày cập nhật |

Ngoài ra:

- Mỗi trang có `meta description`, `canonical`, OpenGraph/Twitter card và JSON-LD
  (schema.org): trang chủ là `CollectionPage` + `ItemList`, trang xem là `VideoObject`
  (tiêu đề, mô tả, ảnh, ngày đăng, lượt xem/thích).
- Thời gian hiển thị dùng thẻ `<time datetime="...">` (ISO 8601) để máy hiểu.
- Bot AI/search **không bị cổng 18+ chặn** (chỉ đọc metadata). Người xem vẫn phải
  xác nhận như thường. Tắt bằng `AI_CRAWLER_BYPASS = false` trong `config.php`.
- `AGENTS.md` là hướng dẫn cho AI/agent khi bảo trì code (quy ước, cấu trúc, luồng dữ liệu).
- Lưu ý: chính sách của từng AI (OpenAI, Anthropic...) có thể vẫn tự chặn nội dung
  người lớn dù `robots.txt` cho phép — mình chỉ làm phần mình kiểm soát được.

---

## 4.4. Sinh mô tả/tag qua OpenRouter

Key và model đã được điền sẵn trong `config.php`:

```php
define('OPENROUTER_API_KEY', 'sk-or-v1-...');
define('OPENROUTER_MODEL',   'nvidia/nemotron-3-ultra-550b-a55b:free');
```

1. Đăng nhập admin, vào menu **🤖 AI** (`ai-settings.php`) → bấm **Test kết nối**.
2. Nếu muốn đổi key/model, dán vào form trang đó (lưu vào bảng `settings`, ưu tiên
   hơn `config.php`) rồi bấm **Lưu cấu hình**.
3. Tuỳ chọn:
   - **Tự động sinh mô tả khi nhập video mới từ `link.txt`** (mặc định BẬT).
     Chỉ áp dụng cho dòng có mô tả trống, tối đa `AI_BATCH_LIMIT` video mỗi lần đồng bộ.
   - **Tạo mô tả hàng loạt** cho các video cũ chưa có mô tả (mỗi lần tối đa 10).
   - Nút **✨ Tạo mô tả AI** trên từng trang xem video.

- Model `nvidia/nemotron-3-ultra-550b-a55b:free` là bản miễn phí trên OpenRouter;
  đổi ở ô **Model** nếu muốn dùng model khác.
- Nếu host giới hạn thời gian chạy (thường 30s) làm nút "Tạo hàng loạt" bị ngắt,
  giảm `AI_BATCH_LIMIT` trong `config.php` xuống 3–5.
- **Tiêu đề không bao giờ bị AI đổi** — đây là khóa để đồng bộ xoá trong `link.txt`.
- Nếu AI từ chối nội dung nhạy cảm / hết hạn mức, mô tả cũ được giữ nguyên; xem
  `data/error.log` (dòng có tiền tố `[AI]`) để biết lý do.
- ⚠️ API key là bí mật: `config.php` đã bị `.htaccess` chặn truy cập trực tiếp.
  Nếu từng chia sẻ key ở nơi công khai, hãy tạo key mới trên OpenRouter.

---

## 5. Khi có lỗi mới

1. Mở `https://sexviet.cc.cd/check.php` → xem môi trường/CSDL/file thiếu.
2. Xem `data/error.log` (FileZilla tải về) — hoặc tạm đặt `APP_DEBUG` = `true`
   trong `config.php` để thấy chi tiết lỗi ngay trên trình duyệt.
3. Nhớ đặt lại `APP_DEBUG` = `false` sau khi sửa xong.

---

## 6. Cấu hình hiện tại (đã đúng, không cần sửa)

| Thông số | Giá trị |
|---|---|
| PHP | 8.4.27 (OK, cần ≥ 7.4) |
| MySQL host | `sqlxxx.infinityfree.com` |
| Database | `if0_xxxxxxx_dbname` (dùng chung với WordPress cũ — bảng web tên riêng, không đụng `wpwk_*`) |
| Tài khoản DB | `if0_xxxxxxx` |
| Thư mục web | `htdocs` |
| Domain | `https://sexviet.cc.cd` |

---

## 7. Giới hạn của InfinityFree

| Hạn chế | Giá trị | Cách sống chung |
|---|---|---|
| Upload qua web | ~10 MB/file | Video lớn: dùng **link ngoài** hoặc **nhập danh sách** (`import.php`) |
| Dung lượng disk | 5 GB | Đủ cho thumbnail + web |
| Lượt truy cập/ngày | ~50.000 hits | Web mới ổn |
| Không đăng nhập panel 30 ngày | Bị xóa tài khoản | Đăng nhập panel định kỳ |
| ffmpeg | Không có | Thumbnail tự sinh SVG (code đã xử lý) |
| Nội dung | Không được đăng nội dung người lớn / vi phạm bản quyền | Dễ bị khóa vĩnh viễn — cân nhắc trước khi đăng |

## 8. Chức năng

- Trang chủ: lưới video, tìm kiếm, lọc danh mục, sắp xếp mới/xem nhiều
- Xem video: player, đếm lượt xem, like/unlike, bình luận, video liên quan
- Upload: file hoặc link ngoài + thumbnail (tự sinh SVG nếu bỏ trống)
- Đăng ký / đăng nhập / đăng xuất (mật khẩu băm `password_hash`)
- Xóa video của chính mình (xóa cả file + like + bình luận)
- Nhập danh sách video từ file `.txt` đúng format `dangbai.txt`
- Phát HLS (m3u8) qua hls.js cho embed `<video data-m3u8Url='...'>`
