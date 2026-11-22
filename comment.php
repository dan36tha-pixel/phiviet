<?php
/** Thêm bình luận (POST). */
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ./');
    exit;
}
csrf_check();

$id = (int)($_GET['id'] ?? 0);
$body = trim($_POST['body'] ?? '');
if ($body !== '' && $id > 0) {
    db()->prepare('INSERT INTO comments (user_id, video_id, body, created_at) VALUES (?, ?, ?, NOW())')
        ->execute([$user['id'], $id, $body]);
}
header('Location: watch.php?id=' . $id);
exit;
