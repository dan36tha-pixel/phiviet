<?php
/**
 * ai-settings.php — nhập API key OpenRouter, test kết nối, tạo mô tả hàng loạt.
 * Chỉ admin (user id 1 hoặc username "admin") truy cập được.
 */
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();
if (!is_admin($user)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="vi"><meta charset="UTF-8"><title>403</title>'
       . '<body style="font-family:system-ui;background:#101014;color:#e8e8ef;padding:40px">'
       . 'Trang này chỉ dành cho quản trị viên. <a href="./">Về trang chủ</a></body></html>';
    exit;
}

$page_title = 'Cài đặt AI';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $key   = trim((string)($_POST['api_key'] ?? ''));
        $model = trim((string)($_POST['model'] ?? ''));
        if ($key !== '')   ai_setting_set('openrouter_api_key', $key);   // để trống = giữ key cũ
        if ($model !== '') ai_setting_set('openrouter_model', $model);
        ai_setting_set('auto_describe', isset($_POST['auto_describe']) ? '1' : '0');
        flash('Đã lưu cấu hình AI.', 'ok');
        header('Location: ai-settings.php');
        exit;
    }

    if ($action === 'test') {
        $text = ai_request('Chỉ trả về đúng chữ: OK', 20);
        if ($text === null) {
            flash('Kết nối OpenRouter thất bại — xem data/error.log (tiền tố [AI]) để biết lý do.', 'error');
        } else {
            flash('Kết nối OK. AI trả: ' . u_substr($text, 0, 80), 'ok');
        }
        header('Location: ai-settings.php');
        exit;
    }

    if ($action === 'batch') {
        $limit = defined('AI_BATCH_LIMIT') ? (int)AI_BATCH_LIMIT : 10;
        $rows = db()->query(
            "SELECT id, title, category FROM videos
             WHERE description IS NULL OR description = ''
             ORDER BY id DESC LIMIT " . $limit
        )->fetchAll();
        $n = 0;
        foreach ($rows as $r) {
            $text = ai_describe_video((string)$r['title'], (string)$r['category']);
            if ($text !== null) {
                db()->prepare('UPDATE videos SET description = ? WHERE id = ?')
                   ->execute([$text, (int)$r['id']]);
                $n++;
            }
        }
        flash("Đã tạo mô tả cho $n / " . count($rows) . " video.", ($n > 0 ? 'ok' : 'error'));
        header('Location: ai-settings.php');
        exit;
    }
}

$curKey  = ai_api_key();
$masked  = $curKey !== '' ? u_substr($curKey, 0, 4) . '••••' . u_substr($curKey, -4) : '(chưa có)';
$model   = ai_model();
$defaultAuto = (defined('AI_AUTO_DESCRIBE') && AI_AUTO_DESCRIBE) ? '1' : '0';
$autoOn  = ai_setting_get('auto_describe', $defaultAuto) === '1';
$missing = (int)db()->query("SELECT COUNT(*) FROM videos WHERE description IS NULL OR description = ''")
                    ->fetchColumn();
$limit   = defined('AI_BATCH_LIMIT') ? (int)AI_BATCH_LIMIT : 10;

require __DIR__ . '/includes/header.php';
?>
  <div class="form-card">
    <h2>🤖 Cài đặt AI (OpenRouter)</h2>
    <p style="color:var(--text-dim); font-size:.92rem; line-height:1.6">
      Key đã được điền sẵn trong <code>config.php</code>. Nếu bạn dán key mới vào ô
      dưới, key đó được lưu trong database và được ưu tiên dùng — đổi key không cần
      sửa code. Chỉ admin thấy key, người xem không bao giờ nhận được key.
    </p>

    <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:8px;
                padding:12px;margin:14px 0;font-size:.9rem;line-height:1.8">
      <div>Trạng thái: <strong style="color:<?= $curKey !== '' ? 'var(--ok)' : '#ff8a3d' ?>">
        <?= $curKey !== '' ? 'Đã có key (' . h($masked) . ')' : 'Chưa có key' ?></strong></div>
      <div>Model: <code><?= h($model) ?></code></div>
      <div>Tự sinh mô tả khi nhập video mới từ <code>link.txt</code>:
        <strong><?= $autoOn ? 'BẬT' : 'TẮT' ?></strong></div>
      <div>Video chưa có mô tả: <strong><?= $missing ?></strong></div>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <label for="api_key">API key OpenRouter</label>
      <input type="password" id="api_key" name="api_key"
             placeholder="<?= $curKey !== '' ? 'Để trống = giữ key cũ' : 'Dán key sk-or-v1-... vào đây' ?>"
             autocomplete="off">
      <div class="hint">Key dạng <code>sk-or-v1-...</code>, lấy tại openrouter.ai/keys.</div>

      <label for="model">Model</label>
      <input type="text" id="model" name="model" value="<?= h($model) ?>"
             placeholder="nvidia/nemotron-3-ultra-550b-a55b:free">
      <div class="hint">Model có đuôi <code>:free</code> là miễn phí. Có thể đổi model khác trên OpenRouter.</div>

      <label style="display:flex;align-items:center;gap:8px;margin-top:12px">
        <input type="checkbox" name="auto_describe" value="1" <?= $autoOn ? 'checked' : '' ?>>
        Tự động sinh mô tả cho video mới (tối đa <?= $limit ?> video mỗi lần đồng bộ)
      </label>

      <button class="btn btn-primary submit-btn" type="submit">💾 Lưu cấu hình</button>
    </form>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="test">
        <button class="btn btn-ghost" type="submit">🔌 Test kết nối</button>
      </form>
      <form method="post" onsubmit="return confirm('Tạo mô tả cho tối đa <?= $limit ?> video chưa có mô tả?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="batch">
        <button class="btn btn-ghost" type="submit">✨ Tạo mô tả hàng loạt (<?= $limit ?>)</button>
      </form>
    </div>

    <p style="color:var(--text-dim);font-size:.85rem;line-height:1.6;margin-top:16px">
      Mỗi video cũng có nút <strong>✨ Tạo mô tả AI</strong> riêng trên trang xem.
      Nếu AI từ chối nội dung nhạy cảm hoặc hết hạn mức, mô tả cũ được giữ nguyên — xem
      <code>data/error.log</code> (có tiền tố <code>[AI]</code>) để biết lý do.
    </p>
  </div>
<?php require __DIR__ . '/includes/footer.php'; ?>
