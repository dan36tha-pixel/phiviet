<?php
/**
 * migrate-categories.php — CHẠY MỘT LẦN sau khi đổi tên chuyên mục trong config.php,
 * sau đó XÓA FILE NÀY.
 *
 * Đổi các video cũ (HỌC TẬP / GIẢI TRÍ / THỂ THAO) sang chuyên mục mới
 * (PHIM VIỆT / SINH VIÊN / CAMERA) để chúng không bị "mất" khỏi bộ lọc.
 * Mở trình duyệt: https://ten-mien/migrate-categories.php
 */
require_once __DIR__ . '/includes/helpers.php';

// Bảng quy đổi: chuyên mục cũ => chuyên mục mới
$map = [
    'HỌC TẬP'  => 'PHIM VIỆT',
    'GIẢI TRÍ' => 'SINH VIÊN',
    'THỂ THAO' => 'CAMERA',
];

$page_title = 'Đổi tên chuyên mục';
$errors = [];
$lines  = [];
$done   = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    try {
        $pdo = db();
        foreach ($map as $old => $new) {
            $st = $pdo->prepare('UPDATE videos SET category = ? WHERE category = ?');
            $st->execute([$new, $old]);
            $n = $st->rowCount();
            $lines[] = "✔ $old → $new: $n video";
        }
        $lines[] = '— Chuyên mục hiện có trong database:';
        $rows = $pdo->query('SELECT category, COUNT(*) AS c FROM videos GROUP BY category ORDER BY category')
                    ->fetchAll();
        foreach ($rows as $r) {
            $lines[] = '• ' . h($r['category']) . ': ' . (int)$r['c'] . ' video';
        }
        $done = true;
    } catch (Throwable $e) {
        $errors[] = 'Lỗi database: ' . $e->getMessage();
    }
}

require __DIR__ . '/includes/header.php';
?>
  <div class="form-card">
    <h2>🔁 Đổi tên chuyên mục</h2>

    <?php if ($done): ?>
      <div class="flash flash-ok" style="margin-bottom:14px">
        <?php foreach ($lines as $l): ?><div><?= $l ?></div><?php endforeach; ?>
      </div>
      <p style="font-weight:600; margin-bottom:10px">✅ Xong! Bước tiếp theo:</p>
      <ol style="color:var(--text-dim); font-size:.92rem; line-height:1.8; padding-left:20px">
        <li><strong>XÓA file <code>migrate-categories.php</code> khỏi hosting</strong>.</li>
        <li>Mở <a style="color:var(--accent)" href="./">trang chủ</a> kiểm tra lại bộ lọc.</li>
      </ol>
    <?php else: ?>
      <?php foreach ($errors as $er): ?>
        <div class="flash flash-error"><?= h($er) ?></div>
      <?php endforeach; ?>

      <p style="color:var(--text-dim); font-size:.9rem; line-height:1.6; margin-bottom:12px">
        Video trong database đang dùng chuyên mục cũ. Bấm nút dưới để chuyển sang tên mới:
      </p>
      <ul style="color:var(--text-dim); font-size:.9rem; line-height:1.9; padding-left:20px; margin-bottom:16px">
        <?php foreach ($map as $old => $new): ?>
          <li><strong><?= h($old) ?></strong> → <strong><?= h($new) ?></strong></li>
        <?php endforeach; ?>
      </ul>

      <form method="post">
        <?= csrf_field() ?>
        <button class="btn btn-primary submit-btn" type="submit">Chuyển chuyên mục</button>
      </form>
    <?php endif; ?>
  </div>
<?php require __DIR__ . '/includes/footer.php'; ?>
