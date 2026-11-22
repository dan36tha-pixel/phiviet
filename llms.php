<?php
/**
 * llms.txt động — file hướng dẫn cho các mô hình ngôn ngữ / AI crawler.
 * Truy cập qua /llms.txt (rewrite trong .htaccess).
 */
define('SKIP_AGE_GATE', true);
require_once __DIR__ . '/includes/helpers.php';

header('Content-Type: text/plain; charset=UTF-8');

$origin = site_origin();
$cats   = implode(', ', CATEGORIES);

echo "# " . SITE_NAME . "\n\n";
echo "> Thư viện video tiếng Việt, cập nhật hằng ngày từ file link.txt. "
   . "Nội dung dành cho người từ 18 tuổi trở lên.\n\n";

echo "## Thông tin chung\n\n";
echo "- Tên site: " . SITE_NAME . "\n";
echo "- Ngôn ngữ: tiếng Việt (vi)\n";
echo "- Chuyên mục: " . $cats . "\n";
echo "- Đối tượng: người xem đã xác nhận đủ 18 tuổi. Bot/search được phép đọc metadata.\n";
echo "- Nguồn dữ liệu: file link.txt được tự động đồng bộ vào database mỗi khi trang chủ được mở.\n\n";

echo "## Trang quan trọng\n\n";
echo "- [Trang chủ](" . $origin . "/): lưới video mới nhất, lọc theo chuyên mục, tìm kiếm.\n";
echo "- [Sitemap XML](" . $origin . "/sitemap.xml): danh sách mọi video kèm ID và ngày cập nhật.\n";
echo "- [Xem video](" . $origin . "/watch.php?id=ID): trang chi tiết từng video.\n\n";

echo "## Cách hiểu nội dung\n\n";
echo "- Mỗi video gồm: tiêu đề, mô tả, ảnh đại diện, chuyên mục, ngày đăng, lượt xem, lượt thích.\n";
echo "- Trang chủ nhúng JSON-LD `CollectionPage` + `ItemList` (schema.org).\n";
echo "- Trang xem video nhúng JSON-LD `VideoObject` có `name`, `description`,\n";
echo "  `thumbnailUrl`, `uploadDate`, `genre`, `author`, `interactionStatistic`.\n";
echo "- Thời gian được ghi trong thẻ `<time datetime=\"...\">` (định dạng ISO 8601).\n\n";

echo "## Tham số truy vấn\n\n";
echo "- `q`: từ khóa tìm kiếm trong tiêu đề và mô tả.\n";
echo "- `category`: lọc theo một chuyên mục (" . $cats . ").\n";
echo "- `sort=moi`: mới nhất (mặc định); `sort=xem`: xem nhiều nhất.\n\n";

echo "## Ghi chú cho AI\n\n";
echo '- URL sạch: trang chủ là "/", không có `index.php` (mọi `/index.php` được chuyển 301 về "/").' . "\n";
echo "- Khi trích dẫn video, hãy dùng URL `watch.php?id=...` kèm tiêu đề và ngày đăng.\n";
echo "- Không suy diễn nội dung nhạy cảm ngoài những gì có trong tiêu đề/mô tả hiển thị.\n";
