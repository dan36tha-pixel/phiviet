<?php
/**
 * Tải video lên: file (nhỏ — host free giới hạn ~10MB) hoặc link ngoài,
 * kèm thumbnail tùy chọn; không có thumb thì tự sinh SVG.
 */
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();
$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category    = $_POST['category'] ?? '';
    $externalUrl = trim($_POST['external_url'] ?? '');
    $videoFile   = $_FILES['video'] ?? null;
    $thumbFile   = $_FILES['thumbnail'] ?? null;

    $ok = true;
    if ($title === '') {
        flash('Vui lòng nhập tiêu đề.', 'error');
        $ok = false;
    }
    if (!in_array($category, CATEGORIES, true)) {
        $category = CATEGORIES[0];
    }

    $filename = null;
    if ($ok && $videoFile && $videoFile['error'] !== UPLOAD_ERR_NO_FILE && $videoFile['name'] !== '') {
        $ext = strtolower(pathinfo($videoFile['name'], PATHINFO_EXTENSION));
        if (!in_array('.' . $ext, ['.mp4', '.webm', '.mkv', '.mov'], true)) {
            flash('Định dạng video không hỗ trợ (mp4, webm, mkv, mov).', 'error');
            $ok = false;
        } elseif ($videoFile['error'] !== UPLOAD_ERR_OK) {
            flash('Tải file video lên thất bại (mã lỗi ' . (int)$videoFile['error'] . ').', 'error');
            $ok = false;
        } elseif ($videoFile['size'] > MAX_VIDEO_MB * 1024 * 1024) {
            flash('File video vượt quá giới hạn ' . MAX_VIDEO_MB . 'MB của host. Hãy dùng link video bên ngoài.', 'error');
            $ok = false;
        } else {
            $filename = safe_filename($videoFile['name']);
            if (!move_uploaded_file($videoFile['tmp_name'], VIDEO_DIR . '/' . $filename)) {
                flash('Không thể lưu file video vào thư mục data/videos.', 'error');
                $filename = null;
                $ok = false;
            }
        }
    }
    if ($ok && !$filename && $externalUrl === '') {
        flash('Cần tải lên file video hoặc dán link video.', 'error');
        $ok = false;
    }

    $thumbName = null;
    if ($ok && $thumbFile && $thumbFile['error'] === UPLOAD_ERR_OK && $thumbFile['name'] !== '') {
        $ext = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));
        if (in_array('.' . $ext, ['.jpg', '.jpeg', '.png', '.gif', '.webp'], true)
            && $thumbFile['size'] <= MAX_IMAGE_MB * 1024 * 1024) {
            $thumbName = safe_filename($thumbFile['name']);
            if (!move_uploaded_file($thumbFile['tmp_name'], THUMB_DIR . '/' . $thumbName)) {
                $thumbName = null;
            }
        }
    }
    if ($ok && !$thumbName) {
        $thumbName = make_thumb_svg($title); // không có ffmpeg trên host free -> SVG
    }

    if ($ok) {
        $pdo->prepare(
            'INSERT INTO videos (user_id, title, description, category, filename,
                                 external_url, thumbnail, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([$user['id'], $title, $description, $category,
                    $filename, $externalUrl ?: null, $thumbName]);
        flash('Tải video lên thành công!', 'ok');
        header('Location: ./');
        exit;
    }
    // lỗi -> rơi xuống hiển thị lại form (flash đã gửi thông báo)
}

require __DIR__ . '/includes/header.php';
?>
  <div class="form-card">
    <h2>⬆️ Tải video lên</h2>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <label for="title">Tiêu đề *</label>
      <input type="text" id="title" name="title" required maxlength="150"
             value="<?= h($_POST['title'] ?? '') ?>">

      <label for="description">Mô tả</label>
      <textarea id="description" name="description" rows="4"><?= h($_POST['description'] ?? '') ?></textarea>

      <label for="category">Danh mục</label>
      <select id="category" name="category">
        <?php foreach (CATEGORIES as $c): ?>
          <option value="<?= h($c) ?>" <?= (($_POST['category'] ?? '') === $c) ? 'selected' : '' ?>><?= h($c) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="video">File video</label>
      <input type="file" id="video" name="video" accept="video/*">
      <div class="hint">Hỗ trợ: mp4, webm, mkv, mov — tối đa <?= MAX_VIDEO_MB ?>MB (giới hạn host).</div>

      <label for="external_url">…hoặc link video bên ngoài</label>
      <input type="url" id="external_url" name="external_url"
             value="<?= h($_POST['external_url'] ?? '') ?>" placeholder="https://.../video.mp4">
      <div class="hint">Nhập link trực tiếp tới file video nếu không tải file lên — cách khuyên dùng trên host free.</div>

      <label for="thumbnail">Ảnh thumbnail</label>
      <input type="file" id="thumbnail" name="thumbnail" accept="image/*">
      <div class="hint">Không bắt buộc. jpg, png, webp, gif. Bỏ trống sẽ tự sinh ảnh SVG theo tiêu đề.</div>

      <button class="btn btn-primary submit-btn" type="submit">Đăng tải</button>
    </form>
  </div>
<?php require __DIR__ . '/includes/footer.php'; ?>
