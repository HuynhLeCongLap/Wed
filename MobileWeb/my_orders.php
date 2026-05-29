<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['user'])) {
    header("Location: pages/login.php");
    exit();
}
$tenDangNhap = $_SESSION['user'];

$filterStatus = $_GET['status'] ?? 'all';
$validStatuses = ['all','Chưa xác nhận','Đã xác nhận','Đang giao','Hoàn thành','Đã hủy'];
if (!in_array($filterStatus, $validStatuses)) $filterStatus = 'all';

if ($filterStatus === 'all') {
    $stmt = mysqli_prepare($conn, "SELECT * FROM hoadon WHERE TenDangNhap=? ORDER BY MaHoaDon DESC");
    mysqli_stmt_bind_param($stmt, 's', $tenDangNhap);
} else {
    $stmt = mysqli_prepare($conn, "SELECT * FROM hoadon WHERE TenDangNhap=? AND TrangThai=? ORDER BY MaHoaDon DESC");
    mysqli_stmt_bind_param($stmt, 'ss', $tenDangNhap, $filterStatus);
}
mysqli_stmt_execute($stmt);
$orders = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Đếm giỏ hàng
$stmtC = mysqli_prepare($conn, "SELECT COUNT(*) FROM giohang WHERE TenDangNhap=?");
mysqli_stmt_bind_param($stmtC, 's', $tenDangNhap);
mysqli_stmt_execute($stmtC);
mysqli_stmt_bind_result($stmtC, $cartCount);
mysqli_stmt_fetch($stmtC);
mysqli_stmt_close($stmtC);

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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng của tôi — Mobile Web</title>
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        :root{
            --blue:#3498db;--red:#e74c3c;--dark:#1a202c;
            --gray:#718096;--light:#f7f8fa;--white:#fff;
            --radius:14px;--shadow:0 4px 20px rgba(0,0,0,.08);
        }
        body{font-family:'Segoe UI',sans-serif;background:var(--light);color:var(--dark);line-height:1.6}
        a{text-decoration:none;color:inherit}

        /* NAVBAR */
        .navbar{position:sticky;top:0;z-index:200;background:rgba(255,255,255,.95);
            backdrop-filter:blur(12px);border-bottom:1px solid #e8ecf0;
            box-shadow:0 2px 12px rgba(0,0,0,.06)}
        .navbar-inner{display:flex;align-items:center;height:64px;gap:20px;
            max-width:1240px;margin:0 auto;padding:0 20px}
        .navbar-logo{font-size:20px;font-weight:800;
            background:linear-gradient(135deg,#3498db,#2980b9);
            -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
            white-space:nowrap;letter-spacing:-.5px}
        .nav-links{display:flex;align-items:center;gap:4px;margin-left:auto}
        .nav-link{display:flex;align-items:center;gap:5px;padding:8px 14px;
            border-radius:8px;font-size:14px;font-weight:500;color:var(--gray);transition:all .2s}
        .nav-link:hover{background:var(--light);color:var(--dark)}
        .nav-link.active{color:var(--blue);font-weight:600;background:#ebf5fb}
        .cart-pill{display:flex;align-items:center;gap:6px;background:var(--blue);color:#fff;
            padding:8px 16px;border-radius:50px;font-size:14px;font-weight:600;transition:all .2s}
        .cart-pill:hover{background:#2980b9;color:#fff}
        .cart-count{background:var(--red);color:#fff;border-radius:50%;min-width:20px;height:20px;
            font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center}

        /* PAGE */
        .container{max-width:1240px;margin:0 auto;padding:0 20px}
        .page-wrap{padding:28px 0 60px}

        .page-header{display:flex;align-items:center;justify-content:space-between;
            margin-bottom:24px;flex-wrap:wrap;gap:12px}
        .page-title{font-size:26px;font-weight:800;color:var(--dark)}
        .page-title span{color:var(--blue)}

        /* STATS ROW */
        .stats-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));
            gap:12px;margin-bottom:24px}
        .stat-pill{background:var(--white);border-radius:var(--radius);
            padding:16px;box-shadow:var(--shadow);display:flex;align-items:center;gap:12px;
            border-left:4px solid transparent}
        .stat-pill-icon{font-size:24px;flex-shrink:0}
        .stat-pill-val{font-size:20px;font-weight:800;color:var(--dark)}
        .stat-pill-lbl{font-size:12px;color:var(--gray)}

        /* FILTER TABS */
        .filter-tabs{display:flex;gap:8px;flex-wrap:wrap;background:var(--white);
            padding:14px 18px;border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:22px;
            overflow-x:auto}
        .filter-tab{padding:8px 16px;border-radius:50px;font-size:13px;font-weight:600;
            color:var(--gray);background:#f0f2f5;white-space:nowrap;transition:all .2s}
        .filter-tab:hover{background:#e0e4e8;color:var(--dark)}
        .filter-tab.active{background:var(--dark);color:#fff}

        /* ORDER CARDS */
        .orders-list{display:flex;flex-direction:column;gap:14px}
        .order-card{background:var(--white);border-radius:var(--radius);
            box-shadow:var(--shadow);overflow:hidden;border:1.5px solid transparent;
            transition:all .2s}
        .order-card:hover{border-color:rgba(52,152,219,.2);
            box-shadow:0 8px 32px rgba(0,0,0,.12);transform:translateY(-2px)}

        .order-card-top{display:flex;justify-content:space-between;align-items:center;
            padding:16px 20px;background:#fafbfc;border-bottom:1px solid #f0f0f0;flex-wrap:wrap;gap:10px}
        .order-meta{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
        .order-id{font-weight:800;font-size:16px;color:var(--dark)}
        .order-date{font-size:13px;color:var(--gray)}

        .status-badge{display:inline-flex;align-items:center;gap:6px;
            padding:5px 14px;border-radius:50px;font-size:13px;font-weight:700;border:1px solid}

        .order-card-body{padding:16px 20px}
        .order-items-preview{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}
        .preview-img{width:64px;height:64px;object-fit:contain;border-radius:10px;
            background:#f0f2f5;padding:4px;border:1px solid #eee;flex-shrink:0}
        .preview-info{flex:1;min-width:120px}
        .preview-name{font-weight:600;font-size:14px;color:var(--dark);
            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:240px}
        .preview-meta{font-size:12px;color:var(--gray);margin-top:3px}
        .more-badge{background:#f0f2f5;border-radius:8px;padding:4px 10px;
            font-size:12px;color:var(--gray);align-self:center;white-space:nowrap}

        .order-card-footer{display:flex;justify-content:space-between;align-items:center;
            padding:14px 20px;border-top:1px solid #f0f0f0;flex-wrap:wrap;gap:10px}
        .order-total{font-size:15px;color:var(--gray)}
        .order-total strong{color:var(--red);font-size:18px;font-weight:800}
        .btn-detail{display:inline-flex;align-items:center;gap:6px;
            padding:9px 20px;background:var(--blue);color:#fff;
            border-radius:9px;font-size:13px;font-weight:700;transition:all .2s}
        .btn-detail:hover{background:#2980b9;color:#fff;transform:translateX(2px)}

        /* EMPTY */
        .empty{text-align:center;padding:80px 20px;background:var(--white);
            border-radius:var(--radius);box-shadow:var(--shadow)}
        .empty-icon{font-size:64px;margin-bottom:16px}
        .empty h3{font-size:20px;font-weight:700;margin-bottom:8px}
        .empty p{color:var(--gray);margin-bottom:24px}
        .btn-shop{display:inline-block;padding:13px 28px;background:var(--blue);
            color:#fff;border-radius:10px;font-weight:700;transition:all .2s}
        .btn-shop:hover{background:#2980b9;color:#fff;transform:translateY(-1px)}

        /* FOOTER */
        .footer{background:var(--dark);color:rgba(255,255,255,.55);
            text-align:center;padding:24px;margin-top:20px;font-size:13px}

        @media(max-width:600px){
            .page-title{font-size:20px}
            .preview-name{max-width:160px}
        }
    </style>
</head>
<body>
<header class="navbar">
    <div class="navbar-inner">
        <a href="index.php" class="navbar-logo">📱 MobileWeb</a>
        <nav class="nav-links">
            <a href="index.php" class="nav-link">🏠 <span>Trang chủ</span></a>
            <a href="cart.php" class="cart-pill">
                🛒 Giỏ hàng
                <?php if ($cartCount > 0): ?>
                    <span class="cart-count"><?= $cartCount ?></span>
                <?php endif; ?>
            </a>
            <a href="my_orders.php" class="nav-link active">📦 <span>Đơn hàng</span></a>
            <a href="pages/profile.php" class="nav-link">👤 <span>Tài khoản</span></a>
        </nav>
    </div>
</header>

<main class="container page-wrap">
    <div class="page-header">
        <h1 class="page-title">Đơn hàng <span>của tôi</span></h1>
        <a href="index.php" class="btn-shop" style="font-size:13px;padding:9px 20px;">+ Mua thêm</a>
    </div>

    <!-- Stats -->
    <?php
    $countAll  = count($orders);
    $countPend = count(array_filter($orders, fn($o)=>$o['TrangThai']==='Chưa xác nhận'));
    $countShip = count(array_filter($orders, fn($o)=>$o['TrangThai']==='Đang giao'));
    $countDone = count(array_filter($orders, fn($o)=>$o['TrangThai']==='Hoàn thành'));
    $totalSpent = array_sum(array_column($orders,'TongTien'));
    ?>
    <div class="stats-row">
        <div class="stat-pill" style="border-color:#3498db">
            <div class="stat-pill-icon">📦</div>
            <div><div class="stat-pill-val"><?= $countAll ?></div><div class="stat-pill-lbl">Tất cả đơn</div></div>
        </div>
        <div class="stat-pill" style="border-color:#f39c12">
            <div class="stat-pill-icon">🕐</div>
            <div><div class="stat-pill-val"><?= $countPend ?></div><div class="stat-pill-lbl">Chờ xác nhận</div></div>
        </div>
        <div class="stat-pill" style="border-color:#1abc9c">
            <div class="stat-pill-icon">🚚</div>
            <div><div class="stat-pill-val"><?= $countShip ?></div><div class="stat-pill-lbl">Đang giao</div></div>
        </div>
        <div class="stat-pill" style="border-color:#2ecc71">
            <div class="stat-pill-icon">🎉</div>
            <div><div class="stat-pill-val"><?= $countDone ?></div><div class="stat-pill-lbl">Hoàn thành</div></div>
        </div>
        <div class="stat-pill" style="border-color:#e74c3c">
            <div class="stat-pill-icon">💰</div>
            <div><div class="stat-pill-val"><?= number_format($totalSpent/1000000,1) ?>M</div><div class="stat-pill-lbl">Tổng chi tiêu</div></div>
        </div>
    </div>

    <!-- Filter tabs -->
    <div class="filter-tabs">
        <?php
        $tabs = ['all'=>'Tất cả','Chưa xác nhận'=>'Chờ xác nhận',
                 'Đã xác nhận'=>'Đã xác nhận','Đang giao'=>'Đang giao',
                 'Hoàn thành'=>'Hoàn thành','Đã hủy'=>'Đã hủy'];
        foreach ($tabs as $val => $lbl):
        ?>
        <a href="?status=<?= urlencode($val) ?>"
           class="filter-tab <?= $filterStatus===$val?'active':'' ?>">
            <?= statusInfo($val === 'all' ? 'default' : $val)['icon'] ?> <?= $lbl ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- List -->
    <?php if (empty($orders)): ?>
        <div class="empty">
            <div class="empty-icon">📭</div>
            <h3>Không có đơn hàng nào</h3>
            <p>Hãy bắt đầu mua sắm để tạo đơn hàng đầu tiên!</p>
            <a href="index.php" class="btn-shop">Mua sắm ngay →</a>
        </div>
    <?php else: ?>
        <div class="orders-list">
        <?php foreach ($orders as $order):
            $si = statusInfo($order['TrangThai']);

            // Lấy sản phẩm trong đơn
            $stmtI = mysqli_prepare($conn,
                "SELECT ct.SoLuong, ct.TenMau, ct.KichThuoc, sp.TenSanPham,
                 (SELECT DiaChiAnh FROM image WHERE MaSanPham=ct.MaSanPham LIMIT 1) AS Anh
                 FROM chitiethoadon ct
                 JOIN sanpham sp ON sp.MaSanPham=ct.MaSanPham
                 WHERE ct.MaHoaDon=?");
            mysqli_stmt_bind_param($stmtI,'i',$order['MaHoaDon']);
            mysqli_stmt_execute($stmtI);
            $items = mysqli_fetch_all(mysqli_stmt_get_result($stmtI),MYSQLI_ASSOC);
            mysqli_stmt_close($stmtI);
        ?>
        <div class="order-card">
            <div class="order-card-top">
                <div class="order-meta">
                    <span class="order-id">#<?= str_pad($order['MaHoaDon'],5,'0',STR_PAD_LEFT) ?></span>
                    <span class="order-date">📅 <?= date('d/m/Y', strtotime($order['NgayLap'])) ?></span>
                    <span class="order-date">🛍 <?= count($items) ?> sản phẩm</span>
                </div>
                <span class="status-badge"
                      style="color:<?= $si['color'] ?>;background:<?= $si['bg'] ?>;border-color:<?= $si['border'] ?>">
                    <?= $si['icon'] ?> <?= htmlspecialchars($order['TrangThai']) ?>
                </span>
            </div>

            <div class="order-card-body">
                <div class="order-items-preview">
                    <?php foreach (array_slice($items,0,3) as $item): ?>
                    <div style="display:flex;align-items:center;gap:10px;min-width:0">
                        <?php if (!empty($item['Anh'])): ?>
                            <img class="preview-img" src="<?= htmlspecialchars($item['Anh']) ?>"
                                 alt="" onerror="this.src='';this.style.background='#f0f2f5'">
                        <?php else: ?>
                            <div class="preview-img" style="display:flex;align-items:center;justify-content:center;font-size:28px;">📱</div>
                        <?php endif; ?>
                        <div class="preview-info">
                            <div class="preview-name"><?= htmlspecialchars($item['TenSanPham']) ?></div>
                            <div class="preview-meta"><?= htmlspecialchars($item['TenMau']) ?> · <?= htmlspecialchars($item['KichThuoc']) ?> · SL: <?= $item['SoLuong'] ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (count($items) > 3): ?>
                        <div class="more-badge">+<?= count($items)-3 ?> sản phẩm</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="order-card-footer">
                <div class="order-total">
                    Tổng cộng: <strong><?= number_format($order['TongTien'],0,',','.') ?>đ</strong>
                </div>
                <a href="order_detail.php?id=<?= $order['MaHoaDon'] ?>" class="btn-detail">
                    Xem chi tiết →
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<footer class="footer">© <?= date('Y') ?> Mobile Web — Đồ án cuối kỳ</footer>
</body>
</html>
