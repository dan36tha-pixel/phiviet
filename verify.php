<?php
/**
 * verify.php — Cổng xác nhận 18+ trước khi vào web.
 * Người xem bấm "Tôi đủ 18 tuổi" -> ghi cookie 30 ngày -> vào tiếp trang họ muốn.
 * Khai báo SKIP_AGE_GATE để header.php không chặn lại chính trang này.
 */
define('SKIP_AGE_GATE', true);
require_once __DIR__ . '/includes/helpers.php';

$next = age_gate_safe_next($_POST['next'] ?? ($_GET['next'] ?? './'));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    if (($_POST['age'] ?? '') === 'yes') {
        age_gate_save();
        header('Location: ' . $next);
        exit;
    }
    // Chưa đủ 18 tuổi: đưa ra khỏi web
    header('Location: https://www.google.com/');
    exit;
}

// Đã xác nhận rồi thì không cần xem lại cổng này
if (age_gate_ok()) {
    header('Location: ' . $next);
    exit;
}

$page_title = 'Xác nhận độ tuổi';
require __DIR__ . '/includes/header.php';
?>
  <div class="form-card age-gate">
    <div class="age-gate-badge">🔞</div>
    <h2>Trang web dành cho người từ 18 tuổi</h2>

    <p class="age-gate-text">
      Nội dung trên <strong><?= h(SITE_NAME) ?></strong> chỉ phù hợp với người đã đủ 18 tuổi.
      Bằng cách bấm nút bên dưới, bạn xác nhận rằng bạn đủ 18 tuổi và tự nguyện xem nội dung này.
    </p>

    <form method="post" action="verify.php">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= h($next) ?>">
      <div class="age-gate-actions">
        <button class="btn btn-primary" type="submit" name="age" value="yes">Tôi đủ 18 tuổi</button>
        <button class="btn btn-ghost" type="submit" name="age" value="no">Tôi chưa đủ 18</button>
      </div>
    </form>

    <p class="age-gate-note">
      Lựa chọn của bạn được ghi nhớ <?= (int)AGE_GATE_DAYS ?> ngày trên thiết bị này.
    </p>
  </div>
<?php require __DIR__ . '/includes/footer.php'; ?>
