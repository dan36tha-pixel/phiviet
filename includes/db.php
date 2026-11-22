<?php
/**
 * Kết nối MySQL (PDO) + schema khởi tạo.
 * Ghi chú: không dùng FOREIGN KEY vì một số free host tắt InnoDB FK;
 * việc xóa CASCADE được xử lý thủ công trong delete.php.
 */
require_once __DIR__ . '/../config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Tự tạo bảng nếu database còn trống (lần đầu deploy / quên chạy
        // install.php). Nhờ vậy trang chủ không còn chết với lỗi 500
        // "Table '...videos' doesn't exist". Chỉ tốn 1 câu SHOW TABLES/request.
        try {
            $have = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            $have = array_map('strtolower', $have ?: []);
            foreach (['users', 'videos', 'likes', 'comments'] as $t) {
                if (!in_array($t, $have, true)) {
                    db_init();
                    break;
                }
            }
        } catch (PDOException $e) {
            // Không đủ quyền SHOW TABLES -> thử tạo bảng, lỗi thật sẽ do
            // bộ xử lý lỗi chung trong helpers.php hiển thị.
            db_init();
        }
    }
    return $pdo;
}

/** Tạo bảng nếu chưa có — chỉ gọi từ install.php */
function db_init(): void
{
    $pdo = db();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS videos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            category VARCHAR(50) NOT NULL DEFAULT 'PHIM VIỆT',
            filename VARCHAR(255) NULL,
            external_url TEXT NULL,
            thumbnail VARCHAR(255) NULL,
            embed_html TEXT NULL,
            views INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            INDEX idx_user (user_id),
            INDEX idx_cat (category),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS likes (
            user_id INT NOT NULL,
            video_id INT NOT NULL,
            PRIMARY KEY (user_id, video_id),
            INDEX idx_video (video_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            video_id INT NOT NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_video (video_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // Cài đặt lưu trong DB (ví dụ API key) — không cần sửa config.php khi đổi.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
            setting_value TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}
