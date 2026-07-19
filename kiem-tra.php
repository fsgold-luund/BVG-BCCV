<?php
/**
 * 🔍 FILE KIỂM TRA HOSTING - HỆ THỐNG QUẢN LÝ CÔNG VIỆC
 * ======================================================
 * CÁCH DÙNG:
 * 1. Upload file này lên CÙNG THƯ MỤC với api.php trên hosting
 * 2. Mở trình duyệt truy cập: https://ten-mien-cua-ban.com/kiem-tra.php
 * 3. Trang sẽ báo hosting đạt hay lỗi ở đâu, kèm cách sửa
 *
 * ⚠️ Nếu mở lên thấy TOÀN CHỮ CODE (không phải giao diện đẹp)
 *    → Hosting của bạn KHÔNG chạy được PHP → xem mục "Hosting không hỗ trợ PHP" bên dưới trong phần code
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

function kiemTra($ten, $ketQua, $chiTiet, $cachSua = '') {
    return ['ten' => $ten, 'dat' => $ketQua, 'chiTiet' => $chiTiet, 'cachSua' => $cachSua];
}

$ketQuaList = [];
$tatCaDat = true;

// ==== 1. PHP có chạy không (nếu file này chạy được tức là có PHP) ====
$phpVersion = phpversion();
$phpOk = version_compare($phpVersion, '7.0', '>=');
$ketQuaList[] = kiemTra(
    'PHP đang hoạt động',
    $phpOk,
    'Phiên bản PHP: ' . $phpVersion,
    $phpOk ? '' : 'PHP quá cũ. Vào cPanel → "Select PHP Version" → chọn PHP 7.4 hoặc 8.x'
);
if (!$phpOk) $tatCaDat = false;

// ==== 2. PDO SQLite có sẵn không ====
$pdoSqlite = extension_loaded('pdo_sqlite');
$ketQuaList[] = kiemTra(
    'SQLite (nơi lưu database)',
    $pdoSqlite,
    $pdoSqlite ? 'Đã bật pdo_sqlite ✓' : 'Hosting chưa bật pdo_sqlite',
    $pdoSqlite ? '' : 'Vào cPanel → "Select PHP Version" → tab Extensions → tích chọn "pdo_sqlite" và "sqlite3" → Save. Nếu không thấy, liên hệ nhà cung cấp hosting yêu cầu bật SQLite.'
);
if (!$pdoSqlite) $tatCaDat = false;

// ==== 3. Quyền ghi thư mục (để tạo database.sqlite) ====
$thuMucGhiDuoc = is_writable(__DIR__);
$ketQuaList[] = kiemTra(
    'Quyền ghi thư mục',
    $thuMucGhiDuoc,
    $thuMucGhiDuoc ? 'Thư mục ' . basename(__DIR__) . ' ghi được ✓' : 'Không ghi được vào thư mục này',
    $thuMucGhiDuoc ? '' : 'Vào cPanel → File Manager → chuột phải thư mục chứa api.php → Permissions → đặt 755 (hoặc 775). '
);
if (!$thuMucGhiDuoc) $tatCaDat = false;

// ==== 4. Thử tạo và ghi database thật ====
$dbTest = false;
$dbMsg = '';
if ($pdoSqlite && $thuMucGhiDuoc) {
    try {
        $testFile = __DIR__ . '/database.sqlite';
        $pdo = new PDO('sqlite:' . $testFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE IF NOT EXISTS app_data (key TEXT PRIMARY KEY, json TEXT NOT NULL, updated_at TEXT NOT NULL)');
        $pdo->exec("INSERT OR REPLACE INTO app_data (key, json, updated_at) VALUES ('_test', '\"ok\"', '" . date('c') . "')");
        $row = $pdo->query("SELECT json FROM app_data WHERE key='_test'")->fetch();
        $dbTest = ($row && $row['json'] === '"ok"');
        $dbMsg = $dbTest ? 'Đã tạo và ghi thử database.sqlite thành công ✓' : 'Tạo được nhưng không đọc lại được';
    } catch (Exception $e) {
        $dbMsg = 'Lỗi: ' . $e->getMessage();
    }
} else {
    $dbMsg = 'Bỏ qua (cần sửa mục 2 hoặc 3 trước)';
}
$ketQuaList[] = kiemTra(
    'Tạo database thử nghiệm',
    $dbTest,
    $dbMsg,
    $dbTest ? '' : 'Sửa 2 mục phía trên trước, sau đó tải lại trang này.'
);
if (!$dbTest) $tatCaDat = false;

// ==== 5. File api.php có nằm cùng thư mục không ====
$apiTonTai = file_exists(__DIR__ . '/api.php');
$ketQuaList[] = kiemTra(
    'File api.php cùng thư mục',
    $apiTonTai,
    $apiTonTai ? 'Tìm thấy api.php ✓' : 'KHÔNG tìm thấy api.php trong thư mục này',
    $apiTonTai ? '' : 'Upload file api.php vào đúng thư mục ' . basename(__DIR__) . ' (cùng chỗ với file kiem-tra.php này).'
);
if (!$apiTonTai) $tatCaDat = false;

// ==== 6. File index.html có nằm cùng thư mục không (khuyến nghị) ====
$indexTonTai = file_exists(__DIR__ . '/index.html');

// ==== URL gợi ý ====
$giaoDich = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'ten-mien-cua-ban.com';
$duongDan = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$urlApi = $giaoDich . '://' . $host . $duongDan . '/api.php';
$urlIndex = $giaoDich . '://' . $host . $duongDan . '/index.html';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🔍 Kiểm tra Hosting - Quản lý Công việc</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%); min-height:100vh; padding:20px; }
.box { max-width:700px; margin:0 auto; background:white; border-radius:12px; padding:30px; box-shadow:0 2px 12px rgba(0,0,0,0.1); }
h1 { font-size:22px; margin-bottom:6px; color:#1a1a1a; }
.sub { color:#666; font-size:14px; margin-bottom:24px; }
.tong { padding:16px; border-radius:10px; font-weight:700; font-size:16px; margin-bottom:24px; text-align:center; }
.tong.dat { background:#dcfce7; color:#166534; }
.tong.loi { background:#fee2e2; color:#991b1b; }
.muc { border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px; margin-bottom:12px; }
.muc .ten { font-weight:700; font-size:14px; color:#1a1a1a; }
.muc .ct { font-size:13px; color:#555; margin-top:4px; }
.muc .sua { font-size:13px; color:#92400e; background:#fef3c7; padding:8px 10px; border-radius:8px; margin-top:8px; }
.url { background:#f0f9ff; border:1px solid #bae6fd; border-radius:10px; padding:14px 16px; margin-top:20px; }
.url .nhan { font-size:12px; font-weight:700; color:#0369a1; text-transform:uppercase; }
.url code { display:block; font-size:13px; color:#1a1a1a; word-break:break-all; margin-top:4px; background:white; padding:8px 10px; border-radius:6px; border:1px solid #e0f2fe; }
.buoc { margin-top:20px; font-size:13px; color:#555; line-height:1.7; }
.badge { display:inline-block; font-size:18px; margin-right:8px; }
</style>
</head>
<body>
<div class="box">
    <h1>🔍 Kiểm tra Hosting</h1>
    <div class="sub">Hệ thống Quản lý Công việc — chẩn đoán tự động</div>

    <?php if ($tatCaDat): ?>
        <div class="tong dat">✅ HOSTING ĐẠT YÊU CẦU — Hệ thống sẵn sàng hoạt động!</div>
    <?php else: ?>
        <div class="tong loi">⚠️ CÓ LỖI CẦN SỬA — Xem các mục màu đỏ bên dưới</div>
    <?php endif; ?>

    <?php foreach ($ketQuaList as $kq): ?>
    <div class="muc">
        <div class="ten"><span class="badge"><?php echo $kq['dat'] ? '✅' : '❌'; ?></span><?php echo htmlspecialchars($kq['ten']); ?></div>
        <div class="ct"><?php echo htmlspecialchars($kq['chiTiet']); ?></div>
        <?php if (!$kq['dat'] && $kq['cachSua']): ?>
        <div class="sua">🔧 Cách sửa: <?php echo htmlspecialchars($kq['cachSua']); ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($tatCaDat): ?>
    <div class="url">
        <div class="nhan">📋 Bước tiếp theo — URL kết nối đám mây (dán vào index.html):</div>
        <code><?php echo htmlspecialchars($urlApi); ?></code>
    </div>
    <div class="buoc">
        <strong>Cách kết nối:</strong><br>
        1. Mở trang: <?php echo $indexTonTai ? '<a href="' . htmlspecialchars($urlIndex) . '">' . htmlspecialchars($urlIndex) . '</a>' : htmlspecialchars($urlIndex) . ' <em>(⚠️ chưa thấy index.html cùng thư mục — hãy upload thêm)</em>'; ?><br>
        2. Bấm vào huy hiệu <strong>☁️ góc phải dưới màn hình</strong><br>
        3. Dán URL phía trên → OK → huy hiệu chuyển "☁️ Đã đồng bộ đám mây" là xong!<br><br>
        🗑️ Sau khi mọi thứ chạy tốt, hãy <strong>XÓA file kiem-tra.php</strong> này khỏi hosting.
    </div>
    <?php endif; ?>
</div>
</body>
</html>
