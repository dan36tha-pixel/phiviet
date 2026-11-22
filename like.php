<?php
/** Thích / bỏ thích video (POST). */
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ./');
    exit;
}
csrf_check();

$id = (int)($_GET['id'] ?? 0);
$pdo = db();

$st = $pdo->prepare('SELECT 1 FROM likes WHERE user_id = ? AND video_id = ?');
$st->execute([$user['id'], $id]);
if ($st->fetch()) {
    $pdo->prepare('DELETE FROM likes WHERE user_id = ? AND video_id = ?')
        ->execute([$user['id'], $id]);
} else {
    $pdo->prepare('INSERT INTO likes (user_id, video_id) VALUES (?, ?)')
        ->execute([$user['id'], $id]);
}
header('Location: watch.php?id=' . $id);
exit;
