<?php
/**
 * Đầu trang dùng chung. Biến tùy chọn trước khi include:
 *   $page_title        (string) — tiêu đề trang
 *   $page_description  (string) — mô tả (meta description / og:description)
 *   $page_canonical    (string) — URL chuẩn (mặc định lấy URL hiện tại)
 *   $og_image          (string) — ảnh đại diện dùng khi chia sẻ (og:image)
 *   $og_type           (string) — website | video (mặc định website)
 *   $ld_json           (array)  — các đối tượng JSON-LD bổ sung cho trang
 *   $q                 (string) — từ khóa tìm kiếm đang áp dụng
 */
require_once __DIR__ . '/helpers.php';

$user    = current_user();
$flashes = take_flashes();
$page_title       = $page_title ?? '';
$page_description = $page_description ?? SITE_NAME . ' — thư viện video theo chuyên mục, cập nhật hằng ngày.';
$page_canonical   = $page_canonical ?? current_url_clean();
$og_image         = $og_image ?? '';
$og_type          = $og_type ?? 'website';
$ld_json          = $ld_json ?? [];
$q                = $q ?? '';

// Dữ liệu có cấu trúc chung cho mọi trang + phần riêng do từng trang truyền vào.
$graph = [[
    '@type'       => 'WebSite',
    '@id'         => site_origin() . '/#website',
    'url'         => site_origin() . '/',
    'name'        => SITE_NAME,
    'description' => $page_description,
    'inLanguage'  => 'vi',
]];
if ($ld_json) {
    $graph = array_merge($graph, $ld_json);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title !== '' ? h($page_title) . ' - ' : '' ?><?= h(SITE_NAME) ?> - Chia sẻ video</title>
  <meta name="description" content="<?= h($page_description) ?>">
  <meta name="robots" content="index,follow,max-image-preview:large">
  <link rel="canonical" href="<?= h($page_canonical) ?>">
  <meta name="rating" content="adult">
  <meta name="age-rating" content="18+">
  <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
  <meta property="og:locale" content="vi_VN">
  <meta property="og:title" content="<?= h($page_title !== '' ? $page_title : SITE_NAME) ?>">
  <meta property="og:description" content="<?= h($page_description) ?>">
  <meta property="og:type" content="<?= h($og_type) ?>">
  <meta property="og:url" content="<?= h($page_canonical) ?>">
  <?php if ($og_image !== ''): ?>
    <meta property="og:image" content="<?= h(to_absolute_asset($og_image)) ?>">
  <?php endif; ?>
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= h($page_title !== '' ? $page_title : SITE_NAME) ?>">
  <meta name="twitter:description" content="<?= h($page_description) ?>">
  <?php if ($og_image !== ''): ?>
    <meta name="twitter:image" content="<?= h(to_absolute_asset($og_image)) ?>">
  <?php endif; ?>
  <?= ld_json_script(['@context' => 'https://schema.org', '@graph' => $graph]) ?>
  <link rel="stylesheet" href="static/css/style.css">

  <!-- Google Tag Manager -->
  <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
  new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
  j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
  'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
  })(window,document,'script','dataLayer','GTM-5TXJ298G');</script>
  <!-- End Google Tag Manager -->
</head>
<body>
  <!-- Google Tag Manager (noscript) -->
  <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5TXJ298G"
  height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
  <!-- End Google Tag Manager (noscript) -->

  <header class="topbar">
    <div class="topbar-inner">
      <a class="logo" href="./">
        <span class="logo-mark">▶</span> SEX<span class="logo-accent">Việt</span>
      </a>

      <form class="search" action="./" method="get">
        <input type="text" name="q" placeholder="Tìm kiếm video..." value="<?= h($q) ?>">
        <button type="submit">🔍 Tìm</button>
      </form>

      <nav class="top-actions">
        <?php if ($user): ?>
          <span class="user-chip">👤 <?= h($user['username']) ?></span>
          <a class="btn btn-primary" href="upload.php">+ Tải video lên</a>
          <a class="btn btn-ghost" href="import.php">📋 Nhập danh sách</a>
          <?php if (is_admin($user)): ?>
            <a class="btn btn-ghost" href="ai-settings.php">🤖 AI</a>
          <?php endif; ?>
          <a class="btn btn-ghost" href="logout.php">Thoát</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <?php if ($flashes): ?>
    <div class="flash-area">
      <?php foreach ($flashes as $f): ?>
        <div class="flash flash-<?= h($f['type']) ?>"><?= h($f['msg']) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <main class="container">