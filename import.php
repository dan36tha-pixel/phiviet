<?php
/**
 * Nhập danh sách video từ file .txt (format giống dangbai.txt của bản Python).
 */
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $file = $_FILES['listfile'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['name'] === '') {
        flash('Vui lòng chọn file danh sách.', 'error');
    } else {
        $text = file_get_contents($file['tmp_name']) ?: '';
        $entries = parse_list_text($text);
        [$added, $skipped] = import_entries($entries, $user['username']);
        if ($added) {
            flash("Đã nhập $added video (bỏ qua $skipped video trùng).", 'ok');
        } else {
            flash('Không có video mới nào được nhập.', 'error');
        }
    }
    header('Location: ./');
    exit;
}

require __DIR__ . '/includes/header.php';
?>
  <div class="form-card">
    <h2>📋 Nhập danh sách video</h2>
    <p style="color:var(--text-dim); font-size:.9rem; line-height:1.5">
      Tải lên file danh sách theo format (mỗi dòng 1 video):
    </p>
    <pre style="background:var(--bg-2); border:1px solid var(--border); border-radius:8px;
                padding:12px; font-size:.82rem; overflow-x:auto; margin:12px 0">Link ảnh|Tiêu đề|Mô tả|width|height|&lt;HTML nhúng&gt;

# ---------- TÊN CHUYÊN MỤC ----------
# Dòng bắt đầu bằng # là comment, bỏ qua
# Link ảnh: URL https... hoặc đường dẫn /... (để trống -&gt; tự sinh thumbnail)
# HTML nhúng: &lt;video ... data-m3u8Url='...'&gt; hoặc &lt;iframe src="..."&gt;</pre>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <label for="listfile">Chọn file danh sách (.txt)</label>
      <input type="file" id="listfile" name="listfile" accept=".txt" required>
      <button class="btn btn-primary submit-btn" type="submit">Nhập vào web</button>
    </form>
  </div>
<?php require __DIR__ . '/includes/footer.php'; ?>
