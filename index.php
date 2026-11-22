<?php
/**
 * Trang chủ: lưới video, tìm kiếm, lọc danh mục, sắp xếp.
 */
require_once __DIR__ . '/includes/helpers.php';

// [SỬA] Redirect nếu URL chứa index.html - đặt ngay đầu file
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'index.html') !== false) {
    $clean_url = str_replace('index.html', '', $_SERVER['REQUEST_URI']);
    $clean_url = rtrim($clean_url, '/') . '/';
    header('Location: ' . $clean_url, true, 301);
    exit;
}

// Đồng bộ link.txt vào database (chỉ chạy khi file thay đổi): dòng mới -> thêm,
// dòng đổi link -> cập nhật, dòng bị xóa -> xóa luôn video đó. Sau đó hiển thị
// như video bình thường bên dưới.
try {
    sync_link_file(__DIR__ . '/link.txt');
} catch (Throwable $e) {
    app_log_error($e); // link.txt lỗi thì trang chủ vẫn chạy với dữ liệu cũ
}

$q    = trim($_GET['q'] ?? '');
$cat  = trim($_GET['category'] ?? '');
$sort = (($_GET['sort'] ?? 'moi') === 'xem') ? 'xem' : 'moi';

$sql = "SELECT v.*, u.username,
        (SELECT COUNT(*) FROM likes l WHERE l.video_id = v.id) AS like_count
        FROM videos v JOIN users u ON u.id = v.user_id WHERE 1=1";
$params = [];
if ($q !== '') {
    $sql .= ' AND (v.title LIKE ? OR v.description LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($cat !== '') {
    $sql .= ' AND v.category = ?';
    $params[] = $cat;
}
$sql .= ' ORDER BY ' . ($sort === 'xem' ? 'v.views DESC' : 'v.created_at DESC');

$st = db()->prepare($sql);
$st->execute($params);
$videos = $st->fetchAll();

$user = current_user();
$page_title = 'Trang chủ';
if ($q !== '') {
    $page_description = 'Kết quả tìm kiếm "' . $q . '" tại ' . SITE_NAME . '.';
} elseif ($cat !== '') {
    $page_description = 'Danh sách video ' . $cat . ' tại ' . SITE_NAME . '.';
} else {
    $page_description = SITE_NAME . ' — thư viện video theo chuyên mục, cập nhật hằng ngày.';
}

// ItemList: để AI/search hiểu đây là danh sách video, có tiêu đề + ảnh + link.
$list_items = [];
$i = 1;
foreach (array_slice($videos, 0, 50) as $v) {
    $list_items[] = [
        '@type'    => 'ListItem',
        'position' => $i++,
        'name'     => $v['title'],
        'url'      => to_absolute_asset('watch.php?id=' . (int)$v['id']),
        'image'    => to_absolute_asset(thumb_src($v['thumbnail'])),
    ];
}
$ld_json = [[
    '@type'       => 'CollectionPage',
    'name'        => $page_title,
    'description' => $page_description,
    'url'         => current_url_clean(),
    'mainEntity'  => [
        '@type'           => 'ItemList',
        'numberOfItems'   => count($list_items),
        'itemListElement' => $list_items,
    ],
]];
require __DIR__ . '/includes/header.php';
?>
<!-- [SỬA] Canonical: ép SEO về URL gốc không có index.html -->
<link rel="canonical" href="<?= h(rtrim(to_absolute_asset(''), '/')) ?>/">

<!-- [SỬA] JavaScript redirect mạnh hơn - chạy trước khi trang load xong -->
<script>
(function() {
    var path = window.location.pathname;
    var search = window.location.search;
    // Nếu URL chứa index.html ở bất kỳ vị trí nào
    if (path.indexOf('index.html') !== -1) {
        var newPath = path.replace(/index\.html/g, '').replace(/\/+/g, '/');
        if (newPath === '') newPath = '/';
        window.location.replace(newPath + search);
    }
})();
</script>

<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtm.js?id=G-R0B5BY69VF"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
</script>
<!-- End Google Analytics -->

  <h1 class="sr-only"><?= h(SITE_NAME) ?> — thư viện video<?= $cat !== '' ? ' ' . h($cat) : '' ?></h1>

  <div class="filters">
    <a class="chip <?= $cat === '' ? 'active' : '' ?>"
       href="./?<?= http_build_query(['q' => $q, 'sort' => $sort]) ?>">Tất cả</a>
    <?php foreach (CATEGORIES as $c): ?>
      <a class="chip <?= $cat === $c ? 'active' : '' ?>"
         href="./?<?= http_build_query(['q' => $q, 'category' => $c, 'sort' => $sort]) ?>"><?= h($c) ?></a>
    <?php endforeach; ?>
    <div class="sort-group">
      <a class="chip <?= $sort === 'moi' ? 'active' : '' ?>"
         href="./?<?= http_build_query(['q' => $q, 'category' => $cat, 'sort' => 'moi']) ?>">Mới nhất</a>
      <a class="chip <?= $sort === 'xem' ? 'active' : '' ?>"
         href="./?<?= http_build_query(['q' => $q, 'category' => $cat, 'sort' => 'xem']) ?>">Xem nhiều</a>
    </div>
  </div>

  <?php if ($q !== ''): ?>
    <h2 class="section-title">Kết quả cho "<span><?= h($q) ?></span>"</h2>
  <?php elseif ($cat !== ''): ?>
    <h2 class="section-title">Danh mục: <span><?= h($cat) ?></span></h2>
  <?php endif; ?>

  <?php if ($videos): ?>
    <div class="grid">
      <?php foreach ($videos as $v): ?>
        <a class="card" href="watch.php?id=<?= (int)$v['id'] ?>">
          <div class="thumb-wrap">
            <img src="<?= h(thumb_src($v['thumbnail'])) ?>" alt="<?= h($v['title']) ?>" loading="lazy">
            <span class="views-badge">👁 <?= format_views($v['views']) ?> lượt xem</span>
            <?php if ($v['like_count']): ?>
              <span class="duration-badge">❤ <?= (int)$v['like_count'] ?></span>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <div class="card-title"><?= h($v['title']) ?></div>
            <div class="card-meta">
              <span class="cat"><?= h($v['category']) ?></span>
              <span><?= h($v['username']) ?></span>
              <time datetime="<?= h(iso_datetime($v['created_at'])) ?>"><?= h(time_ago($v['created_at'])) ?></time>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <div class="big">🎬</div>
      <p>Chưa có video nào. Hãy tải video đầu tiên lên!</p>
      <?php if ($user): ?>
        <p style="margin-top:14px"><a class="btn btn-primary" href="upload.php">+ Tải video lên</a></p>
      <?php elseif (file_exists(__DIR__ . '/install.php')
                 && (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0): ?>
        <p style="margin-top:14px"><a class="btn btn-primary" href="install.php">🛠 Cài đặt lần đầu</a></p>
        <p style="color:var(--text-dim); font-size:.85rem; margin-top:8px">
          Tạo tài khoản admin + video mẫu, sau đó xóa file <code>install.php</code>.
        </p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
