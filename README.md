# s-viet

Giá trị cộng đồng — web PHP thuần chạy trên Apache / InfinityFree.

## Cấu hình (quan trọng)

Repo này **không chứa mật khẩu DB hay API key**. Để chạy:

1. Sao chép `config.example.php` thành `config.local.php`.
2. Điền thông tin MySQL và OpenRouter API key thật vào `config.local.php`.
3. `config.php` sẽ tự đọc các giá trị đó. File `config.local.php` nằm trong `.gitignore`,
   tuyệt đối không commit lên GitHub.

Các thiết lập khác (tên site, cổng 18+, model AI...) nằm trực tiếp trong `config.php`.
Xem `HUONGDAN-DEPLOY.md` để biết cách deploy lên InfinityFree.
