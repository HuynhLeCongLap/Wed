<?php
/**
 * fix_images.php - Chạy 1 lần để fix toàn bộ đường dẫn ảnh
 * URL: http://localhost/GiaHuy/fix_images.php
 */
require 'connect.php';

// =====================================================================
// BƯỚC 1: TẠO ẢNH PLACEHOLDER SVG CHO CÁC HÃNG CHƯA CÓ ẢNH THỰC
// =====================================================================
$placeholder_dir = __DIR__ . '/img/placeholder';
if (!is_dir($placeholder_dir)) mkdir($placeholder_dir, 0777, true);

$brands_svg = [
    'xiaomi' => ['#FF6900', '#FFFFFF', 'Xiaomi'],
    'poco'   => ['#191919', '#F6DC3B', 'POCO'],
    'oppo'   => ['#1B5E20', '#FFFFFF', 'OPPO'],
    'realme' => ['#FEE500', '#1B1B1B', 'realme'],
    'default'=> ['#2D3748', '#FFFFFF', 'Phone'],
];

foreach ($brands_svg as $name => [$bg, $color, $label]) {
    $svg_file = $placeholder_dir . '/' . $name . '.svg';
    if (!file_exists($svg_file)) {
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400">
  <rect width="400" height="400" fill="$bg" rx="20"/>
  <!-- Phone icon -->
  <rect x="140" y="80" width="120" height="200" rx="15" ry="15" fill="none" stroke="$color" stroke-width="6"/>
  <rect x="156" y="100" width="88" height="140" fill="$color" opacity="0.15" rx="4"/>
  <circle cx="200" cy="258" r="10" fill="$color" opacity="0.6"/>
  <line x1="175" y1="92" x2="225" y2="92" stroke="$color" stroke-width="5" stroke-linecap="round"/>
  <!-- Brand label -->
  <text x="200" y="330" font-family="Arial,sans-serif" font-size="38" font-weight="bold"
        text-anchor="middle" fill="$color">$label</text>
  <text x="200" y="365" font-family="Arial,sans-serif" font-size="18"
        text-anchor="middle" fill="$color" opacity="0.7">Smartphone</text>
</svg>
SVG;
        file_put_contents($svg_file, $svg);
    }
}

// =====================================================================
// BƯỚC 2: MAP MaSanPham => đường dẫn ảnh thực tế
// =====================================================================
// Ảnh theo từng MaSanPham (lấy ảnh đại diện phù hợp nhất từ files có sẵn)
$product_image_map = [
    // ===== SAMSUNG S SERIES =====
    1  => 'img/samsung/Dong_S/dien_thoai_S24_ULTRA.jpg',
    2  => 'img/samsung/Dong_S/samsung-galaxy-s22-ultra.jpg',
    3  => 'img/samsung/Dong_S/samsung-s23.jpg',
    // ===== SAMSUNG Z SERIES =====
    4  => 'img/samsung/Dong_Z/samsung-galaxy-z-fold-4.jpg',
    // ===== SAMSUNG A SERIES =====
    5  => 'img/samsung/Dong_A/samsung-galaxy-a55.jpg',
    6  => 'img/samsung/Dong_A/dien-thoai-samsung-galaxy-a36.jpg',
    7  => 'img/samsung/Dong_A/samsung-galaxy-a55.jpg',
    8  => 'img/samsung/Dong_A/dien-thoai-samsung-galaxy-a06.jpg',
    9  => 'img/samsung/Dong_A/samsung-galaxy-a07-5g-2_3.jpg',
    10 => 'img/samsung/Dong_A/dien-thoai-samsung-galaxy-a26.jpg',
    // ===== XIAOMI / REDMI =====
    11 => 'img/placeholder/xiaomi.svg',
    12 => 'img/placeholder/xiaomi.svg',
    13 => 'img/placeholder/xiaomi.svg',
    14 => 'img/placeholder/xiaomi.svg',
    15 => 'img/placeholder/xiaomi.svg',
    16 => 'img/placeholder/xiaomi.svg',
    17 => 'img/placeholder/xiaomi.svg',
    18 => 'img/placeholder/xiaomi.svg',
    19 => 'img/placeholder/xiaomi.svg',
    20 => 'img/placeholder/poco.svg',
    // ===== OPPO =====
    21 => 'img/placeholder/oppo.svg',
    22 => 'img/placeholder/oppo.svg',
    23 => 'img/placeholder/oppo.svg',
    24 => 'img/placeholder/oppo.svg',
    25 => 'img/placeholder/oppo.svg',
    26 => 'img/placeholder/oppo.svg',
    27 => 'img/placeholder/oppo.svg',
    28 => 'img/placeholder/oppo.svg',
    29 => 'img/placeholder/oppo.svg',
    30 => 'img/placeholder/oppo.svg',
    // ===== REALME =====
    31 => 'img/placeholder/realme.svg',
    32 => 'img/placeholder/realme.svg',
    33 => 'img/placeholder/realme.svg',
    34 => 'img/placeholder/realme.svg',
    35 => 'img/placeholder/realme.svg',
    36 => 'img/placeholder/realme.svg',
    37 => 'img/placeholder/realme.svg',
    38 => 'img/placeholder/realme.svg',
    39 => 'img/placeholder/realme.svg',
    40 => 'img/placeholder/realme.svg',
    // ===== IPHONE =====
    41 => 'img/iphone/13_SERIES/iphone-13.jpg',
    42 => 'img/iphone/13_SERIES/iphone-13.jpg',
    43 => 'img/iphone/13_SERIES/iphone-13.jpg',
    44 => 'img/iphone/14_SERIES/iphone-14.jpg',
    45 => 'img/iphone/15_SERIES/iphone-15.jpg',
    46 => 'img/iphone/14_SERIES/iphone-14-plus.jpg',
    47 => 'img/iphone/15_SERIES/iphone-15-plus.jpg',
    48 => 'img/iphone/15_SERIES/iphone-15-pro.jpg',
    49 => 'img/iphone/14_SERIES/iphone-14-pro.jpg',
    50 => 'img/iphone/15_SERIES/iphone-15-pro-max.jpg',
];

// =====================================================================
// BƯỚC 3: CẬP NHẬT BẢNG image
// =====================================================================
$stmt    = $conn->prepare("UPDATE image SET DiaChiAnh = ? WHERE MaSanPham = ?");
$updated = 0;
$errors  = 0;
$log     = [];

foreach ($product_image_map as $ma_san_pham => $path) {
    $stmt->bind_param('si', $path, $ma_san_pham);
    $stmt->execute();
    if ($stmt->errno) {
        $errors++;
        $log[] = "❌ SP #$ma_san_pham: " . $stmt->error;
    } else {
        $rows    = $stmt->affected_rows;
        $updated += $rows;
        $log[]   = "✅ SP #$ma_san_pham → <code>$path</code> ($rows hàng)";
    }
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Fix Image Paths</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
  h1 { color: #333; }
  .summary { background: #fff; padding: 16px 24px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #4CAF50; }
  .log { background: #fff; padding: 16px 24px; border-radius: 8px; max-height: 400px; overflow-y: auto; }
  .log p { margin: 4px 0; font-size: 13px; }
  a.btn { display: inline-block; margin-top: 16px; padding: 10px 24px;
          background: #3498db; color: #fff; border-radius: 6px; text-decoration: none; }
  .check-section { margin-top: 24px; background: #fff; padding: 16px; border-radius: 8px; }
  .file-ok { color: green; } .file-miss { color: red; }
</style>
</head>
<body>
<h1>🔧 Fix Image Paths</h1>

<div class="summary">
    <h2>📊 Kết quả</h2>
    <p>✅ Đã cập nhật: <strong><?= $updated ?></strong> bản ghi</p>
    <?php if ($errors): ?>
        <p style="color:red">❌ Lỗi: <strong><?= $errors ?></strong></p>
    <?php endif; ?>
    <p>📁 Ảnh placeholder SVG đã được tạo trong <code>img/placeholder/</code></p>
</div>

<div class="log">
    <h3>Chi tiết:</h3>
    <?php foreach ($log as $line): ?>
        <p><?= $line ?></p>
    <?php endforeach; ?>
</div>

<div class="check-section">
    <h3>🔍 Kiểm tra file ảnh tồn tại:</h3>
    <?php
    $checked = [];
    foreach ($product_image_map as $path) {
        if (in_array($path, $checked)) continue;
        $checked[] = $path;
        $full = __DIR__ . '/' . $path;
        $exists = file_exists($full);
        $cls  = $exists ? 'file-ok' : 'file-miss';
        $icon = $exists ? '✅' : '❌';
        echo "<p class='$cls'>$icon <code>$path</code></p>";
    }
    ?>
</div>

<a href="index.php" class="btn">← Về trang chủ</a>
<a href="fix_images.php" class="btn" style="background:#e67e22">🔄 Chạy lại</a>
</body>
</html>
