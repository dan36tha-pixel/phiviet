<?php
/**
 * install.php — CHẠY MỘT LẦN khi mới deploy, sau đó XÓA FILE NÀY.
 * Hiện form cho bạn tự đặt tài khoản admin, rồi tạo bảng + tài khoản
 * + (tùy chọn) vài video mẫu. Mở trình duyệt: https://ten-mien/install.php
 */
require_once __DIR__ . '/includes/helpers.php';

$page_title = 'Cài đặt VuTube';

// ------------------------------------------------------------------
// Xử lý form khi bấm "Cài đặt"
// ------------------------------------------------------------------
$errors = [];
$done   = false;
$doneLines = [];
$username  = '';
$seed      = '1';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['password2'] ?? '');
    $seed     = isset($_POST['seed']) ? '1' : '';

    if (u_len($username) < 3 || u_len($username) > 30) {
        $errors[] = 'Tên admin phải từ 3 đến 30 ký tự.';
    }
    if (!preg_match('/^[A-Za-z0-9_.\-]+$/', $username)) {
        $errors[] = 'Tên admin chỉ gồm chữ, số, dấu gạch dưới/chanh, chấm.';
    }
    if (u_len($password) < 6) {
        $errors[] = 'Mật khẩu phải từ 6 ký tự trở lên.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Mật khẩu nhập lại không khớp.';
    }

    if (!$errors) {
        try {
            db_init();
            $doneLines[] = '✔ Đã tạo/cập nhật các bảng: users, videos, likes, comments';

            $pdo = db();
            $st = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $st->execute([$username]);
            if ($st->fetch()) {
                $errors[] = "Tài khoản \"$username\" đã tồn tại trong database — chọn tên khác, hoặc đăng nhập thẳng nếu đây là tài khoản của bạn.";
            } else {
                $pdo->prepare('INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, NOW())')
                    ->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
                $doneLines[] = "✔ Đã tạo tài khoản admin: " . h($username);

                if ($seed === '1'
                    && (int)$pdo->query('SELECT COUNT(*) FROM videos')->fetchColumn() === 0) {
                    $adminId = (int)$pdo->lastInsertId();
                    $cats  = CATEGORIES;
                    $titles = ['Video mẫu 1', 'Video mẫu 2', 'Video mẫu 3',
                               'Video mẫu 4', 'Video mẫu 5', 'Video mẫu 6'];
                    $bucket = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/';
                    $files  = ['ForBiggerFun.mp4', 'ForBiggerJoyrides.mp4', 'ForBiggerBlazes.mp4',
                               'ForBiggerEscapes.mp4', 'ForBiggerMeltdowns.mp4', 'ElephantsDream.mp4'];
                    $ins = $pdo->prepare(
                        'INSERT INTO videos (user_id, title, description, category, filename,
                                             external_url, thumbnail, created_at)
                         VALUES (?, ?, ?, ?, NULL, ?, ?, NOW())'
                    );
                    foreach ($titles as $i => $t) {
                        $ins->execute([
                            $adminId, $t,
                            'Video mẫu dùng link mp4 công khai (Google sample bucket).',
                            $cats[$i % count($cats)],
                            $bucket . $files[$i],
                            make_thumb_svg($t),
                        ]);
                    }
                    $doneLines[] = '✔ Đã thêm 6 video mẫu.';
                }
                $done = true;
            }
        } catch (Throwable $e) {
            $errors[] = 'Lỗi database: ' . $e->getMessage();
            $errors[] = '→ Kiểm tra lại DB_HOST / DB_NAME / DB_USER / DB_PASS trong config.php '
                      . '(4 giá trị lấy từ mục "MySQL Databases" trong panel InfinityFree).';
        }
    }
}

// ------------------------------------------------------------------
// Hiển thị
// ------------------------------------------------------------------
require __DIR__ . '/includes/header.php';
?>
  <div class="form-card">
    <h2>🛠 Cài đặt VuTube</h2>

    <?php if ($done): ?>
      <div class="flash flash-ok" style="margin-bottom:14px">
        <?php foreach ($doneLines as $l): ?><div><?= $l ?></div><?php endforeach; ?>
      </div>
      <p style="font-weight:600; margin-bottom:10px">✅ Cài đặt hoàn tất! Bước tiếp theo:</p>
      <ol style="color:var(--text-dim); font-size:.92rem; line-height:1.8; padding-left:20px">
        <li><strong>XÓA file <code>install.php</code> khỏi hosting ngay bây giờ</strong> — để người lạ không chạy lại.</li>
        <li><a style="color:var(--accent)" href="login.php">Đăng nhập</a> bằng tài khoản admin vừa tạo.</li>
        <li>Mở <a style="color:var(--accent)" href="./">trang chủ</a> để kiểm tra.</li>
      </ol>
    <?php else: ?>
      <?php foreach ($errors as $er): ?>
        <div class="flash flash-error"><?= h($er) ?></div>
      <?php endforeach; ?>

      <p style="color:var(--text-dim); font-size:.9rem; line-height:1.5; margin-bottom:6px">
        Đặt tài khoản quản trị cho web. Chỉ chạy một lần; sau khi xong hãy xóa file này.
      </p>

      <form method="post">
        <?= csrf_field() ?>
        <label for="username">Tên admin</label>
        <input type="text" id="username" name="username" required minlength="3" maxlength="30"
               value="<?= h($username) ?>" placeholder="vd: admin">

        <label for="password">Mật khẩu (≥ 6 ký tự)</label>
        <input type="password" id="password" name="password" required minlength="6">

        <label for="password2">Nhập lại mật khẩu</label>
        <input type="password" id="password2" name="password2" required minlength="6">

        <label style="display:flex; align-items:center; gap:8px; margin-top:16px; color:var(--text)">
          <input type="checkbox" name="seed" value="1" <?= $seed === '1' ? 'checked' : '' ?>>
          Thêm 6 video mẫu để trang không trống
        </label>

        <button class="btn btn-primary submit-btn" type="submit">Cài đặt</button>
      </form>
    <?php endif; ?>
  </div>
<?php require __DIR__ . '/includes/footer.php'; ?>
