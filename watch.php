<?php
/**
 * Trang xem video: player, lượt xem, thích, bình luận, video liên quan.
 */
require_once __DIR__ . '/includes/helpers.php';

$id  = (int)($_GET['id'] ?? 0);
$pdo = db();

$st = $pdo->prepare(
    'SELECT v.*, u.username FROM videos v JOIN users u ON u.id = v.user_id WHERE v.id = ?'
);
$st->execute([$id]);
$video = $st->fetch();

if (!$video) {
    http_response_code(404);
    $page_title = 'Không tìm thấy video';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><div class="big">🔍</div><p>Video không tồn tại hoặc đã bị xóa.</p>'
       . '<p style="margin-top:14px"><a class="btn btn-primary" href="./">Về trang chủ</a></p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$user = current_user();
$can_edit = $user && (int)$video['user_id'] === (int)$user['id'];

// ----- Đổi ảnh đại diện (thumbnail) cho bài viết: file tải lên hoặc link ảnh -----
if ($can_edit && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'thumb') {
    csrf_check();
    $thumbFile = $_FILES['thumbnail'] ?? null;
    $thumbUrl  = trim($_POST['thumbnail_url'] ?? '');
    $newThumb  = null;
    $ok = true;

    if ($thumbFile && $thumbFile['error'] === UPLOAD_ERR_OK && $thumbFile['name'] !== '') {
        $ext = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));
        if (!in_array('.' . $ext, ['.jpg', '.jpeg', '.png', '.gif', '.webp'], true)) {
            flash('Định dạng ảnh không hỗ trợ (jpg, png, webp, gif).', 'error');
            $ok = false;
        } elseif ($thumbFile['size'] > MAX_IMAGE_MB * 1024 * 1024) {
            flash('Ảnh vượt quá giới hạn ' . MAX_IMAGE_MB . 'MB.', 'error');
            $ok = false;
        } else {
            $newThumb = safe_filename($thumbFile['name']);
            if (!move_uploaded_file($thumbFile['tmp_name'], THUMB_DIR . '/' . $newThumb)) {
                flash('Không thể lưu ảnh vào thư mục data/thumbs.', 'error');
                $newThumb = null;
                $ok = false;
            }
        }
    } elseif ($thumbUrl !== '') {
        if (!preg_match('#^https?://#i', $thumbUrl) || u_len($thumbUrl) > 500) {
            flash('Link ảnh không hợp lệ (phải là http/https).', 'error');
            $ok = false;
        } else {
            $newThumb = $thumbUrl; // ảnh từ URL ngoài, không tải về máy
        }
    } else {
        flash('Chưa chọn ảnh: hãy tải file ảnh lên hoặc dán link ảnh.', 'error');
        $ok = false;
    }

    if ($ok && $newThumb !== null) {
        // Xóa file thumbnail cũ nếu nó nằm trong data/thumbs (không phải URL ngoài)
        $old = (string)$video['thumbnail'];
        $oldPath = ($old !== '' && stripos($old, 'http') !== 0 && $old[0] !== '/')
            ? (realpath(THUMB_DIR . '/' . $old) ?: '')
            : '';
        if ($oldPath !== '' && strpos($oldPath, realpath(THUMB_DIR) ?: "\0none\0") === 0) {
            @unlink($oldPath);
        }
        $pdo->prepare('UPDATE videos SET thumbnail = ? WHERE id = ?')
            ->execute([$newThumb, $id]);
        flash('Đã cập nhật ảnh đại diện cho bài viết!', 'ok');
        header('Location: watch.php?id=' . $id);
        exit;
    }
    // Lỗi -> hiện lại trang kèm thông báo flash
}

// Đếm lượt xem (như bản gốc: đếm mỗi lần mở trang)
$pdo->prepare('UPDATE videos SET views = views + 1 WHERE id = ?')->execute([$id]);

$liked = false;
if ($user) {
    $st = $pdo->prepare('SELECT 1 FROM likes WHERE user_id = ? AND video_id = ?');
    $st->execute([$user['id'], $id]);
    $liked = (bool)$st->fetch();
}
$like_count = (int)$pdo->query('SELECT COUNT(*) FROM likes WHERE video_id = ' . (int)$id)->fetchColumn();

$st = $pdo->prepare(
    'SELECT c.*, u.username FROM comments c JOIN users u ON u.id = c.user_id
     WHERE c.video_id = ? ORDER BY c.created_at DESC'
);
$st->execute([$id]);
$comments = $st->fetchAll();

$st = $pdo->prepare(
    'SELECT * FROM videos WHERE category = ? AND id != ? ORDER BY views DESC LIMIT 8'
);
$st->execute([$video['category'], $id]);
$related = $st->fetchAll();

// Player: embed HTML nguyên trạng (chỉ nạp embed do chính bạn soạn — cẩn thận XSS)
if (!empty($video['embed_html'])) {
    $player = $video['embed_html'];
} elseif ($video['filename']) {
    $src = 'data/videos/' . rawurlencode($video['filename']);
    $poster = h(thumb_src($video['thumbnail']));
    $player = '<video controls autoplay poster="' . $poster . '" src="' . h($src) . '"></video>';
} elseif ($video['external_url']) {
    $poster = h(thumb_src($video['thumbnail']));
    $player = '<video controls autoplay poster="' . $poster . '" src="' . h($video['external_url']) . '"></video>';
} else {
    $player = '<div class="empty-state"><p>Video không khả dụng.</p></div>';
}

$page_title = $video['title'];
$og_image = thumb_src($video['thumbnail']);
$og_type = 'video';
$page_description = $video['description'] !== ''
    ? u_substr($video['description'], 0, 160)
    : $video['title'] . ' — xem tại ' . SITE_NAME . '.';
$page_canonical = to_absolute_asset('watch.php?id=' . $id);

// VideoObject: giúp AI/search hiểu đây là 1 video, có ảnh, ngày đăng, lượt xem.
$video_json = [
    '@type'        => 'VideoObject',
    'name'         => $video['title'],
    'description'  => $page_description,
    'thumbnailUrl' => to_absolute_asset($og_image),
    'uploadDate'   => iso_datetime($video['created_at']),
    'genre'        => $video['category'],
    'author'       => ['@type' => 'Person', 'name' => $video['username']],
    'interactionStatistic' => [
        ['@type' => 'InteractionCounter',
         'interactionType' => 'https://schema.org/WatchAction',
         'userInteractionCount' => (int)$video['views'] + 1],
        ['@type' => 'InteractionCounter',
         'interactionType' => 'https://schema.org/LikeAction',
         'userInteractionCount' => $like_count],
    ],
];
$embed_url = extract_embed_url((string)($video['embed_html'] ?? ''));
if ($embed_url) $video_json['embedUrl'] = $embed_url;
if ($video['external_url']) $video_json['contentUrl'] = $video['external_url'];
$ld_json = [$video_json];

require __DIR__ . '/includes/header.php';
?>
  <div class="watch-layout">
    <div class="watch-main">
      <div class="player-wrap"><?= $player ?></div>

      <h1 class="watch-title"><?= h($video['title']) ?></h1>
      <div class="watch-meta">
        👁 <?= format_views($video['views']) ?> lượt xem ·
        <time datetime="<?= h(iso_datetime($video['created_at'])) ?>"><?= h(time_ago($video['created_at'])) ?></time> ·
        danh mục <a style="color:var(--accent-2)"
                    href="./?<?= http_build_query(['category' => $video['category']]) ?>"><?= h($video['category']) ?></a>
      </div>

      <!-- Ảnh đại diện của bài viết -->
      <figure class="post-thumb">
        <img src="<?= h(thumb_src($video['thumbnail'])) ?>" alt="Ảnh đại diện: <?= h($video['title']) ?>" loading="lazy">
        <figcaption>🖼 Ảnh đại diện bài viết</figcaption>
        <?php if ($can_edit): ?>
          <details class="thumb-edit">
            <summary>✏️ Đổi ảnh đại diện</summary>
            <form method="post" enctype="multipart/form-data">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="thumb">
              <label for="thumbnail">Tải file ảnh lên</label>
              <input type="file" id="thumbnail" name="thumbnail" accept="image/*">
              <div class="hint">jpg, png, webp, gif — tối đa <?= MAX_IMAGE_MB ?>MB.</div>

              <label for="thumbnail_url">…hoặc dán link ảnh</label>
              <input type="url" id="thumbnail_url" name="thumbnail_url" placeholder="https://.../anh.jpg">
              <div class="hint">Ưu tiên file tải lên nếu cả hai đều điền.</div>

              <button class="btn btn-primary" type="submit">Lưu ảnh đại diện</button>
            </form>
          </details>
        <?php endif; ?>
      </figure>

      <div class="watch-actions">
        <?php if ($user): ?>
          <form method="post" action="like.php?id=<?= $id ?>">
            <?= csrf_field() ?>
            <button class="btn <?= $liked ? 'btn-primary' : 'btn-outline' ?>" type="submit">
              <?= $liked ? '❤ Đã thích' : '♡ Thích' ?> · <?= $like_count ?>
            </button>
          </form>
          <?php if ((int)$video['user_id'] === (int)$user['id']): ?>
            <form method="post" action="delete.php?id=<?= $id ?>"
                  onsubmit="return confirm('Xóa video này?')">
              <?= csrf_field() ?>
              <button class="btn btn-ghost" type="submit">🗑 Xóa video</button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="desc-box">
        <strong><?= h($video['username']) ?></strong>
        <?= $video['description'] !== '' ? h($video['description']) : '(Chưa có mô tả)' ?>
      </div>

      <?php if (is_admin($user)): ?>
        <form method="post" action="ai-generate.php" style="margin-top:10px">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <button class="btn btn-ghost" type="submit">✨ Tạo mô tả AI</button>
        </form>
      <?php endif; ?>

      <div class="comments">
        <h3>💬 <?= count($comments) ?> bình luận</h3>
        <?php if ($user): ?>
          <form class="comment-form" method="post" action="comment.php?id=<?= $id ?>">
            <?= csrf_field() ?>
            <textarea name="body" placeholder="Viết bình luận..." required></textarea>
            <button class="btn btn-primary" type="submit">Gửi</button>
          </form>
        <?php endif; ?>

        <?php if ($comments): ?>
          <?php foreach ($comments as $c): ?>
            <div class="comment">
              <span class="who"><?= h($c['username']) ?></span>
              <span class="when"><?= h(time_ago($c['created_at'])) ?></span>
              <p><?= h($c['body']) ?></p>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="color:var(--text-dim)">Chưa có bình luận nào.</p>
        <?php endif; ?>
      </div>
    </div>

    <aside>
      <h3 class="section-title" style="font-size:1.05rem">Video liên quan</h3>
      <div class="side-list">
        <?php if ($related): ?>
          <?php foreach ($related as $v): ?>
            <a class="side-item" href="watch.php?id=<?= (int)$v['id'] ?>">
              <img class="s-thumb" src="<?= h(thumb_src($v['thumbnail'])) ?>" alt="<?= h($v['title']) ?>">
              <div>
                <div class="s-title"><?= h($v['title']) ?></div>
                <div class="s-meta">👁 <?= format_views($v['views']) ?> · <?= h($v['username'] ?? '') ?></div>
              </div>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="color:var(--text-dim)">Chưa có video liên quan.</p>
        <?php endif; ?>
      </div>
    </aside>
  </div>

  <?php if (!empty($video['embed_html']) && stripos($video['embed_html'], 'data-m3u8Url') !== false): ?>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1"></script>
    <script>
      document.querySelectorAll("video[data-m3u8Url], video[data-m3u8url]").forEach(function (v) {
        var url = v.dataset.m3u8Url || v.dataset.m3u8url;
        if (!url) return;
        if (/\.mp4($|\?)/i.test(url)) { v.src = url; return; }
        if (window.Hls && Hls.isSupported()) {
          var hls = new Hls();
          hls.loadSource(url);
          hls.attachMedia(v);
        } else if (v.canPlayType("application/vnd.apple.mpegurl")) {
          v.src = url; // Safari hỗ trợ HLS sẵn
        } else {
          v.replaceWith(Object.assign(document.createElement("div"),
            { textContent: "Trình duyệt không hỗ trợ phát video này." }));
        }
      });
    </script>
  <?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
