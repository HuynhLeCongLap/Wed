<?php
session_start();
require_once 'connect.php';

$maSanPham = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($maSanPham <= 0) {
    header('Location: index.php');
    exit;
}

// Thông tin sản phẩm
$stmt = mysqli_prepare($conn, "SELECT * FROM sanpham WHERE MaSanPham = ?");
mysqli_stmt_bind_param($stmt, 'i', $maSanPham);
mysqli_stmt_execute($stmt);
$sanPham = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$sanPham) {
    header('Location: index.php');
    exit;
}

// Chi tiết kỹ thuật
$stmt = mysqli_prepare($conn, "SELECT * FROM chitietsanpham WHERE MaSanPham = ?");
mysqli_stmt_bind_param($stmt, 'i', $maSanPham);
mysqli_stmt_execute($stmt);
$chiTiet = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Màu sắc + ảnh
$stmt = mysqli_prepare($conn,
    "SELECT c.MaMau, c.TenMau, i.DiaChiAnh
     FROM colors c
     LEFT JOIN image i ON i.MaMau = c.MaMau AND i.MaSanPham = c.MaSanPham
     WHERE c.MaSanPham = ?
     ORDER BY c.MaMau");
mysqli_stmt_bind_param($stmt, 'i', $maSanPham);
mysqli_stmt_execute($stmt);
$colors = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// RAM/ROM options
$stmt = mysqli_prepare($conn, "SELECT * FROM ram_rom_option WHERE MaSanPham = ? ORDER BY MaRam");
mysqli_stmt_bind_param($stmt, 'i', $maSanPham);
mysqli_stmt_execute($stmt);
$ramOptions = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Giá + tồn kho theo (MaRam, MaMau)
$stmt = mysqli_prepare($conn, "SELECT g.MaRam, g.MaMau, g.GiaMoi, g.GiaCu, g.SoLuong
     FROM giasanpham g WHERE g.MaSanPham = ?");
mysqli_stmt_bind_param($stmt, 'i', $maSanPham);
mysqli_stmt_execute($stmt);
$prices = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Build lookup: prices[$maRam][$maMau] => [GiaMoi, GiaCu, SoLuong]
$priceMap = [];
foreach ($prices as $pr) {
    $priceMap[$pr['MaRam']][$pr['MaMau']] = $pr;
}

// Video
$stmt = mysqli_prepare($conn, "SELECT DiaChiVideo FROM video WHERE MaSanPham = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $maSanPham);
mysqli_stmt_execute($stmt);
$video = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Mặc định chọn màu đầu tiên
$defaultColor = !empty($colors) ? $colors[0] : null;
$defaultRam   = !empty($ramOptions) ? $ramOptions[0] : null;

// Xử lý thêm vào giỏ
$cartMsg = '';
if (isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['user'])) {
        header('Location: pages/login.php');
        exit;
    }
    $maMau  = (int)$_POST['ma_mau'];
    $maRam  = (int)$_POST['ma_ram'];
    $soLuong = max(1, (int)$_POST['so_luong']);
    $tenDN  = $_SESSION['user'];

    // Lấy thông tin giá + kiểm tra tồn kho
    $stmt = mysqli_prepare($conn,
        "SELECT g.GiaMoi, g.SoLuong, c.TenMau, r.KichThuoc,
                (SELECT DiaChiAnh FROM image WHERE MaSanPham=? AND MaMau=? LIMIT 1) AS DiaChiAnh
         FROM giasanpham g
         JOIN colors c ON c.MaMau = g.MaMau
         JOIN ram_rom_option r ON r.MaRam = g.MaRam
         WHERE g.MaSanPham=? AND g.MaMau=? AND g.MaRam=?
         LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'iiiii', $maSanPham, $maMau, $maSanPham, $maMau, $maRam);
    mysqli_stmt_execute($stmt);
    $gia = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$gia) {
        $cartMsg = 'error|Không tìm thấy phiên bản sản phẩm này.';
    } elseif ($gia['SoLuong'] < $soLuong) {
        $cartMsg = 'error|Số lượng trong kho không đủ (còn ' . $gia['SoLuong'] . ').';
    } else {
        // Kiểm tra đã có trong giỏ chưa
        $chk = mysqli_prepare($conn,
            "SELECT MaHang, Soluong FROM giohang
             WHERE TenDangNhap=? AND MaSanPham=? AND MauSac=? AND KichThuoc=? LIMIT 1");
        mysqli_stmt_bind_param($chk, 'ssss', $tenDN, $maSanPham, $gia['TenMau'], $gia['KichThuoc']);
        mysqli_stmt_execute($chk);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);

        if ($existing) {
            $newQty = $existing['Soluong'] + $soLuong;
            if ($newQty > $gia['SoLuong']) $newQty = $gia['SoLuong'];
            $upd = mysqli_prepare($conn, "UPDATE giohang SET Soluong=? WHERE MaHang=?");
            mysqli_stmt_bind_param($upd, 'ii', $newQty, $existing['MaHang']);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        } else {
            $ins = mysqli_prepare($conn,
                "INSERT INTO giohang (TenDangNhap, MaSanPham, DiaChiAnh, TenSanPham, MauSac, KichThuoc, GiaMoi, Soluong)
                 VALUES (?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($ins, 'ssssssii',
                $tenDN, $maSanPham, $gia['DiaChiAnh'], $sanPham['TenSanPham'],
                $gia['TenMau'], $gia['KichThuoc'], $gia['GiaMoi'], $soLuong);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
        }
        $cartMsg = 'success|Đã thêm vào giỏ hàng!';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($sanPham['TenSanPham']) ?> - Mobile Web</title>
    <link rel="stylesheet" href="assets/css/checkout.css">
    <style>
        .detail-page { padding-bottom: 40px; }

        .product-top {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        @media (max-width: 768px) {
            .product-top { grid-template-columns: 1fr; }
        }

        /* Gallery */
        .gallery { position: sticky; top: 20px; }
        .main-img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: contain;
            border-radius: 12px;
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 12px;
        }
        .thumb-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .thumb {
            width: 60px; height: 60px;
            object-fit: contain;
            border-radius: 8px;
            border: 2px solid #ddd;
            cursor: pointer;
            background: #f8f9fa;
            padding: 4px;
        }
        .thumb.active { border-color: #3498db; }
        .no-img-placeholder {
            width: 100%;
            aspect-ratio: 1;
            background: #f0f2f5;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: #ccc;
        }

        /* Product Info */
        .product-info-panel .brand-tag {
            font-size: 12px;
            color: #3498db;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        .product-info-panel h1 {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 12px;
        }
        .current-price {
            font-size: 28px;
            font-weight: 700;
            color: #e74c3c;
        }
        .old-price {
            font-size: 16px;
            color: #aaa;
            text-decoration: line-through;
            margin-left: 8px;
        }
        .discount-badge {
            display: inline-block;
            background: #e74c3c;
            color: white;
            font-size: 12px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 8px;
        }

        .stock-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            margin: 10px 0;
        }
        .stock-indicator.in-stock    { background: #d5f5e3; color: #196f3d; }
        .stock-indicator.low-stock   { background: #fef9e7; color: #d68910; }
        .stock-indicator.out-of-stock{ background: #fadbd8; color: #922b21; }

        .option-label {
            font-weight: 700;
            font-size: 14px;
            color: #2c3e50;
            margin-bottom: 8px;
            margin-top: 14px;
        }
        .color-options {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .color-btn {
            padding: 7px 14px;
            border: 2px solid #ddd;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            font-size: 13px;
            transition: all .2s;
        }
        .color-btn:hover   { border-color: #3498db; }
        .color-btn.active  { border-color: #3498db; background: #ebf5fb; color: #3498db; font-weight: 700; }

        .ram-options {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .ram-btn {
            padding: 7px 14px;
            border: 2px solid #ddd;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            font-size: 13px;
            transition: all .2s;
        }
        .ram-btn:hover  { border-color: #3498db; }
        .ram-btn.active { border-color: #3498db; background: #ebf5fb; color: #3498db; font-weight: 700; }
        .ram-btn.no-stock { opacity: .4; cursor: not-allowed; }

        .qty-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 14px;
        }
        .qty-row label { font-weight: 700; font-size: 14px; }
        .qty-control {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 6px;
            overflow: hidden;
        }
        .qty-control button {
            width: 36px; height: 36px;
            border: none;
            background: #f0f2f5;
            font-size: 18px;
            cursor: pointer;
            color: #333;
            padding: 0;
            line-height: 1;
        }
        .qty-control input {
            width: 60px;
            border: none;
            border-left: 1px solid #ddd;
            border-right: 1px solid #ddd;
            text-align: center;
            font-size: 15px;
            font-weight: 600;
            padding: 0;
            height: 36px;
        }

        .add-cart-btn {
            display: block;
            width: 100%;
            padding: 14px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 16px;
            transition: background .2s;
        }
        .add-cart-btn:hover { background: #c0392b; }
        .add-cart-btn:disabled { background: #aaa; cursor: not-allowed; }

        .buy-now-btn {
            display: block;
            width: 100%;
            padding: 14px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
            transition: background .2s;
            text-decoration: none;
            text-align: center;
        }
        .buy-now-btn:hover { background: #2980b9; color: white; text-decoration: none; }

        /* Specs table */
        .specs-table { width: 100%; border-collapse: collapse; }
        .specs-table tr:nth-child(even) { background: #f8fafc; }
        .specs-table td {
            padding: 11px 14px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        .specs-table td:first-child {
            color: #666;
            font-weight: 600;
            width: 40%;
        }

        /* Video */
        .video-wrap {
            position: relative;
            padding-top: 56.25%;
            border-radius: 12px;
            overflow: hidden;
        }
        .video-wrap iframe {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            border: none;
        }

        .msg-success {
            background: #d5f5e3; color: #196f3d;
            border: 1px solid #a9dfbf;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 14px;
            font-weight: 600;
        }
        .msg-error {
            background: #fadbd8; color: #922b21;
            border: 1px solid #f1948a;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 14px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a href="index.php" class="logo">Mobile Web</a>
            <nav>
                <a href="index.php">Trang chủ</a>
                <a href="cart.php">Giỏ hàng</a>
                <?php if (isset($_SESSION['user'])): ?>
                    <a href="my_orders.php">Đơn hàng</a>
                    <a href="pages/profile.php">Tài khoản</a>
                <?php else: ?>
                    <a href="pages/login.php">Đăng nhập</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="container detail-page">
        <div class="breadcrumb">
            <a href="index.php">Trang chủ</a> ›
            <a href="index.php?hang=<?= urlencode($sanPham['Hang']) ?>"><?= htmlspecialchars($sanPham['Hang']) ?></a> ›
            <?= htmlspecialchars($sanPham['TenSanPham']) ?>
        </div>

        <?php
        $msgParts = $cartMsg ? explode('|', $cartMsg, 2) : [];
        if (!empty($msgParts)):
        ?>
            <div class="<?= $msgParts[0] === 'success' ? 'msg-success' : 'msg-error' ?>">
                <?= htmlspecialchars($msgParts[1]) ?>
                <?php if ($msgParts[0] === 'success'): ?>
                    &nbsp;<a href="cart.php" style="color:inherit; text-decoration:underline;">Xem giỏ hàng →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="product-top">
            <!-- Gallery -->
            <div class="gallery card">
                <?php if (!empty($colors[0]['DiaChiAnh'])): ?>
                    <img id="main-img" class="main-img"
                         src="<?= htmlspecialchars($colors[0]['DiaChiAnh']) ?>"
                         alt="<?= htmlspecialchars($sanPham['TenSanPham']) ?>">
                    <div class="thumb-row">
                        <?php foreach ($colors as $c): ?>
                            <?php if (!empty($c['DiaChiAnh'])): ?>
                                <img class="thumb <?= $c === $colors[0] ? 'active' : '' ?>"
                                     src="<?= htmlspecialchars($c['DiaChiAnh']) ?>"
                                     alt="<?= htmlspecialchars($c['TenMau']) ?>"
                                     data-color="<?= $c['MaMau'] ?>"
                                     onclick="selectImg(this)">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-img-placeholder">📱</div>
                <?php endif; ?>
            </div>

            <!-- Info Panel -->
            <div class="product-info-panel card">
                <div class="brand-tag"><?= htmlspecialchars($sanPham['Hang']) ?></div>
                <h1><?= htmlspecialchars($sanPham['TenSanPham']) ?></h1>

                <div id="price-display">
                    <?php
                    $firstPr = null;
                    if ($defaultColor && $defaultRam && isset($priceMap[$defaultRam['MaRam']][$defaultColor['MaMau']])) {
                        $firstPr = $priceMap[$defaultRam['MaRam']][$defaultColor['MaMau']];
                    }
                    if ($firstPr):
                        $discount = $firstPr['GiaCu'] > 0
                            ? round((1 - $firstPr['GiaMoi'] / $firstPr['GiaCu']) * 100)
                            : 0;
                    ?>
                        <span class="current-price"><?= number_format($firstPr['GiaMoi'], 0, ',', '.') ?>đ</span>
                        <?php if ($firstPr['GiaCu'] > $firstPr['GiaMoi']): ?>
                            <span class="old-price"><?= number_format($firstPr['GiaCu'], 0, ',', '.') ?>đ</span>
                            <?php if ($discount > 0): ?>
                                <span class="discount-badge">-<?= $discount ?>%</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="current-price">Liên hệ</span>
                    <?php endif; ?>
                </div>

                <div id="stock-display">
                    <?php if ($firstPr): ?>
                        <?php
                        $qty = (int)$firstPr['SoLuong'];
                        $cls = $qty === 0 ? 'out-of-stock' : ($qty <= 10 ? 'low-stock' : 'in-stock');
                        $txt = $qty === 0 ? '❌ Hết hàng' : ($qty <= 10 ? "⚠️ Còn $qty sản phẩm" : '✅ Còn hàng');
                        ?>
                        <span class="stock-indicator <?= $cls ?>"><?= $txt ?></span>
                    <?php endif; ?>
                </div>

                <!-- Chọn màu -->
                <?php if (!empty($colors)): ?>
                <div class="option-label">Màu sắc: <span id="selected-color-name"><?= htmlspecialchars($colors[0]['TenMau']) ?></span></div>
                <div class="color-options">
                    <?php foreach ($colors as $idx => $c): ?>
                        <button type="button"
                                class="color-btn <?= $idx === 0 ? 'active' : '' ?>"
                                data-mau="<?= $c['MaMau'] ?>"
                                data-ten="<?= htmlspecialchars($c['TenMau']) ?>"
                                data-img="<?= htmlspecialchars($c['DiaChiAnh'] ?? '') ?>"
                                onclick="selectColor(this)">
                            <?= htmlspecialchars($c['TenMau']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Chọn RAM/ROM -->
                <?php if (!empty($ramOptions)): ?>
                <div class="option-label">Dung lượng: <span id="selected-ram-name"><?= htmlspecialchars($ramOptions[0]['KichThuoc']) ?></span></div>
                <div class="ram-options">
                    <?php foreach ($ramOptions as $idx => $ram): ?>
                        <button type="button"
                                class="ram-btn <?= $idx === 0 ? 'active' : '' ?>"
                                data-ram="<?= $ram['MaRam'] ?>"
                                data-ten="<?= htmlspecialchars($ram['KichThuoc']) ?>"
                                onclick="selectRam(this)">
                            <?= htmlspecialchars($ram['KichThuoc']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Số lượng -->
                <div class="qty-row">
                    <label>Số lượng:</label>
                    <div class="qty-control">
                        <button type="button" onclick="changeQty(-1)">−</button>
                        <input type="number" id="qty-input" value="1" min="1" max="99">
                        <button type="button" onclick="changeQty(1)">+</button>
                    </div>
                </div>

                <!-- Form thêm giỏ -->
                <form method="POST" id="cart-form">
                    <input type="hidden" name="ma_mau" id="f_maMau" value="<?= $defaultColor ? $defaultColor['MaMau'] : 0 ?>">
                    <input type="hidden" name="ma_ram" id="f_maRam" value="<?= $defaultRam ? $defaultRam['MaRam'] : 0 ?>">
                    <input type="hidden" name="so_luong" id="f_soLuong" value="1">
                    <button type="submit" name="add_to_cart" class="add-cart-btn" id="add-btn">
                        🛒 Thêm vào giỏ hàng
                    </button>
                </form>

                <a href="cart.php" class="buy-now-btn" style="margin-top:8px;">💳 Mua ngay</a>
            </div>
        </div>

        <!-- Specs + Video -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
            <?php if ($chiTiet): ?>
            <div class="card">
                <h2 class="card-title">Thông số kỹ thuật</h2>
                <table class="specs-table">
                    <?php
                    $specs = [
                        'Màn hình'           => $chiTiet['KichThuocManHinh'],
                        'Công nghệ MH'       => $chiTiet['CongNgheManHinh'],
                        'Độ phân giải'       => $chiTiet['DoPhanGiaiManHinh'],
                        'Tính năng MH'       => $chiTiet['TinhNangManHinh'],
                        'Camera sau'         => $chiTiet['CameraSau'],
                        'Quay video sau'     => $chiTiet['QuayVideoSau'],
                        'Camera trước'       => $chiTiet['CameraTruoc'],
                        'Quay video trước'   => $chiTiet['QuayVideoTruoc'],
                        'Chip'               => $chiTiet['ChipSet'],
                        'Pin'                => $chiTiet['Pin'],
                        'Sạc'               => $chiTiet['CongNgheSac'],
                        'SIM'                => $chiTiet['TheSim'],
                        'Hệ điều hành'      => $chiTiet['HeDieuHanh'],
                        'Mạng'              => $chiTiet['HoTroMang'],
                        'WiFi'               => $chiTiet['Wifi'],
                        'Bluetooth'          => $chiTiet['Bluetooth'],
                        'GPS'                => $chiTiet['Gps'],
                        'Kháng nước'         => $chiTiet['KhangNuocBui'],
                        'Âm thanh'          => $chiTiet['CongNgheAmThanh'],
                    ];
                    foreach ($specs as $label => $value):
                        if (!$value) continue;
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($label) ?></td>
                            <td><?= htmlspecialchars($value) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php endif; ?>

            <?php if ($video): ?>
            <div class="card">
                <h2 class="card-title">Video giới thiệu</h2>
                <div class="video-wrap">
                    <iframe src="<?= htmlspecialchars($video['DiaChiVideo']) ?>"
                            allowfullscreen></iframe>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">© <?= date('Y') ?> Mobile Web - Đồ án cuối kỳ</div>
    </footer>

    <script>
    // Dữ liệu giá từ PHP
    const priceMap = <?= json_encode($priceMap) ?>;
    const colorImgs = {};
    <?php foreach ($colors as $c): ?>
        colorImgs[<?= $c['MaMau'] ?>] = "<?= addslashes($c['DiaChiAnh'] ?? '') ?>";
    <?php endforeach; ?>

    let selectedMau = <?= $defaultColor ? $defaultColor['MaMau'] : 0 ?>;
    let selectedRam = <?= $defaultRam ? $defaultRam['MaRam'] : 0 ?>;

    function updatePrice() {
        const pr = priceMap[selectedRam] && priceMap[selectedRam][selectedMau]
            ? priceMap[selectedRam][selectedMau] : null;

        const priceDiv = document.getElementById('price-display');
        const stockDiv = document.getElementById('stock-display');

        if (pr) {
            const giaMoi = parseInt(pr.GiaMoi);
            const giaCu  = parseInt(pr.GiaCu);
            const soLuong = parseInt(pr.SoLuong);
            let html = `<span class="current-price">${giaMoi.toLocaleString('vi-VN')}đ</span>`;
            if (giaCu > giaMoi) {
                const disc = Math.round((1 - giaMoi / giaCu) * 100);
                html += ` <span class="old-price">${giaCu.toLocaleString('vi-VN')}đ</span>`;
                if (disc > 0) html += ` <span class="discount-badge">-${disc}%</span>`;
            }
            priceDiv.innerHTML = html;

            // Stock
            let cls, txt;
            if (soLuong === 0) { cls='out-of-stock'; txt='❌ Hết hàng'; }
            else if (soLuong <= 10) { cls='low-stock'; txt=`⚠️ Còn ${soLuong} sản phẩm`; }
            else { cls='in-stock'; txt='✅ Còn hàng'; }
            stockDiv.innerHTML = `<span class="stock-indicator ${cls}">${txt}</span>`;

            // Qty max
            const qtyInput = document.getElementById('qty-input');
            qtyInput.max = soLuong;
            if (parseInt(qtyInput.value) > soLuong) qtyInput.value = soLuong || 1;

            document.getElementById('add-btn').disabled = soLuong === 0;
        } else {
            priceDiv.innerHTML = '<span class="current-price">Liên hệ</span>';
            stockDiv.innerHTML = '<span class="stock-indicator out-of-stock">❌ Không có phiên bản này</span>';
            document.getElementById('add-btn').disabled = true;
        }
    }

    function selectColor(btn) {
        document.querySelectorAll('.color-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        selectedMau = parseInt(btn.dataset.mau);
        document.getElementById('selected-color-name').textContent = btn.dataset.ten;
        document.getElementById('f_maMau').value = selectedMau;

        // Đổi ảnh
        const img = colorImgs[selectedMau];
        if (img) {
            document.getElementById('main-img').src = img;
            document.querySelectorAll('.thumb').forEach(t => {
                t.classList.toggle('active', parseInt(t.dataset.color) === selectedMau);
            });
        }
        updatePrice();
    }

    function selectRam(btn) {
        document.querySelectorAll('.ram-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        selectedRam = parseInt(btn.dataset.ram);
        document.getElementById('selected-ram-name').textContent = btn.dataset.ten;
        document.getElementById('f_maRam').value = selectedRam;
        updatePrice();
    }

    function selectImg(img) {
        document.getElementById('main-img').src = img.src;
        document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
        img.classList.add('active');
        // Đổi màu tương ứng
        const colorId = parseInt(img.dataset.color);
        if (!isNaN(colorId)) {
            const btn = document.querySelector(`.color-btn[data-mau="${colorId}"]`);
            if (btn) selectColor(btn);
        }
    }

    function changeQty(delta) {
        const input = document.getElementById('qty-input');
        const max = parseInt(input.max) || 99;
        let val = parseInt(input.value) + delta;
        if (val < 1) val = 1;
        if (val > max) val = max;
        input.value = val;
        document.getElementById('f_soLuong').value = val;
    }

    document.getElementById('qty-input').addEventListener('input', function() {
        document.getElementById('f_soLuong').value = this.value;
    });
    </script>
</body>
</html>
