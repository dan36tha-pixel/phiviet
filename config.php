<?php
/**
 * VuTube PHP — CẤU HÌNH SITE
 * Các giá trị bí mật (DB_PASS, OPENROUTER_API_KEY...) được đọc từ
 * config.local.php (bị .gitignore). Xem config.example.php để tạo file này.
 */
$__config_local = __DIR__ . '/config.local.php';
$__cfg          = is_file($__config_local) ? require $__config_local : [];

/**
 * Khi deploy lên InfinityFree: tạo database rồi khai báo thông tin trong
 * config.local.php (không sửa trực tiếp các dòng dưới đây).
 */
define('DB_HOST', $__cfg['DB_HOST'] ?? 'localhost');
define('DB_NAME', $__cfg['DB_NAME'] ?? 'database_name');
define('DB_USER', $__cfg['DB_USER'] ?? 'database_user');
define('DB_PASS', $__cfg['DB_PASS'] ?? '');

/**
 * Hiện chi tiết lỗi ra trình duyệt? false = an toàn cho web thật
 * (lỗi được ghi vào data/error.log), true = chỉ dùng khi đang sửa code.
 */
define('APP_DEBUG', false);

// Tên site + chuyên mục
define('SITE_NAME', 'Sex Việt');
define('CATEGORIES', ['PHIM VIỆT', 'SINH VIÊN', 'CAMERA', 'PHIM AI']);

/**
 * Cổng xác nhận 18+ trước khi vào web.
 * AGE_GATE_DAYS = số ngày ghi nhớ xác nhận của người xem.
 */
define('AGE_GATE_COOKIE', 'sv_age_ok');
define('AGE_GATE_DAYS', 30);

/**
 * Cho phép bot tìm kiếm / AI (Googlebot, GPTBot, ClaudeBot...) đọc metadata
 * mà không cần bấm xác nhận 18+. Người xem vẫn phải xác nhận bình thường.
 * Đặt false nếu bạn muốn chặn hoàn toàn crawler.
 */
define('AI_CRAWLER_BYPASS', true);

/**
 * Tính năng sinh mô tả/tag qua OpenRouter (https://openrouter.ai).
 * - Key đặt trong config.local.php hoặc nhập trong trang /ai-settings.php
 *   (key lưu trong database được ưu tiên, đổi key không cần sửa code).
 * - Model mặc định: NVIDIA Nemotron 3 Ultra (bản :free).
 * - AI_AUTO_DESCRIBE: tự sinh mô tả cho video MỚI nhập từ link.txt.
 * - AI_BATCH_LIMIT: giới hạn số video mỗi lần bấm "Tạo hàng loạt" / mỗi lần sync.
 *
 * ⚠️ API key là bí mật: config.php đã bị .htaccess chặn truy cập trực tiếp.
 *    Không đưa key vào file JS/HTML hay commit lên repo công khai.
 */
define('OPENROUTER_API_KEY', $__cfg['OPENROUTER_API_KEY'] ?? '');
define('OPENROUTER_MODEL', 'nvidia/nemotron-3-ultra-550b-a55b:free');
define('AI_AUTO_DESCRIBE', true);
define('AI_BATCH_LIMIT', 10);

/**
 * Giới hạn dung lượng upload (MB).
 * LƯU Ý: InfinityFree giới hạn ~10MB cho mỗi file tải lên qua web,
 * nên video dung lượng lớn hãy dùng "link video bên ngoài" hoặc nhập danh sách.
 */
define('MAX_VIDEO_MB', 10);
define('MAX_IMAGE_MB', 5);

// Thư mục lưu file (không cần sửa)
define('BASE_DIR', __DIR__);
define('VIDEO_DIR', BASE_DIR . '/data/videos');
define('THUMB_DIR', BASE_DIR . '/data/thumbs');
