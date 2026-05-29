<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['user'])) { header("Location: pages/login.php"); exit(); }
$tenDangNhap = $_SESSION['user'];

$maHoaDon = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($maHoaDon <= 0) { header("Location: my_orders.php"); exit(); }

$stmt = mysqli_prepare($conn, "SELECT * FROM hoadon WHERE MaHoaDon=? AND TenDangNhap=?");
mysqli_stmt_bind_param($stmt,'is',$maHoaDon,$tenDangNhap);
mysqli_stmt_execute($stmt);
$hoaDon = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$hoaDon) { header("Location: my_orders.php"); exit(); }

// Sản phẩm
$stmt = mysqli_prepare($conn,
    "SELECT ct.*, sp.TenSanPham,
     (SELECT DiaChiAnh FROM image WHERE MaSanPham=ct.MaSanPham LIMIT 1) AS Anh
     FROM chitiethoadon ct JOIN sanpham sp ON ct.MaSanPham=sp.MaSanPham
     WHERE ct.MaHoaDon=?");
mysqli_stmt_bind_param($stmt,'i',$maHoaDon);
mysqli_stmt_execute($stmt);
$chiTiet = mysqli_fetch_all(mysqli_stmt_get_result($stmt),MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Tracking
$stmt = mysqli_prepare($conn,"SELECT * FROM order_tracking WHERE MaHoaDon=? ORDER BY ThoiGian ASC");
mysqli_stmt_bind_param($stmt,'i',$maHoaDon);
mysqli_stmt_execute($stmt);
$tracking = mysqli_fetch_all(mysqli_stmt_get_result($stmt),MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

function statusInfo($s) {
    return match($s) {
        'Chưa xác nhận' => ['icon'=>'🕐','color'=>'#d68910','bg'=>'#fef9e7','border'=>'#f9ca24'],
        'Đã xác nhận'   => ['icon'=>'✅','color'=>'#2874a6','bg'=>'#d6eaf8','border'=>'#85c1e9'],
        'Đang giao'     => ['icon'=>'🚚','color'=>'#1f618d','bg'=>'#d4e6f1','border'=>'#7fb3d3'],
        'Hoàn thành'    => ['icon'=>'🎉','color'=>'#196f3d','bg'=>'#d5f5e3','border'=>'#82e0aa'],
        'Đã hủy'        => ['icon'=>'❌','color'=>'#922b21','bg'=>'#fadbd8','border'=>'#f1948a'],
        default         => ['icon'=>'📋','color'=>'#666','bg'=>'#f0f0f0','border'=>'#ccc'],
    };
}

$steps = ['Chưa xác nhận','Đã xác nhận','Đang giao','Hoàn thành'];
$isCancelled = $hoaDon['TrangThai'] === 'Đã hủy';
$canCancel   = $hoaDon['TrangThai'] === 'Chưa xác nhận';
$currentStep = array_search($hoaDon['TrangThai'], $steps);
$si = statusInfo($hoaDon['TrangThai']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng #<?= str_pad($maHoaDon,5,'0',STR_PAD_LEFT) ?> — Mobile Web</title>
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#3498db;--red:#e74c3c;--dark:#1a202c;--gray:#718096;
              --light:#f7f8fa;--white:#fff;--radius:14px;--shadow:0 4px 20px rgba(0,0,0,.08)}
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
        .nav-link.active{color:var(--blue);font-weight:600;background:#ebf5fb}
        .cart-pill{display:flex;align-items:center;gap:6px;background:var(--blue);color:#fff;
            padding:8px 16px;border-radius:50px;font-size:14px;font-weight:600}
        .cart-pill:hover{background:#2980b9;color:#fff}

        /* LAYOUT */
        .container{max-width:1240px;margin:0 auto;padding:0 20px}
        .page-wrap{padding:24px 0 60px}

        /* BREADCRUMB */
        .breadcrumb{display:flex;align-items:center;gap:8px;font-size:14px;
            color:var(--gray);margin-bottom:20px}
        .breadcrumb a{color:var(--blue)}
        .breadcrumb a:hover{text-decoration:underline}

        /* ORDER HEADER */
        .order-header{background:var(--white);border-radius:var(--radius);
            box-shadow:var(--shadow);padding:24px 28px;margin-bottom:20px;
            display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;
            border-left:5px solid}
        .order-header-info h1{font-size:22px;font-weight:800;margin-bottom:4px}
        .order-header-info p{font-size:14px;color:var(--gray)}
        .status-badge{display:inline-flex;align-items:center;gap:7px;
            padding:8px 18px;border-radius:50px;font-size:14px;font-weight:700;border:1px solid}

        /* CARD */
        .card{background:var(--white);border-radius:var(--radius);
            box-shadow:var(--shadow);padding:24px;margin-bottom:18px}
        .card-title{font-size:16px;font-weight:800;color:var(--dark);
            margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid #f0f0f0;
            display:flex;align-items:center;gap:8px}

        /* GRID */
        .detail-grid{display:grid;grid-template-columns:1fr 1.8fr;gap:18px;margin-bottom:18px}
        @media(max-width:900px){.detail-grid{grid-template-columns:1fr}}

        /* INFO TABLE */
        .info-row{display:flex;justify-content:space-between;padding:10px 0;
            border-bottom:1px solid #f7f8fa;font-size:14px;gap:12px}
        .info-row:last-child{border-bottom:none}
        .info-label{color:var(--gray);font-weight:500;flex-shrink:0}
        .info-value{font-weight:600;color:var(--dark);text-align:right}

        /* PRODUCTS TABLE */
        .products-table{width:100%;border-collapse:collapse}
        .products-table th{padding:10px 12px;font-size:12px;font-weight:700;color:var(--gray);
            text-transform:uppercase;letter-spacing:.5px;text-align:left;
            background:#f7f8fa;border-bottom:1px solid #eee}
        .products-table td{padding:14px 12px;border-bottom:1px solid #f7f8fa;vertical-align:middle;font-size:14px}
        .products-table tr:last-child td{border-bottom:none}
        .product-cell{display:flex;align-items:center;gap:12px}
        .product-img{width:56px;height:56px;object-fit:contain;border-radius:10px;
            background:#f7f8fa;padding:4px;border:1px solid #eee;flex-shrink:0}
        .product-name{font-weight:700;color:var(--dark);margin-bottom:3px}
        .product-meta{font-size:12px;color:var(--gray)}
        .tfoot-row td{padding-top:16px;font-size:16px;font-weight:800;
            border-top:2px solid #f0f0f0;border-bottom:none}
        .price-red{color:var(--red)}

        /* TIMELINE */
        .timeline{position:relative;padding:0 0 8px}
        .timeline-steps{display:flex;justify-content:space-between;
            position:relative;margin-bottom:24px}
        .tl-line{position:absolute;top:20px;left:20px;right:20px;height:3px;background:#e8ecf0;z-index:0}
        .tl-progress{position:absolute;top:20px;left:20px;height:3px;background:var(--blue);z-index:1;transition:width .5s}
        .tl-step{display:flex;flex-direction:column;align-items:center;z-index:2;flex:1}
        .tl-dot{width:40px;height:40px;border-radius:50%;background:#e8ecf0;
            display:flex;align-items:center;justify-content:center;font-size:18px;
            border:3px solid #fff;box-shadow:0 0 0 2px #e8ecf0;margin-bottom:8px;transition:all .3s}
        .tl-dot.done{background:var(--blue);box-shadow:0 0 0 2px var(--blue)}
        .tl-dot.current{background:#2ecc71;box-shadow:0 0 0 2px #2ecc71;
            animation:pulse 1.5s infinite}
        .tl-dot.cancelled{background:var(--red);box-shadow:0 0 0 2px var(--red)}
        @keyframes pulse{0%,100%{box-shadow:0 0 0 2px #2ecc71}50%{box-shadow:0 0 0 8px rgba(46,204,113,.2)}}
        .tl-label{font-size:12px;font-weight:600;color:var(--gray);text-align:center;max-width:70px}
        .tl-label.done{color:var(--blue)}
        .tl-label.current{color:#2ecc71}

        /* TRACKING LOG */
        .track-log{margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0}
        .track-log-title{font-size:13px;font-weight:700;color:var(--gray);margin-bottom:10px}
        .track-item{display:flex;gap:12px;padding:8px 0;border-bottom:1px solid #f7f8fa}
        .track-item:last-child{border-bottom:none}
        .track-dot{width:10px;height:10px;border-radius:50%;background:var(--blue);flex-shrink:0;margin-top:5px}
        .track-status{font-weight:700;font-size:14px}
        .track-note{font-size:13px;color:var(--gray)}
        .track-time{font-size:12px;color:#aaa}

        /* ACTIONS */
        .action-bar{display:flex;gap:12px;flex-wrap:wrap;justify-content:flex-end;
            padding:20px;background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow)}
        .btn{display:inline-flex;align-items:center;gap:7px;padding:11px 22px;
            border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;
            border:none;transition:all .2s;font-family:inherit}
        .btn:hover{transform:translateY(-1px)}
        .btn-blue{background:var(--blue);color:#fff}
        .btn-blue:hover{background:#2980b9;color:#fff}
        .btn-green{background:#2ecc71;color:#fff}
        .btn-green:hover{background:#27ae60;color:#fff}
        .btn-red{background:var(--red);color:#fff}
        .btn-red:hover{background:#c0392b;color:#fff}
        .btn-outline{background:#fff;color:var(--dark);border:1.5px solid #e2e8f0}
        .btn-outline:hover{border-color:var(--blue);color:var(--blue)}

        /* ALERT */
        #alert-box{display:none;padding:13px 18px;border-radius:10px;
            margin-bottom:16px;font-weight:600;font-size:14px}
        .alert-success{background:#d5f5e3;color:#196f3d;border:1px solid #82e0aa}
        .alert-error{background:#fadbd8;color:#922b21;border:1px solid #f1948a}

        .footer{background:var(--dark);color:rgba(255,255,255,.55);
            text-align:center;padding:24px;margin-top:20px;font-size:13px}
    </style>
</head>
<body>

<header class="navbar">
    <div class="navbar-inner">
        <a href="index.php" class="navbar-logo">📱 MobileWeb</a>
        <nav class="nav-links">
            <a href="index.php" class="nav-link">🏠 Trang chủ</a>
            <a href="cart.php" class="cart-pill">🛒 Giỏ hàng</a>
            <a href="my_orders.php" class="nav-link active">📦 Đơn hàng</a>
            <a href="pages/profile.php" class="nav-link">👤 Tài khoản</a>
        </nav>
    </div>
</header>

<main class="container page-wrap">

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="index.php">Trang chủ</a> ›
        <a href="my_orders.php">Đơn hàng của tôi</a> ›
        #<?= str_pad($maHoaDon,5,'0',STR_PAD_LEFT) ?>
    </div>

    <div id="alert-box"></div>

    <!-- Order header -->
    <div class="order-header" style="border-color:<?= $si['color'] ?>">
        <div class="order-header-info">
            <h1>Đơn hàng #<?= str_pad($maHoaDon,5,'0',STR_PAD_LEFT) ?></h1>
            <p>📅 Ngày đặt: <?= date('d/m/Y', strtotime($hoaDon['NgayLap'])) ?></p>
        </div>
        <span class="status-badge"
              style="color:<?= $si['color'] ?>;background:<?= $si['bg'] ?>;border-color:<?= $si['border'] ?>">
            <?= $si['icon'] ?> <?= htmlspecialchars($hoaDon['TrangThai']) ?>
        </span>
    </div>

    <!-- Timeline trạng thái -->
    <div class="card">
        <div class="card-title">🗺 Theo dõi đơn hàng</div>

        <?php if ($isCancelled): ?>
            <div style="text-align:center;padding:24px 0">
                <div style="font-size:56px;margin-bottom:12px">❌</div>
                <p style="font-size:16px;font-weight:700;color:#922b21">Đơn hàng đã bị hủy</p>
            </div>
        <?php else: ?>
            <div class="timeline">
                <div class="timeline-steps" id="tl-steps">
                    <div class="tl-line"></div>
                    <div class="tl-progress" id="tl-progress"></div>
                    <?php
                    $stepIcons = ['📋','✅','🚚','🎉'];
                    $stepLabels = ['Chờ xác nhận','Đã xác nhận','Đang giao','Hoàn thành'];
                    foreach ($steps as $i => $step):
                        if ($currentStep !== false) {
                            if ($i < $currentStep)     $cls = 'done';
                            elseif ($i === $currentStep) $cls = 'current';
                            else                        $cls = '';
                        } else $cls = '';
                    ?>
                    <div class="tl-step">
                        <div class="tl-dot <?= $cls ?>"><?= $stepIcons[$i] ?></div>
                        <div class="tl-label <?= $cls ?>"><?= $stepLabels[$i] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($tracking)): ?>
        <div class="track-log">
            <div class="track-log-title">Lịch sử cập nhật</div>
            <?php foreach (array_reverse($tracking) as $t): ?>
            <div class="track-item">
                <div class="track-dot"></div>
                <div>
                    <div class="track-status"><?= htmlspecialchars($t['TrangThai']) ?></div>
                    <?php if ($t['GhiChu']): ?>
                        <div class="track-note"><?= htmlspecialchars($t['GhiChu']) ?></div>
                    <?php endif; ?>
                    <div class="track-time"><?= date('H:i — d/m/Y', strtotime($t['ThoiGian'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Grid: Thông tin + Sản phẩm -->
    <div class="detail-grid">
        <!-- Thông tin giao hàng -->
        <div class="card">
            <div class="card-title">📍 Thông tin giao hàng</div>
            <div class="info-row">
                <span class="info-label">Người nhận</span>
                <span class="info-value"><?= htmlspecialchars($hoaDon['HoTenNhan'] ?? '—') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Số điện thoại</span>
                <span class="info-value"><?= htmlspecialchars($hoaDon['SoDienThoaiNhan'] ?? '—') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Địa chỉ</span>
                <span class="info-value" style="max-width:200px"><?= htmlspecialchars($hoaDon['DiaChiNhan'] ?? '—') ?></span>
            </div>
            <?php if (!empty($hoaDon['GhiChu'])): ?>
            <div class="info-row">
                <span class="info-label">Ghi chú</span>
                <span class="info-value"><?= htmlspecialchars($hoaDon['GhiChu']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="info-label">Thanh toán</span>
                <span class="info-value">
                    <?= ($hoaDon['PhuongThucThanhToan'] ?? 'COD') === 'COD'
                        ? '💵 Tiền mặt (COD)' : '🏦 Chuyển khoản' ?>
                </span>
            </div>
        </div>

        <!-- Sản phẩm -->
        <div class="card">
            <div class="card-title">🛍 Sản phẩm (<?= count($chiTiet) ?>)</div>
            <table class="products-table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th style="text-align:center">SL</th>
                        <th style="text-align:right">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($chiTiet as $item):
                    $donGia = $item['SoLuong'] > 0 ? $item['ThanhTien'] / $item['SoLuong'] : 0;
                ?>
                    <tr>
                        <td>
                            <div class="product-cell">
                                <?php if (!empty($item['Anh'])): ?>
                                    <img class="product-img" src="<?= htmlspecialchars($item['Anh']) ?>"
                                         alt="" onerror="this.src='';this.style.background='#eee'">
                                <?php else: ?>
                                    <div class="product-img" style="display:flex;align-items:center;justify-content:center;font-size:26px">📱</div>
                                <?php endif; ?>
                                <div>
                                    <div class="product-name"><?= htmlspecialchars($item['TenSanPham']) ?></div>
                                    <div class="product-meta">
                                        <?= htmlspecialchars($item['TenMau']) ?> · <?= htmlspecialchars($item['KichThuoc']) ?>
                                        · <?= number_format($donGia,0,',','.') ?>đ/cái
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align:center;font-weight:700"><?= (int)$item['SoLuong'] ?></td>
                        <td style="text-align:right;font-weight:700;color:var(--red)">
                            <?= number_format($item['ThanhTien'],0,',','.') ?>đ
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="tfoot-row">
                        <td colspan="2" style="text-align:right">Tổng cộng:</td>
                        <td style="text-align:right" class="price-red">
                            <?= number_format($hoaDon['TongTien'],0,',','.') ?>đ
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Action bar -->
    <div class="action-bar">
        <a href="my_orders.php" class="btn btn-outline">← Danh sách đơn</a>
        <a href="print_bill.php?id=<?= $maHoaDon ?>" target="_blank" class="btn btn-green">
            🖨 In hóa đơn
        </a>
        <?php if ($canCancel): ?>
            <button class="btn btn-red" id="btn-cancel" data-id="<?= $maHoaDon ?>">
                ✕ Hủy đơn hàng
            </button>
        <?php endif; ?>
    </div>

</main>

<footer class="footer">© <?= date('Y') ?> Mobile Web — Đồ án cuối kỳ</footer>

<script>
// Timeline progress bar
(function(){
    const steps = document.querySelectorAll('.tl-step');
    const bar   = document.getElementById('tl-progress');
    if (!steps.length || !bar) return;
    const current = document.querySelector('.tl-dot.current');
    if (!current) { bar.style.width='0'; return; }
    const idx  = [...steps].indexOf(current.closest('.tl-step'));
    const total = steps.length - 1;
    const wrap  = document.getElementById('tl-steps');
    const w     = wrap.offsetWidth - 40;
    bar.style.width = Math.round((idx / total) * w) + 'px';
})();

// Hủy đơn
const btnCancel = document.getElementById('btn-cancel');
if (btnCancel) {
    btnCancel.addEventListener('click', function() {
        if (!confirm('Bạn có chắc muốn hủy đơn hàng này không?')) return;
        this.disabled = true; this.textContent = 'Đang hủy...';
        const box = document.getElementById('alert-box');

        fetch('cancel_order.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'MaHoaDon=<?= $maHoaDon ?>'
        })
        .then(r=>r.json())
        .then(d=>{
            box.style.display='block';
            box.className = d.success ? 'alert-success' : 'alert-error';
            box.textContent = d.message;
            box.scrollIntoView({behavior:'smooth'});
            if (d.success) setTimeout(()=>location.reload(), 1500);
            else { btnCancel.disabled=false; btnCancel.textContent='✕ Hủy đơn hàng'; }
        });
    });
}
</script>
</body>
</html>
