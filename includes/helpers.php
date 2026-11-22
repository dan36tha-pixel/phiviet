<?php
/**
 * Helper dùng chung: session, đăng nhập, flash, format, thumbnail,
 * parse danh sách video (format dangbai.txt), CSRF.
 */
require_once __DIR__ . '/db.php';

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', false);
}

/**
 * Ghi lỗi vào data/error.log + error_log của PHP.
 * Người xem không thấy chi tiết (tránh lộ đường dẫn, mật khẩu DB...).
 */
function app_log_error(Throwable $e): void
{
    $line = '[' . date('c') . '] ' . get_class($e) . ': ' . $e->getMessage()
          . ' in ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL
          . $e->getTraceAsString() . PHP_EOL . PHP_EOL;
    @file_put_contents(BASE_DIR . '/data/error.log', $line, FILE_APPEND | LOCK_EX);
    error_log('[VuTube] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
}

/** Trang báo lỗi thân thiện thay cho màn hình "HTTP ERROR 500" trắng trơn. */
function app_error_page(?Throwable $e = null): void
{
    if (defined('APP_RENDERED')) {
        return; // trang đã in xong, không chèn thêm
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    $detail = '';
    if (APP_DEBUG && $e) {
        $detail = '<pre style="text-align:left;white-space:pre-wrap;background:#1b1b22;'
                . 'border:1px solid #333;border-radius:8px;padding:14px;margin-top:16px;'
                . 'font-size:.82rem;color:#ffb4a2;overflow-x:auto">'
                . htmlspecialchars(get_class($e) . ': ' . $e->getMessage()
                    . "\n" . $e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8')
                . '</pre>';
    }
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
       . '<title>Lỗi máy chủ</title></head>'
       . '<body style="font-family:Segoe UI,system-ui,sans-serif;background:#0e0e12;color:#e8e8ef;'
       . 'display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:20px">'
       . '<div style="max-width:640px;text-align:center">'
       . '<div style="font-size:52px">⚠️</div>'
       . '<h1 style="font-size:1.4rem;margin:10px 0 6px">Trang tạm thời bị lỗi</h1>'
       . '<p style="color:#a9a9b8;line-height:1.6">Máy chủ gặp sự cố khi xử lý trang này. '
       . 'Vui lòng thử lại sau ít phút.</p>'
       . '<p style="margin-top:18px"><a href="./" style="color:#ff5c39">← Về trang chủ</a></p>'
       . $detail
       . '</div></body></html>';
}

// Bắt mọi exception chưa được xử lý -> không còn trang 500 trắng.
set_exception_handler(function (Throwable $e): void {
    app_log_error($e);
    app_error_page($e);
});

// Bắt cả lỗi fatal (E_ERROR / parse error) mà exception handler không thấy.
register_shutdown_function(function (): void {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    $e = new ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']);
    app_log_error($e);
    app_error_page($e);
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Chặn xem nội dung khi chưa xác nhận 18+ (verify.php tự khai báo SKIP_AGE_GATE).
// Đặt ở đây để chạy TRƯỚC khi trang kịp truy vấn database hay in ra nội dung.
age_gate_guard();

// Tạo thư mục data nếu chưa có (host cho phép ghi)
foreach ([VIDEO_DIR, THUMB_DIR] as $d) {
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
}

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Xử lý chuỗi UTF-8 không bắt buộc extension mbstring
function u_len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s) : (int)preg_match_all('/./us', $s);
}

function u_substr(string $s, int $start, ?int $len = null): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($s, $start, $len);
    }
    $out = '';
    $i = 0;
    foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        if ($i >= $start && ($len === null || $i < $start + $len)) $out .= $ch;
        $i++;
    }
    return $out;
}

// ----- Flash message -----
function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ----- CSRF đơn giản cho các form POST -----
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && !hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Phiên không hợp lệ, hãy tải lại trang và thử lại.');
    }
}

// ----- Cổng xác nhận 18+ -----
/** Người xem đã bấm "Tôi đủ 18 tuổi" chưa? */
function age_gate_ok(): bool
{
    return ($_COOKIE[AGE_GATE_COOKIE] ?? '') === '1';
}

/** Lưu xác nhận 18+ vào cookie (mặc định 30 ngày). */
function age_gate_save(): void
{
    if (headers_sent()) {
        return;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie(AGE_GATE_COOKIE, '1', [
        'expires'  => time() + AGE_GATE_DAYS * 86400,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[AGE_GATE_COOKIE] = '1';
}

/**
 * Chặn truy cập nếu chưa xác nhận 18+.
 * Gọi ở đầu includes/header.php — trước khi in ra bất kỳ nội dung nào.
 */
function age_gate_guard(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (defined('SKIP_AGE_GATE') && SKIP_AGE_GATE) {
        return;
    }
    if (age_gate_ok() || is_ai_crawler()) {
        return; // bot AI/search chỉ cần metadata, không chặn bởi cổng 18+
    }
    $next = age_gate_safe_next($_SERVER['REQUEST_URI'] ?? './');
    header('Location: verify.php?next=' . rawurlencode($next));
    exit;
}

/** Chỉ chấp nhận đường dẫn nội bộ, tránh open-redirect sang site lạ. */
function age_gate_safe_next(?string $next): string
{
    $next = (string)$next;
    // Bỏ xuống dòng / ký tự điều khiển có thể bị chèn header
    $next = str_replace(["\r", "\n", "\0"], '', $next);
    // Chấp nhận đường dẫn tuyệt đối (/...) hoặc tương đối (./...) tại thư mục gốc.
    if ($next === ''
        || ($next[0] !== '/' && strpos($next, './') !== 0)
        || str_starts_with($next, '//')
        || str_contains($next, '\\')
        || str_contains($next, '../')) {
        return './';
    }
    return $next;
}

// ----- Tài khoản -----
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $st = db()->prepare('SELECT * FROM users WHERE id = ?');
            $st->execute([$_SESSION['user_id']]);
            $user = $st->fetch() ?: null;
        }
    }
    return $user;
}

/** Bắt buộc đăng nhập, trả về user; chuyển hướng sang login.php nếu chưa đăng nhập */
function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash('Bạn cần đăng nhập để làm việc này.', 'error');
        $next = $_SERVER['REQUEST_URI'] ?? './';
        header('Location: login.php?next=' . rawurlencode($next));
        exit;
    }
    return $u;
}

// ----- Định dạng -----
function format_views($n): string
{
    $n = (int)$n;
    if ($n >= 1000000) return round($n / 1000000, 1) . 'M';
    if ($n >= 1000)    return round($n / 1000, 1) . 'K';
    return (string)$n;
}

function time_ago(string $mysqlDatetime): string
{
    $t = strtotime($mysqlDatetime);
    if ($t === false) return '';
    $diff = time() - $t;
    if ($diff < 0) $diff = 0;
    foreach ([[31536000, 'năm'], [2592000, 'tháng'], [86400, 'ngày'],
              [3600, 'giờ'], [60, 'phút']] as [$secs, $unit]) {
        if ($diff >= $secs) {
            return floor($diff / $secs) . " $unit trước";
        }
    }
    return 'Vừa xong';
}

// ----- URL + dữ liệu có cấu trúc cho máy đọc (AI/search) -----
/** Scheme + host hiện tại (đã lọc ký tự lạ). */
function site_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return ($https ? 'https' : 'http') . '://' . ($host ?: 'localhost');
}

/** Biến đường dẫn tương đối thành URL tuyệt đối (cần cho og:image / JSON-LD). */
function to_absolute_asset(string $url): string
{
    if ($url === '') return '';
    if (preg_match('#^(https?:)?//#i', $url)) return $url;
    return site_origin() . ($url[0] === '/' ? $url : '/' . $url);
}

/** URL đầy đủ của trang hiện tại, chuẩn hóa /index.php -> /. */
function current_url_clean(): string
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $uri = str_replace(["\r", "\n", "\0"], '', $uri);
    if (strpos($uri, '/index.php') === 0) {
        $uri = substr($uri, 10) ?: '/';
        if ($uri[0] !== '?' && $uri[0] !== '/') $uri = '/' . $uri;
    }
    return site_origin() . $uri;
}

/** Ngày MySQL -> ISO 8601 (dùng cho <time datetime> và uploadDate). */
function iso_datetime(string $mysqlDatetime): string
{
    $t = strtotime($mysqlDatetime);
    return $t === false ? '' : date('c', $t);
}

/**
 * In thẻ JSON-LD an toàn: mã hóa < > & để không thể thoát khỏi <script>.
 */
function ld_json_script(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return $json === false ? '' : '<script type="application/ld+json">' . $json . '</script>';
}

/** Bot công cụ tìm kiếm / AI được phép đọc metadata mà không qua cổng 18+. */
function is_ai_crawler(): bool
{
    if (!defined('AI_CRAWLER_BYPASS') || !AI_CRAWLER_BYPASS) return false;
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') return false;
    foreach ([
        'gptbot', 'chatgpt-user', 'oai-searchbot', 'claudebot', 'claude-web',
        'anthropic-ai', 'perplexitybot', 'perplexity-user', 'googlebot',
        'google-extended', 'bingbot', 'ccbot', 'applebot', 'duckduckbot',
        'meta-externalagent', 'facebookexternalhit', 'twitterbot',
        'discordbot', 'slackbot-linkexpanding', 'bytespider',
    ] as $bot) {
        if (str_contains($ua, $bot)) return true;
    }
    return false;
}

// ----- AI: OpenRouter (sinh mô tả / tag) -----
/** Admin = user đầu tiên (id 1) hoặc user tên "admin". */
function is_admin(?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user) return false;
    return (int)$user['id'] === 1 || strtolower((string)$user['username']) === 'admin';
}

/** Ghi lỗi AI vào error.log (ngắn gọn, không trace). */
function ai_log(string $msg): void
{
    @file_put_contents(BASE_DIR . '/data/error.log',
        '[' . date('c') . '] [AI] ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/** Đảm bảo bảng settings tồn tại (DB cũ lên đời không cần chạy install.php). */
function ai_settings_ready(): bool
{
    static $ready = false;
    if ($ready) return true;
    try {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
                setting_value TEXT NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $ready = true;
        return true;
    } catch (Throwable $e) {
        ai_log('Không tạo được bảng settings: ' . $e->getMessage());
        return false;
    }
}

function ai_setting_get(string $key, string $default = ''): string
{
    if (!ai_settings_ready()) return $default;
    try {
        $st = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return ($v === false || $v === null) ? $default : (string)$v;
    } catch (Throwable $e) {
        return $default;
    }
}

function ai_setting_set(string $key, string $value): bool
{
    if (!ai_settings_ready()) return false;
    try {
        $st = db()->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        return $st->execute([$key, $value]);
    } catch (Throwable $e) {
        ai_log('Không lưu được setting ' . $key . ': ' . $e->getMessage());
        return false;
    }
}

/** Key trong DB được ưu tiên; nếu chưa có thì dùng hằng số trong config.php. */
function ai_api_key(): string
{
    $inDb = ai_setting_get('openrouter_api_key', '');
    if ($inDb !== '') return $inDb;
    return defined('OPENROUTER_API_KEY') ? (string)OPENROUTER_API_KEY : '';
}

function ai_model(): string
{
    $fallback = defined('OPENROUTER_MODEL') ? OPENROUTER_MODEL : 'nvidia/nemotron-3-ultra-550b-a55b:free';
    $model = ai_setting_get('openrouter_model', $fallback);
    // Cho phép dạng "provider/model:free" (có dấu / và :).
    $model = preg_replace('/[^A-Za-z0-9_.\/\-:]/', '', $model);
    return $model ?: $fallback;
}

function ai_enabled(): bool
{
    return ai_api_key() !== '';
}

/** Gọi OpenRouter chat completions. Trả về text hoặc null (đã log lỗi). */
function ai_request(string $prompt, int $maxTokens = 300): ?string
{
    $key = ai_api_key();
    if ($key === '') return null;

    $url = 'https://openrouter.ai/api/v1/chat/completions';
    $payload = json_encode([
        'model'       => ai_model(),
        'messages'    => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.7,
        'max_tokens'  => $maxTokens,
    ], JSON_UNESCAPED_UNICODE);
    if ($payload === false) return null;

    $resp  = false;
    $http  = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $key,
                'HTTP-Referer: ' . site_origin(),
                'X-Title: ' . SITE_NAME,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            ai_log('OpenRouter cURL lỗi: ' . $err);
            return null;
        }
    } else {
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n"
                             . 'Authorization: Bearer ' . $key . "\r\n"
                             . 'HTTP-Referer: ' . site_origin() . "\r\n"
                             . 'X-Title: ' . SITE_NAME . "\r\n",
            'content'       => $payload,
            'timeout'       => 60,
            'ignore_errors' => true,
        ]]);
        $resp = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header[0])
            && preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m)) {
            $http = (int)$m[1];
        }
        if ($resp === false) {
            ai_log('OpenRouter file_get_contents thất bại');
            return null;
        }
    }

    if ($http < 200 || $http >= 300) {
        ai_log('OpenRouter HTTP ' . $http . ': ' . u_substr((string)$resp, 0, 300));
        return null;
    }
    $data = json_decode((string)$resp, true);
    $text = $data['choices'][0]['message']['content'] ?? null;
    if (!is_string($text) || trim($text) === '') {
        ai_log('OpenRouter không trả text: ' . u_substr((string)$resp, 0, 300));
        return null;
    }
    return trim($text);
}

/** Tên cũ, giữ lại để không hỏng code/reference nào gọi gemini_request(). */
function gemini_request(string $prompt, int $maxTokens = 300): ?string
{
    return ai_request($prompt, $maxTokens);
}

/** Sinh mô tả ngắn + hashtag cho 1 video. Trả về null nếu AI không trả được. */
function ai_describe_video(string $title, string $category, string $existing = ''): ?string
{
    $prompt = "Bạn là biên tập viên nội dung tiếng Việt. Hãy viết mô tả ngắn gọn (tối đa 180 ký tự) "
        . "và 4 hashtag tiếng Việt cho video dựa trên tiêu đề và chuyên mục bên dưới. "
        . "Giữ giọng văn trung tính, không thêm lời dẫn, không nhắc lại lệnh này.\n"
        . "Trả về ĐÚNG 2 dòng theo mẫu:\n"
        . "MOTA: <mô tả>\nTAGS: #tag1 #tag2 #tag3 #tag4\n\n"
        . "TIEU DE: " . $title . "\nCHUYEN MUC: " . $category . "\n";
    if ($existing !== '') {
        $prompt .= "MO TA CO SAN (co the bo qua neu khong phu hop): " . u_substr($existing, 0, 200) . "\n";
    }

    $raw = ai_request($prompt, 300);
    if ($raw === null) return null;

    $desc = '';
    $tags = '';
    if (preg_match('/MOTA:\s*(.+)/u', $raw, $m)) $desc = trim($m[1]);
    if (preg_match('/TAGS:\s*(.+)/u', $raw, $m)) $tags = trim($m[1]);
    if ($desc === '' && $tags === '') {
        $desc = trim(preg_replace('/\s+/u', ' ', $raw));
    }
    $desc = u_substr($desc, 0, 180);
    $tags = u_substr($tags, 0, 120);
    $out  = $desc;
    if ($tags !== '') $out .= ($out !== '' ? "\n\n" : '') . $tags;
    return $out !== '' ? $out : null;
}

// ----- Media -----
function safe_filename(string $original): string
{
    $base = pathinfo($original, PATHINFO_FILENAME);
    $ext  = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $base = preg_replace('/[^A-Za-z0-9_\-]/', '_', $base) ?: 'file';
    return bin2hex(random_bytes(8)) . '_' . substr($base, 0, 60) . '.' . $ext;
}

/** Đường dẫn src cho thumbnail (URL ngoài / file trong data/thumbs / mặc định) */
function thumb_src(?string $thumbnail): string
{
    if ($thumbnail) {
        if (stripos($thumbnail, 'http') === 0 || $thumbnail[0] === '/') {
            return $thumbnail;
        }
        return 'data/thumbs/' . rawurlencode($thumbnail);
    }
    return 'static/img/no-thumb.svg';
}

/** Sinh thumbnail SVG gradient theo tiêu đề (không cần ffmpeg) */
function make_thumb_svg(string $title): string
{
    $hash = md5($title);
    $hue1 = hexdec(substr($hash, 0, 2)) * 360 / 255;
    $hue2 = hexdec(substr($hash, 2, 2)) * 360 / 255;
    $safe = h(u_substr($title, 0, 40));
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="hsl($hue1,55%,28%)"/>
      <stop offset="1" stop-color="hsl($hue2,60%,14%)"/>
    </linearGradient>
  </defs>
  <rect width="640" height="360" fill="url(#g)"/>
  <circle cx="320" cy="168" r="54" fill="rgba(255,92,57,.92)"/>
  <polygon points="303,142 303,194 350,168" fill="#ffffff"/>
  <text x="320" y="286" text-anchor="middle" font-family="Segoe UI, sans-serif"
        font-size="24" font-weight="600" fill="rgba(255,255,255,.85)">$safe</text>
</svg>
SVG;
    $name = bin2hex(random_bytes(16)) . '.svg';
    file_put_contents(THUMB_DIR . '/' . $name, $svg);
    return $name;
}

// ----- Nhập danh sách video (format dangbai.txt) -----
function parse_list_text(string $text): array
{
    $entries = [];
    $category = null;
    foreach (preg_split('/\r?\n/', $text) as $raw) {
        $line = trim($raw);
        if ($line === '') continue;
        if (preg_match('/^#\s*-{2,}\s*(.+?)\s*-{2,}\s*$/u', $line, $m)) {
            $category = trim($m[1]);
            continue;
        }
        if ($line[0] === '#') continue; // comment
        $parts = explode('|', $line);
        if (count($parts) < 6) continue;
        $name  = trim($parts[0]);
        $title = trim($parts[1]);
        $desc  = trim($parts[2]);
        $embed = trim(implode('|', array_slice($parts, 5)));
        $thumb = '';
        if (preg_match('/^(https?:\/\/|\/)/i', $name)) $thumb = $name;
        $entries[] = [
            'title'       => $title !== '' ? $title : ($name !== '' ? $name : 'Không tiêu đề'),
            'description' => $desc,
            'category'    => $category ?: CATEGORIES[array_key_last(CATEGORIES)],
            'embed_html'  => $embed,
            'thumb_url'   => $thumb,
        ];
    }
    return $entries;
}

/** Rút URL khỏi HTML nhúng: data-m3u8Url -> iframe src -> video src */
function extract_embed_url(string $embed): ?string
{
    foreach ([
        "/data-m3u8Url\s*=\s*['\"]([^'\"]+)['\"]/i",
        "/<iframe[^>]+src\s*=\s*['\"]([^'\"]+)['\"]/i",
        "/<video[^>]+src\s*=\s*['\"]([^'\"]+)['\"]/i",
    ] as $re) {
        if (preg_match($re, $embed, $m)) return $m[1];
    }
    return null;
}

/** Nạp entries vào DB (bỏ qua video trùng tiêu đề của user). Trả về [added, skipped, updated] */
function import_entries(array $entries, string $username = 'admin', bool $updateExisting = false,
                        bool $autoDescribe = false): array
{
    if (!$entries) return [0, 0, 0];
    $pdo = db();
    $st = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $st->execute([$username]);
    $row = $st->fetch();
    if ($row) {
        $userId = (int)$row['id'];
    } else {
        $pdo->prepare('INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, NOW())')
            ->execute([$username, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        $userId = (int)$pdo->lastInsertId();
    }

    $added = $skipped = $updated = 0;
    $aiUsed = 0;
    $aiLimit = defined('AI_BATCH_LIMIT') ? (int)AI_BATCH_LIMIT : 10;
    $check = $pdo->prepare('SELECT 1 FROM videos WHERE user_id = ? AND title = ?');
    $insert = $pdo->prepare(
        'INSERT INTO videos (user_id, title, description, category, filename,
                             external_url, thumbnail, embed_html, created_at)
         VALUES (?, ?, ?, ?, NULL, ?, ?, ?, NOW())'
    );
    $update = $pdo->prepare(
        'UPDATE videos SET description = COALESCE(?, description), category = ?, external_url = ?,
                thumbnail = COALESCE(?, thumbnail), embed_html = ?
         WHERE user_id = ? AND title = ?'
    );
    foreach ($entries as $e) {
        $check->execute([$userId, $e['title']]);
        if ($check->fetch()) {
            // Có sẵn video cùng tiêu đề: khi đồng bộ link.txt thì cập nhật link mới,
            // còn khi nhập tay (import.php) thì giữ nguyên và bỏ qua.
            if ($updateExisting) {
                $update->execute([
                    $e['description'] !== '' ? $e['description'] : null,
                    $e['category'],
                    extract_embed_url($e['embed_html']),
                    $e['thumb_url'] ?: null,
                    $e['embed_html'],
                    $userId,
                    $e['title'],
                ]);
                $updated++;
            } else {
                $skipped++;
            }
            continue;
        }
        // Chỉ sinh AI cho video MỚI có mô tả trống, có giới hạn để khỏi tốn tiền.
        $description = $e['description'];
        if ($autoDescribe && $description === '' && $aiUsed < $aiLimit) {
            $aiText = ai_describe_video($e['title'], $e['category']);
            if ($aiText !== null) {
                $description = $aiText;
                $aiUsed++;
            }
        }
        $insert->execute([
            $userId,
            $e['title'],
            $description,
            $e['category'],
            extract_embed_url($e['embed_html']),
            $e['thumb_url'] ?: make_thumb_svg($e['title']),
            $e['embed_html'],
        ]);
        $added++;
    }
    return [$added, $skipped, $updated];
}

/**
 * Xóa 1 video (kèm likes/comments/file media do web quản lý) khỏi database.
 * Dùng chung cho delete.php và cho việc đồng bộ xóa từ link.txt.
 */
function delete_video_row(int $id): void
{
    $pdo = db();
    $st = $pdo->prepare('SELECT filename, thumbnail FROM videos WHERE id = ?');
    $st->execute([$id]);
    $v = $st->fetch();
    if (!$v) return;

    if ($v['filename']) {
        @unlink(VIDEO_DIR . '/' . $v['filename']);
    }
    if ($v['thumbnail'] && strpos($v['thumbnail'], '/') === false) {
        // chỉ xóa file do web tự quản (SVG tự sinh), không đụng URL ngoài
        @unlink(THUMB_DIR . '/' . $v['thumbnail']);
    }
    $pdo->prepare('DELETE FROM likes WHERE video_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM comments WHERE video_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM videos WHERE id = ?')->execute([$id]);
}

/** Lấy id user theo tên (trả về 0 nếu không có). */
function user_id_by_name(string $username): int
{
    $st = db()->prepare('SELECT id FROM users WHERE username = ?');
    $st->execute([$username]);
    return (int)$st->fetchColumn();
}

/**
 * Xóa các video của $username có tiêu đề nằm trong $titles.
 * Trả về số video đã xóa.
 */
function delete_videos_by_titles(array $titles, string $username): int
{
    $titles = array_values(array_unique(array_filter($titles, 'is_string')));
    if (!$titles) return 0;

    $userId = user_id_by_name($username);
    if (!$userId) return 0;

    $select = db()->prepare('SELECT id FROM videos WHERE user_id = ? AND title = ?');
    $deleted = 0;
    foreach ($titles as $title) {
        $select->execute([$userId, $title]);
        $ids = $select->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            delete_video_row((int)$id);
            $deleted++;
        }
    }
    return $deleted;
}

/**
 * Danh sách tiêu đề "đến từ link.txt" hiện có trong database — dùng làm mốc
 * khi lần đầu chạy phiên bản có tính năng tự xóa (upgrade path). Chỉ lấy video
 * có embed_html (video nhập từ danh sách), không đụng video upload tay.
 */
function link_titles_from_db(string $username): array
{
    $st = db()->prepare(
        'SELECT v.title FROM videos v JOIN users u ON u.id = v.user_id
         WHERE u.username = ? AND v.embed_html IS NOT NULL'
    );
    $st->execute([$username]);
    return array_values(array_unique($st->fetchAll(PDO::FETCH_COLUMN)));
}

/**
 * Đồng bộ danh sách trong link.txt vào database (chỉ khi file có thay đổi).
 * - Dòng mới  -> thêm video.
 * - Dòng đổi link (giữ tiêu đề) -> cập nhật link/thumbnail.
 * - Dòng bị xóa -> video tương ứng cũng bị xóa khỏi database + trang chủ.
 * Trả về [added, skipped, updated, deleted]; [0, 0, 0, 0] khi file không đổi.
 */
function sync_link_file(string $path, string $username = 'admin'): array
{
    if (!is_file($path)) {
        return [0, 0, 0, 0];
    }
    $content = (string)file_get_contents($path);
    // Dùng hash nội dung thay vì filemtime: phát hiện cả đổi nội dung trong cùng 1 giây.
    $fingerprint = md5($content);
    $marker = BASE_DIR . '/data/link.txt.mtime'; // tên cũ, giờ lưu fingerprint
    $state  = BASE_DIR . '/data/link.txt.titles.json';

    $fh = @fopen($marker, 'c+');
    if (!$fh) {
        return [0, 0, 0, 0]; // không ghi được file mốc -> bỏ qua, không làm chết trang
    }
    @flock($fh, LOCK_EX);

    // Đã nhập đúng nội dung này rồi thì thôi (tránh 2 khách cùng lúc nhập trùng)
    if (trim((string)stream_get_contents($fh)) === $fingerprint) {
        @flock($fh, LOCK_UN);
        fclose($fh);
        return [0, 0, 0, 0];
    }

    $entries   = parse_list_text($content);
    $newTitles = array_values(array_unique(array_column($entries, 'title')));

    // Tiêu đề từ lần đồng bộ trước: đọc file mốc, lần đầu thì lấy từ database.
    if (is_file($state)) {
        $decoded = json_decode((string)file_get_contents($state), true);
        $oldTitles = is_array($decoded)
            ? array_values(array_filter($decoded, 'is_string'))
            : [];
    } else {
        $oldTitles = link_titles_from_db($username);
    }

    // Các tiêu đề có trước nhưng không còn trong link.txt -> xóa video.
    $removed = array_values(array_diff($oldTitles, $newTitles));
    $deleted = $removed ? delete_videos_by_titles($removed, $username) : 0;

    $autoDefault  = (defined('AI_AUTO_DESCRIBE') && AI_AUTO_DESCRIBE) ? '1' : '0';
    $autoDescribe = ai_enabled() && ai_setting_get('auto_describe', $autoDefault) === '1';
    $result = import_entries($entries, $username, true, $autoDescribe);

    // Lưu mốc tiêu đề hiện tại cho lần đồng bộ sau (có khóa flock nên an toàn).
    @file_put_contents($state, json_encode($newTitles, JSON_UNESCAPED_UNICODE), LOCK_EX);

    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, $fingerprint);
    fflush($fh);
    @flock($fh, LOCK_UN);
    fclose($fh);

    return [$result[0], $result[1], $result[2], $deleted];
}
