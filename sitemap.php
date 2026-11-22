<?php
/**
 * Sitemap XML động — truy cập qua /sitemap.xml (rewrite trong .htaccess).
 * Giúp công cụ tìm kiếm và AI crawler phát hiện toàn bộ video.
 */
define('SKIP_AGE_GATE', true);
require_once __DIR__ . '/includes/helpers.php';

header('Content-Type: application/xml; charset=UTF-8');

$origin = site_origin();
$esc = static fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo '  <url><loc>' . $esc($origin . '/') . '</loc><changefreq>daily</changefreq><priority>1.0</priority></url>' . "\n";

$rows = db()->query('SELECT id, created_at FROM videos ORDER BY id DESC LIMIT 5000')->fetchAll();
foreach ($rows as $r) {
    $loc = $origin . '/watch.php?id=' . (int)$r['id'];
    $mod = iso_datetime((string)$r['created_at']);
    echo '  <url><loc>' . $esc($loc) . '</loc>'
       . ($mod !== '' ? '<lastmod>' . $esc($mod) . '</lastmod>' : '')
       . '<changefreq>weekly</changefreq><priority>0.6</priority></url>' . "\n";
}
echo '</urlset>' . "\n";
