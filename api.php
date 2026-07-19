<?php
/**
 * ============================================================
 * 🗄️ DATABASE API - HỆ THỐNG QUẢN LÝ CÔNG VIỆC (1 FILE DUY NHẤT)
 * ============================================================
 * File backend + database chạy trên hosting (PHP + SQLite).
 * Tự động tạo file database "database.sqlite" cùng thư mục.
 *
 * CÁCH CÀI ĐẶT (3 bước):
 * 1. Upload file api.php này lên hosting (thư mục public_html
 *    hoặc thư mục web bất kỳ) qua cPanel / FTP / File Manager
 * 2. Mở trình duyệt truy cập: https://ten-mien-cua-ban.com/api.php
 *    → Thấy {"success":true,...} là backend đã chạy
 * 3. Mở index.html → bấm huy hiệu ☁️ góc phải dưới
 *    → dán URL: https://ten-mien-cua-ban.com/api.php → OK
 *
 * YÊU CẦU HOSTING: PHP 7.0+ có SQLite (mặc định hầu hết hosting
 * đều có sẵn - cPanel, Hostinger, AZDIGI, Mắt Bão, iNET, ...)
 *
 * BẢO MẬT (tùy chọn): đặt mã bảo vệ ghi dữ liệu tại dòng API_KEY
 * bên dưới. Để trống = không yêu cầu mã (dễ dùng nhất).
 * ============================================================
 */

// ⚙️ TÙY CHỌN: Mã bảo vệ (để trống '' nếu không cần)
define('API_KEY', '');

// Tên file database (tự tạo cùng thư mục với api.php)
define('DB_FILE', __DIR__ . '/database.sqlite');

// Các khóa dữ liệu hợp lệ (khớp với index.html)
$ALLOWED_KEYS = ['tasks', 'members', 'teams', 'completionLog'];

// ==================== CORS (cho phép index.html gọi từ nơi khác) ====================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ==================== KẾT NỐI DATABASE (SQLITE) ====================
try {
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE IF NOT EXISTS app_data (
        key TEXT PRIMARY KEY,
        json TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
    // Nhật ký thay đổi (xem lịch sử ai ghi lúc nào)
    $pdo->exec('CREATE TABLE IF NOT EXISTS change_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        key TEXT NOT NULL,
        action TEXT NOT NULL,
        time TEXT NOT NULL
    )');
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Không tạo được database. Kiểm tra hosting có bật SQLite và thư mục có quyền ghi (chmod 755/775). Chi tiết: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== HÀM TIỆN ÍCH ====================
function jsonOut($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function writeKey($pdo, $key, $data) {
    $stmt = $pdo->prepare('INSERT OR REPLACE INTO app_data (key, json, updated_at) VALUES (:k, :j, :t)');
    $stmt->execute([
        ':k' => $key,
        ':j' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ':t' => date('c')
    ]);
    $log = $pdo->prepare('INSERT INTO change_log (key, action, time) VALUES (:k, :a, :t)');
    $log->execute([':k' => $key, ':a' => 'save', ':t' => date('c')]);
}

function checkApiKey($body) {
    if (API_KEY === '') return true;
    $key = isset($body['apiKey']) ? $body['apiKey'] : (isset($_GET['apiKey']) ? $_GET['apiKey'] : '');
    return hash_equals(API_KEY, (string)$key);
}

// ==================== XỬ LÝ GET (ĐỌC DỮ LIỆU) ====================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    if ($action === 'getAll') {
        $out = [];
        foreach ($pdo->query('SELECT key, json FROM app_data') as $row) {
            $decoded = json_decode($row['json'], true);
            if ($decoded !== null) {
                $out[$row['key']] = $decoded;
            }
        }
        jsonOut($out);
    }

    // Truy cập trực tiếp để kiểm tra backend hoạt động
    jsonOut([
        'success' => true,
        'message' => '✅ Database API đang hoạt động. Dán URL này vào cấu hình đám mây của index.html (bấm huy hiệu ☁️ góc phải dưới).',
        'database' => file_exists(DB_FILE) ? 'Đã tạo' : 'Sẽ tạo khi có dữ liệu',
        'server_time' => date('c')
    ]);
}

// ==================== XỬ LÝ POST (GHI DỮ LIỆU) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);

    if (!is_array($body) || !isset($body['action'])) {
        jsonOut(['success' => false, 'message' => 'Dữ liệu gửi lên không hợp lệ']);
    }

    if (!checkApiKey($body)) {
        jsonOut(['success' => false, 'message' => 'Sai mã bảo vệ (API_KEY)']);
    }

    // Giới hạn kích thước 5MB tránh phá hoại
    if (strlen($raw) > 5 * 1024 * 1024) {
        jsonOut(['success' => false, 'message' => 'Dữ liệu quá lớn (tối đa 5MB)']);
    }

    global $ALLOWED_KEYS;

    // Ghi 1 khóa: { action:'save', key:'tasks', data:[...] }
    if ($body['action'] === 'save') {
        $key = isset($body['key']) ? $body['key'] : '';
        if (!in_array($key, $ALLOWED_KEYS, true)) {
            jsonOut(['success' => false, 'message' => 'Khóa dữ liệu không hợp lệ: ' . $key]);
        }
        writeKey($pdo, $key, isset($body['data']) ? $body['data'] : []);
        jsonOut(['success' => true]);
    }

    // Ghi tất cả: { action:'saveAll', data:{ tasks:[...], members:[...], ... } }
    if ($body['action'] === 'saveAll') {
        $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];
        foreach ($data as $key => $value) {
            if (in_array($key, $ALLOWED_KEYS, true)) {
                writeKey($pdo, $key, $value);
            }
        }
        jsonOut(['success' => true]);
    }

    jsonOut(['success' => false, 'message' => 'Hành động không hợp lệ: ' . $body['action']]);
}

jsonOut(['success' => false, 'message' => 'Phương thức không được hỗ trợ']);
