#!/usr/bin/env node
/**
 * build-static.mjs — xuất bản tĩnh (HTML/CSS/JS) từ link.txt để chạy trên GitHub Pages.
 *
 * GitHub Pages KHÔNG chạy PHP và không có MySQL, nên script này đọc link.txt
 * (nguồn dữ liệu duy nhất của site) rồi sinh ra:
 *   dist/index.html            — lưới video + tìm kiếm/lọc/sắp xếp bằng JS
 *   dist/watch/<slug>.html     — trang xem từng video (player + mô tả + liên quan)
 *   dist/404.html              — trang không tìm thấy
 *   dist/robots.txt, sitemap.xml, llms.txt
 *   dist/static/...            — CSS/JS/ảnh, kèm thumbnail SVG sinh tự động
 *
 * Chạy:  node tools/build-static.mjs
 *        BASE_URL=https://user.github.io/repo node tools/build-static.mjs
 *
 * Lưu ý: bản tĩnh chỉ để xem. Like, bình luận, đăng nhập, upload và AI
 * chỉ có trên bản PHP chạy hosting (InfinityFree...).
 */

import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(ROOT, 'dist');
const BASE_URL = (process.env.BASE_URL || 'https://dan36tha-pixel.github.io/phiviet').replace(/\/+$/, '');
const BUILD_DATE = new Date().toISOString().slice(0, 10);

/* ------------------------------------------------------------------ */
/* Cấu hình: đọc từ config.php để không phải sửa 2 nơi                 */
/* ------------------------------------------------------------------ */
function readConfig() {
  const file = path.join(ROOT, 'config.php');
  const src = fs.existsSync(file) ? fs.readFileSync(file, 'utf8') : '';

  const nameMatch = src.match(/define\(\s*'SITE_NAME'\s*,\s*'([^']*)'\s*\)/);
  const catMatch = src.match(/define\(\s*'CATEGORIES'\s*,\s*\[([\s\S]*?)\]\s*\)/);
  const categories = catMatch ? [...catMatch[1].matchAll(/'([^']*)'/g)].map((m) => m[1]) : [];

  return {
    siteName: nameMatch ? nameMatch[1] : 'Sex Việt',
    categories: categories.length ? categories : ['PHIM VIỆT', 'SINH VIÊN', 'CAMERA', 'PHIM AI'],
  };
}

const cfg = readConfig();
const SITE_NAME = cfg.siteName;
const CATEGORIES = cfg.categories;
const DEFAULT_CATEGORY = CATEGORIES[CATEGORIES.length - 1];
const SITE_DESCRIPTION = `${SITE_NAME} — thư viện video theo chuyên mục, cập nhật hằng ngày.`;

/* ------------------------------------------------------------------ */
/* Tiện ích                                                            */
/* ------------------------------------------------------------------ */
const esc = (s) =>
  String(s ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

const abs = (p) => `${BASE_URL}/${String(p).replace(/^\/+/, '')}`;

/** src ảnh trong HTML: URL ngoài giữ nguyên, file nội bộ thêm tiền tố tương đối. */
const thumbHref = (v, rel) =>
  /^(https?:\/\/|\/)/i.test(v.thumbSrc) ? v.thumbSrc : rel + v.thumbSrc;

function ldScript(data) {
  const json = JSON.stringify(data)
    .replace(/</g, '\\u003c')
    .replace(/>/g, '\\u003e')
    .replace(/&/g, '\\u0026');
  return `<script type="application/ld+json">${json}</script>`;
}

function slugify(text) {
  const slug = text
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'D')
    .replace(/[^a-zA-Z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .toLowerCase();

  if (slug.length <= 70) return slug;
  // Cắt ở ranh giới từ để URL không bị cụt giữa chừng
  const cut = slug.slice(0, 70);
  const lastDash = cut.lastIndexOf('-');
  return (lastDash > 30 ? cut.slice(0, lastDash) : cut).replace(/-+$/g, '');
}

/* ------------------------------------------------------------------ */
/* Đọc link.txt (giống parse_list_text() trong includes/helpers.php)    */
/* ------------------------------------------------------------------ */
function parseLinkTxt(text) {
  const entries = [];
  let category = null;

  for (const raw of text.split(/\r?\n/)) {
    const line = raw.trim();
    if (line === '') continue;

    // "# ---------- TÊN CHUYÊN MỤC ----------" -> đổi chuyên mục hiện tại
    const catMatch = line.match(/^#\s*-{2,}\s*(.+?)\s*-{2,}\s*$/u);
    if (catMatch) {
      category = catMatch[1].trim();
      continue;
    }
    if (line[0] === '#') continue; // dòng comment

    const parts = line.split('|');
    if (parts.length < 6) continue; // thiếu cột -> bỏ qua

    const name = parts[0].trim();
    const title = parts[1].trim();
    const description = parts[2].trim();
    const embedHtml = parts.slice(5).join('|').trim();

    entries.push({
      title: title || name || 'Không tiêu đề',
      description,
      category: category || DEFAULT_CATEGORY,
      embedHtml,
      // Chỉ coi là ảnh khi là URL tuyệt đối hoặc đường dẫn gốc (như PHP)
      thumb: /^(https?:\/\/|\/)/i.test(name) ? name : '',
    });
  }
  return entries;
}

/** Rút URL phát video khỏi HTML nhúng (data-m3u8Url -> iframe src -> video src). */
function extractEmbedUrl(embed) {
  const patterns = [
    /data-m3u8Url\s*=\s*['"]([^'"]+)['"]/i,
    /<iframe[^>]+src\s*=\s*['"]([^'"]+)['"]/i,
    /<video[^>]+src\s*=\s*['"]([^'"]+)['"]/i,
  ];
  for (const re of patterns) {
    const m = embed.match(re);
    if (m) return m[1];
  }
  return null;
}

/** Thumbnail SVG gradient theo tiêu đề (giống make_thumb_svg() bên PHP). */
function makeThumbSvg(title, targetFile) {
  const hash = crypto.createHash('md5').update(title, 'utf8').digest('hex');
  const hue1 = (parseInt(hash.slice(0, 2), 16) * 360) / 255;
  const hue2 = (parseInt(hash.slice(2, 4), 16) * 360) / 255;
  const label = esc([...title].slice(0, 40).join(''));

  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="hsl(${hue1.toFixed(1)},55%,28%)"/>
      <stop offset="1" stop-color="hsl(${hue2.toFixed(1)},60%,14%)"/>
    </linearGradient>
  </defs>
  <rect width="640" height="360" fill="url(#g)"/>
  <circle cx="320" cy="168" r="54" fill="rgba(255,92,57,.92)"/>
  <polygon points="303,142 303,194 350,168" fill="#ffffff"/>
  <text x="320" y="286" text-anchor="middle" font-family="Segoe UI, sans-serif"
        font-size="24" font-weight="600" fill="rgba(255,255,255,.85)">${label}</text>
</svg>`;

  fs.mkdirSync(path.dirname(targetFile), { recursive: true });
  fs.writeFileSync(targetFile, svg, 'utf8');
}

/* ------------------------------------------------------------------ */
/* Khung HTML dùng chung                                               */
/* ------------------------------------------------------------------ */
function pageShell(opts) {
  const {
    rel, // '' cho trang gốc, '../' cho trang trong watch/
    title,
    description,
    canonical,
    ogImage,
    ogType = 'website',
    ld,
    body,
    needHls = false,
  } = opts;

  const fullTitle = title ? `${title} - ${SITE_NAME}` : `${SITE_NAME} - Chia sẻ video`;

  const head = [
    '<meta charset="UTF-8">',
    '<meta name="viewport" content="width=device-width, initial-scale=1.0">',
    `<title>${esc(fullTitle)}</title>`,
    `<meta name="description" content="${esc(description)}">`,
    '<meta name="robots" content="index,follow,max-image-preview:large">',
    `<link rel="canonical" href="${esc(canonical)}">`,
    '<meta name="rating" content="adult">',
    '<meta name="age-rating" content="18+">',
    `<meta property="og:site_name" content="${esc(SITE_NAME)}">`,
    '<meta property="og:locale" content="vi_VN">',
    `<meta property="og:title" content="${esc(title || SITE_NAME)}">`,
    `<meta property="og:description" content="${esc(description)}">`,
    `<meta property="og:type" content="${esc(ogType)}">`,
    `<meta property="og:url" content="${esc(canonical)}">`,
    ogImage ? `<meta property="og:image" content="${esc(ogImage)}">` : '',
    '<meta name="twitter:card" content="summary_large_image">',
    `<meta name="twitter:title" content="${esc(title || SITE_NAME)}">`,
    `<meta name="twitter:description" content="${esc(description)}">`,
    ogImage ? `<meta name="twitter:image" content="${esc(ogImage)}">` : '',
    ldScript({ '@context': 'https://schema.org', '@graph': ld }),
    `<link rel="stylesheet" href="${rel}static/css/style.css">`,
    `<link rel="stylesheet" href="${rel}static/css/static.css">`,
  ]
    .filter(Boolean)
    .join('\n  ');

  return `<!DOCTYPE html>
<html lang="vi">
<head>
  ${head}
</head>
<body>
  <header class="topbar">
    <div class="topbar-inner">
      <a class="logo" href="${rel}index.html">
        <span class="logo-mark">▶</span> SEX<span class="logo-accent">Việt</span>
      </a>

      <form class="search" action="${rel}index.html" method="get" data-search-form>
        <input type="text" name="q" placeholder="Tìm kiếm video..." value="">
        <button type="submit">🔍 Tìm</button>
      </form>

      <nav class="top-actions">
        <a class="btn btn-ghost" href="${rel}index.html">Trang chủ</a>
      </nav>
    </div>
  </header>

  <main class="container">
${body}
  </main>

  <footer class="footer">
    <p>${esc(SITE_NAME)} — góc thư giãn của người Việt. Vui lòng chỉ đăng nội dung bạn có quyền chia sẻ.</p>
  </footer>

  <div class="age-gate-overlay" id="ageGate" hidden>
    <div class="form-card age-gate">
      <div class="age-gate-badge">🔞</div>
      <h2>Trang web dành cho người từ 18 tuổi</h2>
      <p class="age-gate-text">
        Nội dung trên <strong>${esc(SITE_NAME)}</strong> chỉ phù hợp với người đã đủ 18 tuổi.
        Bằng cách bấm nút bên dưới, bạn xác nhận rằng bạn đủ 18 tuổi và tự nguyện xem nội dung này.
      </p>
      <div class="age-gate-actions">
        <button class="btn btn-primary" type="button" data-age-yes>Tôi đủ 18 tuổi</button>
        <button class="btn btn-ghost" type="button" data-age-no>Tôi chưa đủ 18</button>
      </div>
      <p class="age-gate-note">Lựa chọn của bạn được ghi nhớ trên thiết bị này.</p>
    </div>
  </div>

  <script src="${rel}static/js/site.js" defer></script>${
    needHls
      ? `\n  <script src="https://cdn.jsdelivr.net/npm/hls.js@1" defer></script>\n  <script src="${rel}static/js/player.js" defer></script>`
      : ''
  }
</body>
</html>
`;
}

/* ------------------------------------------------------------------ */
/* Sinh trang                                                          */
/* ------------------------------------------------------------------ */
function build() {
  fs.rmSync(OUT, { recursive: true, force: true });
  fs.mkdirSync(OUT, { recursive: true });

  const linkFile = path.join(ROOT, 'link.txt');
  if (!fs.existsSync(linkFile)) {
    throw new Error('Không tìm thấy link.txt ở thư mục gốc repo.');
  }

  // Bỏ dòng comment (#) và tiêu đề trùng (giống PHP: bỏ qua tiêu đề đã có)
  const seen = new Set();
  const videos = [];
  for (const v of parseLinkTxt(fs.readFileSync(linkFile, 'utf8'))) {
    if (seen.has(v.title)) continue;
    seen.add(v.title);
    videos.push(v);
  }

  if (!videos.length) {
    throw new Error('link.txt không có dòng video hợp lệ nào (cần dạng: ảnh|tiêu đề|mô tả|w|h|<embed>).');
  }

  // Slug duy nhất cho từng video
  const usedSlugs = new Set();
  videos.forEach((v, i) => {
    let slug = slugify(v.title) || `video-${i + 1}`;
    let candidate = slug;
    let n = 2;
    while (usedSlugs.has(candidate)) candidate = `${slug}-${n++}`;
    usedSlugs.add(candidate);
    v.slug = candidate;
    v.index = i;
    v.urlPath = `watch/${candidate}.html`;
    v.url = abs(v.urlPath);
  });

  // Thumbnail: URL ngoài dùng nguyên, còn lại sinh SVG tại chỗ
  const thumbsDir = path.join(OUT, 'static', 'img', 'thumbs');
  for (const v of videos) {
    if (v.thumb) {
      v.thumbSrc = v.thumb;
      v.thumbAbs = /^https?:\/\//i.test(v.thumb) ? v.thumb : abs(v.thumb.replace(/^\/+/, ''));
    } else {
      const relPath = `static/img/thumbs/${v.slug}.svg`;
      makeThumbSvg(v.title, path.join(OUT, relPath));
      v.thumbSrc = relPath;
      v.thumbAbs = abs(relPath);
    }
  }

  /* ---------------- Trang chủ ---------------- */
  const listItems = videos.map((v, i) => ({
    '@type': 'ListItem',
    position: i + 1,
    name: v.title,
    url: v.url,
    image: v.thumbAbs,
  }));

  const chips = [
    `<button class="chip active" type="button" data-cat="">Tất cả</button>`,
    ...CATEGORIES.map((c) => `<button class="chip" type="button" data-cat="${esc(c)}">${esc(c)}</button>`),
  ].join('\n      ');

  const cards = videos
    .map(
      (v) => `      <a class="card" href="${esc(v.urlPath)}"
         data-idx="${v.index}" data-title="${esc(v.title)}" data-desc="${esc(v.description)}" data-cat="${esc(v.category)}">
        <div class="thumb-wrap">
          <img src="${esc(thumbHref(v, ''))}" alt="${esc(v.title)}" loading="lazy">
        </div>
        <div class="card-body">
          <div class="card-title">${esc(v.title)}</div>
          <div class="card-meta"><span class="cat">${esc(v.category)}</span></div>
        </div>
      </a>`
    )
    .join('\n');

  const indexBody = `  <h1 class="sr-only">${esc(SITE_NAME)} — thư viện video</h1>

    <div class="filters">
      ${chips}
      <div class="sort-group">
        <button class="chip active" type="button" data-order="new">Mới nhất</button>
        <button class="chip" type="button" data-order="old">Cũ nhất</button>
      </div>
    </div>

    <p class="result-count" data-count></p>

    <div class="grid" data-grid>
${cards}
    </div>

    <div class="empty-state" data-empty hidden>
      <div class="big">🔍</div>
      <p>Không tìm thấy video phù hợp.</p>
    </div>`;

  const indexHtml = pageShell({
    rel: '',
    title: '',
    description: SITE_DESCRIPTION,
    canonical: `${BASE_URL}/`,
    ogImage: videos[0] ? videos[0].thumbAbs : '',
    ld: [
      {
        '@type': 'WebSite',
        '@id': `${BASE_URL}/#website`,
        url: `${BASE_URL}/`,
        name: SITE_NAME,
        description: SITE_DESCRIPTION,
        inLanguage: 'vi',
      },
      {
        '@type': 'CollectionPage',
        name: 'Trang chủ',
        description: SITE_DESCRIPTION,
        url: `${BASE_URL}/`,
        mainEntity: {
          '@type': 'ItemList',
          numberOfItems: listItems.length,
          itemListElement: listItems,
        },
      },
    ],
    body: indexBody,
  });

  fs.writeFileSync(path.join(OUT, 'index.html'), indexHtml, 'utf8');

  /* ---------------- Trang xem từng video ---------------- */
  fs.mkdirSync(path.join(OUT, 'watch'), { recursive: true });

  for (const v of videos) {
    const description = v.description
      ? [...v.description].slice(0, 160).join('')
      : `${v.title} — xem tại ${SITE_NAME}.`;

    const related = videos
      .filter((x) => x !== v && x.category === v.category)
      .slice(-8)
      .reverse();

    const relatedHtml = related.length
      ? related
          .map(
            (r) => `        <a class="side-item" href="./${esc(r.slug)}.html">
          <img class="s-thumb" src="${esc(thumbHref(r, '../'))}" alt="${esc(r.title)}" loading="lazy">
          <div>
            <div class="s-title">${esc(r.title)}</div>
            <div class="s-meta">${esc(r.category)}</div>
          </div>
        </a>`
          )
          .join('\n')
      : '        <p style="color:var(--text-dim)">Chưa có video liên quan.</p>';

    const player = v.embedHtml
      ? v.embedHtml
      : '<div class="empty-state"><p>Video không khả dụng.</p></div>';

    const body = `    <div class="watch-layout">
      <div class="watch-main">
        <div class="player-wrap">${player}</div>

        <h1 class="watch-title">${esc(v.title)}</h1>
        <div class="watch-meta">
          Danh mục <a style="color:var(--accent-2)" href="../index.html?category=${encodeURIComponent(v.category)}">${esc(v.category)}</a>
        </div>

        <figure class="post-thumb">
          <img src="${esc(thumbHref(v, '../'))}" alt="Ảnh đại diện: ${esc(v.title)}" loading="lazy">
          <figcaption>🖼 Ảnh đại diện bài viết</figcaption>
        </figure>

        <div class="desc-box">
          ${v.description ? esc(v.description) : '(Chưa có mô tả)'}
        </div>
      </div>

      <aside>
        <h3 class="section-title" style="font-size:1.05rem">Video liên quan</h3>
        <div class="side-list">
${relatedHtml}
        </div>
      </aside>
    </div>`;

    const videoLd = {
      '@type': 'VideoObject',
      name: v.title,
      description,
      thumbnailUrl: v.thumbAbs,
      genre: v.category,
      inLanguage: 'vi',
      url: v.url,
      isFamilyFriendly: false,
    };
    const embedUrl = extractEmbedUrl(v.embedHtml);
    if (embedUrl) videoLd.embedUrl = embedUrl;

    const html = pageShell({
      rel: '../',
      title: v.title,
      description,
      canonical: v.url,
      ogImage: v.thumbAbs,
      ogType: 'video',
      ld: [
        {
          '@type': 'WebSite',
          '@id': `${BASE_URL}/#website`,
          url: `${BASE_URL}/`,
          name: SITE_NAME,
          description: SITE_DESCRIPTION,
          inLanguage: 'vi',
        },
        videoLd,
      ],
      body,
      needHls: /data-m3u8/i.test(v.embedHtml),
    });

    fs.writeFileSync(path.join(OUT, 'watch', `${v.slug}.html`), html, 'utf8');
  }

  /* ---------------- 404 ---------------- */
  const notFoundHtml = pageShell({
    rel: '',
    title: 'Không tìm thấy trang',
    description: `Không tìm thấy trang bạn yêu cầu tại ${SITE_NAME}.`,
    canonical: `${BASE_URL}/404.html`,
    ogImage: videos[0] ? videos[0].thumbAbs : '',
    ld: [
      {
        '@type': 'WebSite',
        '@id': `${BASE_URL}/#website`,
        url: `${BASE_URL}/`,
        name: SITE_NAME,
        description: SITE_DESCRIPTION,
        inLanguage: 'vi',
      },
    ],
    body: `    <div class="empty-state">
      <div class="big">🔍</div>
      <p>Trang không tồn tại hoặc video đã bị xóa.</p>
      <p style="margin-top:14px"><a class="btn btn-primary" href="index.html">Về trang chủ</a></p>
    </div>`,
  });
  fs.writeFileSync(path.join(OUT, '404.html'), notFoundHtml, 'utf8');

  /* ---------------- robots / sitemap / llms ---------------- */
  fs.writeFileSync(
    path.join(OUT, 'robots.txt'),
    `User-agent: *\nAllow: /\n\nSitemap: ${BASE_URL}/sitemap.xml\n`,
    'utf8'
  );

  const sitemapUrls = [
    `  <url><loc>${BASE_URL}/</loc><lastmod>${BUILD_DATE}</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url>`,
    ...videos.map(
      (v) =>
        `  <url><loc>${v.url}</loc><lastmod>${BUILD_DATE}</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>`
    ),
  ].join('\n');

  fs.writeFileSync(
    path.join(OUT, 'sitemap.xml'),
    `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${sitemapUrls}\n</urlset>\n`,
    'utf8'
  );

  const llmsList = videos
    .map((v) => {
      const desc = v.description ? ` — ${[...v.description].slice(0, 120).join('')}` : '';
      return `- [${v.title}](${v.url})${desc}`;
    })
    .join('\n');

  fs.writeFileSync(
    path.join(OUT, 'llms.txt'),
    `# ${SITE_NAME}\n\n> ${SITE_DESCRIPTION}\n\n## Video\n\n${llmsList}\n`,
    'utf8'
  );

  /* ---------------- Copy tài nguyên tĩnh ---------------- */
  fs.cpSync(path.join(ROOT, 'static'), path.join(OUT, 'static'), { recursive: true });

  // Đánh dấu để GitHub Pages không xử lý qua Jekyll
  fs.writeFileSync(path.join(OUT, '.nojekyll'), '', 'utf8');

  console.log(`✔ Đã build bản tĩnh vào dist/`);
  console.log(`  - ${videos.length} video (${CATEGORIES.join(', ')})`);
  console.log(`  - BASE_URL: ${BASE_URL}`);
  console.log(`  - Trang chủ: dist/index.html`);
  console.log(`  - Xem thử: mở dist/index.html bằng trình duyệt`);
}

build();
