<?php
/**
 * robots.txt động — truy cập qua /robots.txt (rewrite trong .htaccess).
 * Không dùng file tĩnh để Sitemap luôn đúng domain đang dùng.
 */
define('SKIP_AGE_GATE', true);
require_once __DIR__ . '/includes/helpers.php';

header('Content-Type: text/plain; charset=UTF-8');

$sitemap = site_origin() . '/sitemap.xml';

echo "User-agent: *\n";
echo "Allow: /\n\n";

// Khai báo riêng các bot AI/search hay ghé (vẫn nằm trong nhóm * ở trên).
foreach ([
    'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-Web',
    'PerplexityBot', 'Googlebot', 'Google-Extended', 'Bingbot', 'CCBot',
    'Applebot', 'DuckDuckBot', 'facebookexternalhit', 'Twitterbot',
] as $bot) {
    echo "User-agent: $bot\n";
    echo "Allow: /\n\n";
}

echo "Sitemap: $sitemap\n";
