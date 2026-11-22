<?php
/** Xóa video của chính mình (POST) — dùng chung helper xóa likes/comments/file media. */
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ./');
    exit;
}
csrf_check();

$id = (int)($_GET['id'] ?? 0);
$pdo = db();

$st = $pdo->prepare('SELECT * FROM videos WHERE id = ?');
$st->execute([$id]);
$video = $st->fetch();

if ($video && (int)$video['user_id'] === (int)$user['id']) {
    delete_video_row($id); // xóa likes/comments/file media + bản ghi video
    flash('Đã xóa video.', 'ok');
}
header('Location: ./');
exit;
