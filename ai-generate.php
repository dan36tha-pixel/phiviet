<?php
/**
 * ai-generate.php — tạo lại mô tả cho 1 video (admin, POST).
 */
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();
if (!is_admin($user)) {
    http_response_code(403);
    exit('Chỉ admin được dùng tính năng này.');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ./');
    exit;
}
csrf_check();

$id = (int)($_POST['id'] ?? 0);
$st = db()->prepare('SELECT id, title, category FROM videos WHERE id = ?');
$st->execute([$id]);
$video = $st->fetch();

if (!$video) {
    flash('Không tìm thấy video.', 'error');
} else {
    $text = ai_describe_video((string)$video['title'], (string)$video['category']);
    if ($text === null) {
        flash('AI không trả được mô tả. Xem data/error.log (tiền tố [AI]).', 'error');
    } else {
        db()->prepare('UPDATE videos SET description = ? WHERE id = ?')->execute([$text, $id]);
        flash('Đã tạo mô tả bằng AI.', 'ok');
    }
}
header('Location: watch.php?id=' . $id);
exit;
