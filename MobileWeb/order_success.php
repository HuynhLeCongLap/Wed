<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['user'])) { header("Location: pages/login.php"); exit(); }

$maHoaDon    = isset($_GET['id']) ? intval($_GET['id']) : 0;
$tenDangNhap = $_SESSION['user'];

$stmt = mysqli_prepare($conn, "SELECT * FROM hoadon WHERE MaHoaDon=? AND TenDangNhap=?");
mysqli_stmt_bind_param($stmt,'is',$maHoaDon,$tenDangNhap);
mysqli_stmt_execute($stmt);
$hoaDon = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$hoaDon) { header("Location: index.php"); exit(); }

// Lấy sản phẩm trong đơn
$stmt = mysqli_prepare($conn,
    "SELECT ct.SoLuong, ct.ThanhTien, ct.TenMau, ct.KichThuoc, sp.TenSanPham,
     (SELECT DiaChiAnh FROM image WHERE MaSanPham=ct.MaSanPham LIMIT 1) AS Anh
     FROM chitiethoadon ct JOIN sanpham sp ON sp.MaSanPham=ct.MaSanPham
     WHERE ct.MaHoaDon=?");
mysqli_stmt_bind_param($stmt,'i',$maHoaDon);
mysqli_stmt_execute($stmt);
$items = mysqli_fetch_all(mysqli_stmt_get_result($stmt),MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt hàng thành công — Mobile Web</title>
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#3498db;--red:#e74c3c;--green:#2ecc71;--dark:#1a202c;
              --gray:#718096;--light:#f7f8fa;--white:#fff;--radius:14px;
              --shadow:0 4px 20px rgba(0,0,0,.08)}
        body{font-family:'Segoe UI',sans-serif;background:var(--light);color:var(--dark);line-height:1.6}
        a{text-decoration:none;color:inherit}

        /* NAVBAR */
        .navbar{position:sticky;top:0;z-index:200;background:rgba(255,255,255,.95);
            backdrop-filter:blur(12px);border-bottom:1px solid #e8ecf0;box-shadow:0 2px 12px rgba(0,0,0,.06)}
        .navbar-inner{display:flex;align-items:center;height:64px;gap:20px;
            max-width:1240px;margin:0 auto;padding:0 20px}
        .navbar-logo{font-size:20px;font-weight:800;
            background:linear-gradient(135deg,#3498db,#2980b9);
            -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
        .nav-links{display:flex;align-items:center;gap:4px;margin-left:auto}
        .nav-link{padding:8px 14px;border-radius:8px;font-size:14px;font-weight:500;
            color:var(--gray);transition:all .2s}
        .nav-link:hover{background:var(--light);color:var(--dark)}
        .cart-pill{display:flex;align-items:center;gap:6px;background:var(--blue);color:#fff;
            padding:8px 16px;border-radius:50px;font-size:14px;font-weight:600}
        .cart-pill:hover{background:#2980b9;color:#fff}

        /* PAGE */
        .container{max-width:760px;margin:0 auto;padding:0 20px}
        .page-wrap{padding:40px 0 60px}

        /* SUCCESS CARD */
        .success-card{background:var(--white);border-radius:20px;
            box-shadow:0 12px 40px rgba(0,0,0,.1);overflow:hidden}

        .success-hero{background:linear-gradient(135deg,#2ecc71,#27ae60);
            padding:48px 32px;text-align:center;position:relative;overflow:hidden}
        .success-hero::before{content:'';position:absolute;width:300px;height:300px;
            background:rgba(255,255,255,.08);border-radius:50%;top:-100px;right:-60px}
        .success-hero::after{content:'';position:absolute;width:200px;height:200px;
            background:rgba(255,255,255,.06);border-radius:50%;bottom:-80px;left:-40px}
        .check-circle{width:80px;height:80px;background:rgba(255,255,255,.25);
            border-radius:50%;display:flex;align-items:center;justify-content:center;
            font-size:40px;margin:0 auto 16px;animation:popIn .5s cubic-bezier(.34,1.56,.64,1)}
        @keyframes popIn{from{transform:scale(0);opacity:0}to{transform:scale(1);opacity:1}}
        .success-hero h1{font-size:26px;font-weight:800;color:#fff;margin-bottom:6px}
        .success-hero p{color:rgba(255,255,255,.85);font-size:15px}

        /* ORDER INFO */
        .order-info{padding:28px 32px}
        .order-number{display:flex;align-items:center;justify-content:center;
            gap:10px;margin-bottom:24px}
        .order-number-label{font-size:14px;color:var(--gray)}
        .order-number-val{font-size:22px;font-weight:900;color:var(--blue)}

        .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;
            background:#f7f8fa;border-radius:12px;overflow:hidden;margin-bottom:24px}
        .info-cell{padding:14px 18px;border-bottom:1px solid #eee}
        .info-cell:nth-last-child(-n+2){border-bottom:none}
        .info-cell:nth-child(odd){border-right:1px solid #eee}
        .info-cell-label{font-size:12px;color:var(--gray);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
        .info-cell-val{font-size:14px;font-weight:700;color:var(--dark)}
        @media(max-width:560px){.info-grid{grid-template-columns:1fr}
            .info-cell:nth-child(odd){border-right:none}}

        /* BANK INFO */
        .bank-box{background:#fef9e7;border:1px solid #f9ca24;border-radius:12px;
            padding:18px 20px;margin-bottom:24px}
        .bank-box h3{font-size:15px;font-weight:700;color:#d68910;margin-bottom:10px}
        .bank-row{display:flex;justify-content:space-between;font-size:14px;
            padding:6px 0;border-bottom:1px solid rgba(249,202,36,.3)}
        .bank-row:last-child{border-bottom:none}
        .bank-key{color:var(--gray)}
        .bank-val{font-weight:700;color:var(--dark)}

        /* PRODUCTS MINI */
        .products-mini{margin-bottom:24px}
        .products-mini-title{font-size:14px;font-weight:700;color:var(--gray);
            margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px}
        .mini-item{display:flex;align-items:center;gap:12px;padding:10px 0;
            border-bottom:1px solid #f7f8fa}
        .mini-item:last-child{border-bottom:none}
        .mini-img{width:52px;height:52px;object-fit:contain;border-radius:10px;
            background:#f7f8fa;padding:4px;border:1px solid #eee;flex-shrink:0}
        .mini-name{font-weight:600;font-size:14px;color:var(--dark)}
        .mini-meta{font-size:12px;color:var(--gray);margin-top:2px}
        .mini-price{margin-left:auto;font-weight:700;color:var(--red);white-space:nowrap;padding-left:12px}

        /* TOTAL */
        .total-bar{display:flex;justify-content:space-between;align-items:center;
            background:#f7f8fa;border-radius:10px;padding:16px 20px;margin-bottom:24px}
        .total-label{font-size:15px;font-weight:600;color:var(--gray)}
        .total-val{font-size:22px;font-weight:900;color:var(--red)}

        /* ACTIONS */
        .actions{display:flex;gap:12px;flex-wrap:wrap}
        .btn{display:inline-flex;align-items:center;gap:7px;padding:12px 22px;
            border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;
            border:none;transition:all .2s;font-family:inherit;flex:1;justify-content:center}
        .btn:hover{transform:translateY(-1px)}
        .btn-blue{background:var(--blue);color:#fff}
        .btn-blue:hover{background:#2980b9;color:#fff;box-shadow:0 6px 20px rgba(52,152,219,.3)}
        .btn-green{background:var(--green);color:#fff}
        .btn-green:hover{background:#27ae60;color:#fff;box-shadow:0 6px 20px rgba(46,204,113,.3)}
        .btn-outline{background:#fff;color:var(--dark);border:1.5px solid #e2e8f0;flex:0 0 auto}
        .btn-outline:hover{border-color:var(--blue);color:var(--blue)}

        .footer{background:var(--dark);color:rgba(255,255,255,.55);
            text-align:center;padding:24px;margin-top:40px;font-size:13px}
    </style>
</head>
<body>

<header class="navbar">
    <div class="navbar-inner">
        <a href="index.php" class="navbar-logo">📱 MobileWeb</a>
        <nav class="nav-links">
            <a href="index.php" class="nav-link">🏠 Trang chủ</a>
            <a href="cart.php" class="cart-pill">🛒 Giỏ hàng</a>
            <a href="my_orders.php" class="nav-link">📦 Đơn hàng</a>
            <a href="pages/profile.php" class="nav-link">👤 Tài khoản</a>
        </nav>
    </div>
</header>

<main class="container page-wrap">
    <div class="success-card">

        <!-- Hero -->
        <div class="success-hero">
            <div class="check-circle">✓</div>
            <h1>Đặt hàng thành công!</h1>
            <p>Cảm ơn bạn đã mua sắm tại Mobile Web 🎉</p>
        </div>

        <!-- Body -->
        <div class="order-info">

            <!-- Mã đơn -->
            <div class="order-number">
                <span class="order-number-label">Mã đơn hàng của bạn:</span>
                <span class="order-number-val">#<?= str_pad($maHoaDon,5,'0',STR_PAD_LEFT) ?></span>
            </div>

            <!-- Thông tin -->
            <div class="info-grid">
                <div class="info-cell">
                    <div class="info-cell-label">Người nhận</div>
                    <div class="info-cell-val"><?= htmlspecialchars($hoaDon['HoTenNhan'] ?? '—') ?></div>
                </div>
                <div class="info-cell">
                    <div class="info-cell-label">Số điện thoại</div>
                    <div class="info-cell-val"><?= htmlspecialchars($hoaDon['SoDienThoaiNhan'] ?? '—') ?></div>
                </div>
                <div class="info-cell">
                    <div class="info-cell-label">Ngày đặt</div>
                    <div class="info-cell-val"><?= date('d/m/Y', strtotime($hoaDon['NgayLap'])) ?></div>
                </div>
                <div class="info-cell">
                    <div class="info-cell-label">Thanh toán</div>
                    <div class="info-cell-val">
                        <?= ($hoaDon['PhuongThucThanhToan'] ?? 'COD') === 'COD' ? '💵 COD' : '🏦 Chuyển khoản' ?>
                    </div>
                </div>
                <div class="info-cell" style="grid-column:1/-1">
                    <div class="info-cell-label">Địa chỉ giao hàng</div>
                    <div class="info-cell-val"><?= htmlspecialchars($hoaDon['DiaChiNhan'] ?? '—') ?></div>
                </div>
            </div>

            <!-- Thông tin chuyển khoản -->
            <?php if (($hoaDon['PhuongThucThanhToan'] ?? 'COD') === 'BANK'): ?>
            <div class="bank-box">
                <h3>💳 Thông tin chuyển khoản</h3>
                <div class="bank-row"><span class="bank-key">Ngân hàng</span><span class="bank-val">Vietcombank</span></div>
                <div class="bank-row"><span class="bank-key">Số tài khoản</span><span class="bank-val">0123 456 789</span></div>
                <div class="bank-row"><span class="bank-key">Chủ tài khoản</span><span class="bank-val">MOBILE WEB</span></div>
                <div class="bank-row">
                    <span class="bank-key">Nội dung CK</span>
                    <span class="bank-val">#<?= str_pad($maHoaDon,5,'0',STR_PAD_LEFT) ?> <?= htmlspecialchars($hoaDon['HoTenNhan'] ?? '') ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Sản phẩm -->
            <?php if (!empty($items)): ?>
            <div class="products-mini">
                <div class="products-mini-title">🛍 Sản phẩm đã đặt</div>
                <?php foreach ($items as $item): ?>
                <div class="mini-item">
                    <?php if (!empty($item['Anh'])): ?>
                        <img class="mini-img" src="<?= htmlspecialchars($item['Anh']) ?>"
                             alt="" onerror="this.src='';this.style.background='#eee'">
                    <?php else: ?>
                        <div class="mini-img" style="display:flex;align-items:center;justify-content:center;font-size:24px">📱</div>
                    <?php endif; ?>
                    <div>
                        <div class="mini-name"><?= htmlspecialchars($item['TenSanPham']) ?></div>
                        <div class="mini-meta"><?= htmlspecialchars($item['TenMau']) ?> · <?= htmlspecialchars($item['KichThuoc']) ?> · SL: <?= $item['SoLuong'] ?></div>
                    </div>
                    <div class="mini-price"><?= number_format($item['ThanhTien'],0,',','.') ?>đ</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Tổng tiền -->
            <div class="total-bar">
                <span class="total-label">Tổng cộng thanh toán:</span>
                <span class="total-val"><?= number_format($hoaDon['TongTien'],0,',','.') ?>đ</span>
            </div>

            <!-- Actions -->
            <div class="actions">
                <a href="my_orders.php" class="btn btn-blue">📦 Xem đơn hàng</a>
                <a href="print_bill.php?id=<?= $maHoaDon ?>" target="_blank" class="btn btn-green">🖨 In hóa đơn</a>
                <a href="index.php" class="btn btn-outline">← Tiếp tục mua</a>
            </div>

        </div>
    </div>
</main>

<footer class="footer">© <?= date('Y') ?> Mobile Web — Đồ án cuối kỳ</footer>
</body>
</html>
