<?php
/**
 * Đăng ký tài khoản mới — ĐÃ KHÓA.
 * Web chỉ dùng một tài khoản admin (tạo qua install.php). Người lạ vào thẳng
 * URL cũng không đăng ký được. Muốn mở lại: đổi REGISTER_OPEN thành true.
 */
define('REGISTER_OPEN', false);

require_once __DIR__ . '/includes/helpers.php';

if (!REGISTER_OPEN) {
    flash('Trang đăng ký đã đóng.', 'error');
    header('Location: login.php');
    exit;
}

$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (u_len($username) < 3 || u_len($password) < 6) {
        flash('Tên ≥ 3 ký tự, mật khẩu ≥ 6 ký tự.', 'error');
    } else {
        try {
            $pdo->prepare('INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, NOW())')
                ->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            flash('Đăng ký thành công!', 'ok');
            header('Location: ./');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                flash('Tên đăng nhập đã tồn tại.', 'error');
            } else {
                flash('Lỗi hệ thống, thử lại sau.', 'error');
            }
        }
    }
}

$page_title = 'Đăng ký';
require __DIR__ . '/includes/header.php';
?>
  <div class="form-card">
    <h2>📝 Tạo tài khoản</h2>
    <form method="post">
      <?= csrf_field() ?>
      <label for="username">Tên đăng nhập</label>
      <input type="text" id="username" name="username" required minlength="3" maxlength="30"
             value="<?= h($_POST['username'] ?? '') ?>">

      <label for="password">Mật khẩu</label>
      <input type="password" id="password" name="password" required minlength="6">

      <button class="btn btn-primary submit-btn" type="submit">Đăng ký</button>
    </form>
    <p class="alt-link">Đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
  </div>
<?php require __DIR__ . '/includes/footer.php'; ?>
