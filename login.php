<?php
/** Đăng nhập. */
require_once __DIR__ . '/includes/helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $st = db()->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([$username]);
    $row = $st->fetch();

    if ($row && password_verify($password, $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$row['id'];
        flash('Đăng nhập thành công!', 'ok');
        $next = $_GET['next'] ?? '';
        // chỉ chấp nhận đường dẫn nội bộ, chặn redirect ra ngoài
        if (!is_string($next) || $next === ''
            || ($next[0] !== '/' && strpos($next, './') !== 0)
            || strpos($next, '//') === 0 || strpos($next, '\\') !== false
            || strpos($next, '../') !== false) {
            $next = './';
        }
        header('Location: ' . $next);
        exit;
    }
    flash('Sai tên đăng nhập hoặc mật khẩu.', 'error');
}

$page_title = 'Đăng nhập';
require __DIR__ . '/includes/header.php';
?>
  <div class="form-card">
    <h2>🔐 Đăng nhập</h2>
    <form method="post">
      <?= csrf_field() ?>
      <label for="username">Tên đăng nhập</label>
      <input type="text" id="username" name="username" required
             value="<?= h($_POST['username'] ?? '') ?>">

      <label for="password">Mật khẩu</label>
      <input type="password" id="password" name="password" required>

      <button class="btn btn-primary submit-btn" type="submit">Đăng nhập</button>
    </form>
  </div>
<?php require __DIR__ . '/includes/footer.php'; ?>
