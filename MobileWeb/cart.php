<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['user'])) {
    header('Location: pages/login.php');
    exit;
}
$tenDangNhap = $_SESSION['user'];

// ===== XỬ LÝ AJAX CẬP NHẬT SỐ LƯỢNG =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $maHang  = (int)($_POST['ma_hang'] ?? 0);
    $action  = $_POST['action'];

    // Kiểm tra item thuộc về user
    $stmt = mysqli_prepare($conn,
        "SELECT g.MaHang, g.Soluong, g.MaSanPham, g.MauSac, g.KichThuoc
         FROM giohang g WHERE g.MaHang = ? AND g.TenDangNhap = ?");
    mysqli_stmt_bind_param($stmt, 'is', $maHang, $tenDangNhap);
    mysqli_stmt_execute($stmt);
    $item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$item) {
        echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy sản phẩm.']);
        exit;
    }

    if ($action === 'remove') {
        $del = mysqli_prepare($conn, "DELETE FROM giohang WHERE MaHang = ? AND TenDangNhap = ?");
        mysqli_stmt_bind_param($del, 'is', $maHang, $tenDangNhap);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);
        echo json_encode(['ok' => true, 'removed' => true]);
        exit;
    }

    if ($action === 'set_qty') {
        $newQty = max(1, (int)($_POST['qty'] ?? 1));

        // Kiểm tra tồn kho
        $stmtStock = mysqli_prepare($conn,
            "SELECT g.SoLuong FROM giasanpham g
             JOIN colors c ON c.MaMau = g.MaMau
             JOIN ram_rom_option r ON r.MaRam = g.MaRam
             WHERE g.MaSanPham = ? AND c.TenMau = ? AND r.KichThuoc = ?
             LIMIT 1");
        mysqli_stmt_bind_param($stmtStock, 'iss', $item['MaSanPham'], $item['MauSac'], $item['KichThuoc']);
        mysqli_stmt_execute($stmtStock);
        $stock = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtStock));
        mysqli_stmt_close($stmtStock);

        $maxQty = $stock ? (int)$stock['SoLuong'] : 99;
        if ($newQty > $maxQty) $newQty = $maxQty;

        $upd = mysqli_prepare($conn, "UPDATE giohang SET Soluong = ? WHERE MaHang = ? AND TenDangNhap = ?");
        mysqli_stmt_bind_param($upd, 'iis', $newQty, $maHang, $tenDangNhap);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
        echo json_encode(['ok' => true, 'qty' => $newQty, 'maxQty' => $maxQty]);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Action không hợp lệ.']);
    exit;
}

// ===== LẤY DANH SÁCH GIỎ HÀNG =====
$stmt = mysqli_prepare($conn,
    "SELECT g.*,
     (SELECT gs.SoLuong FROM giasanpham gs
      JOIN colors c ON c.MaMau = gs.MaMau
      JOIN ram_rom_option r ON r.MaRam = gs.MaRam
      WHERE gs.MaSanPham = g.MaSanPham AND c.TenMau = g.MauSac AND r.KichThuoc = g.KichThuoc
      LIMIT 1) AS TonKho
     FROM giohang g WHERE g.TenDangNhap = ? ORDER BY g.MaHang DESC");
mysqli_stmt_bind_param($stmt, 's', $tenDangNhap);
mysqli_stmt_execute($stmt);
$cartItems = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$tongTien = 0;
foreach ($cartItems as $item) {
    $tongTien += $item['GiaMoi'] * $item['Soluong'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng - Mobile Web</title>
    <link rel="stylesheet" href="assets/css/checkout.css">
    <style>
        .cart-item-row {
            display: flex;
            gap: 14px;
            background: white;
            padding: 14px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
            margin-bottom: 10px;
            align-items: center;
        }
        .cart-item-row img {
            width: 80px; height: 80px;
            object-fit: cover;
            border-radius: 8px;
            background: #f0f0f0;
        }
        .cart-item-info { flex: 1; min-width: 0; }
        .cart-item-info h3 { font-size: 15px; color: #2c3e50; margin-bottom: 4px; }
        .cart-item-info .meta { color: #888; font-size: 13px; margin-bottom: 8px; }

        .qty-control-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .qty-box {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 6px;
            overflow: hidden;
        }
        .qty-box button {
            width: 32px; height: 32px;
            border: none;
            background: #f0f2f5;
            font-size: 18px;
            cursor: pointer;
            color: #333;
            padding: 0;
            line-height: 1;
            transition: background .2s;
        }
        .qty-box button:hover { background: #e0e2e5; }
        .qty-box input {
            width: 50px;
            border: none;
            border-left: 1px solid #ddd;
            border-right: 1px solid #ddd;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            padding: 0;
            height: 32px;
        }
        .item-subtotal {
            color: #e74c3c;
            font-weight: 700;
            font-size: 16px;
            min-width: 110px;
            text-align: right;
        }
        .item-stock-hint {
            font-size: 12px;
            color: #888;
        }
        .item-stock-hint.low { color: #d68910; }
        .item-stock-hint.out { color: #e74c3c; }

        .btn-del {
            background: transparent;
            border: none;
            color: #bbb;
            font-size: 22px;
            cursor: pointer;
            padding: 6px;
            line-height: 1;
            transition: color .2s;
        }
        .btn-del:hover { color: #e74c3c; }

        .cart-summary-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
            margin-top: 16px;
        }
        .select-all-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            padding: 8px;
        }
        .select-all-row label { font-weight: 600; color: #2c3e50; }
        input[type=checkbox] { transform: scale(1.2); accent-color: #3498db; }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a href="index.php" class="logo">Mobile Web</a>
            <nav>
                <a href="index.php">Trang chủ</a>
                <a href="cart.php" class="active">Giỏ hàng</a>
                <a href="my_orders.php">Đơn hàng</a>
                <a href="pages/profile.php">Tài khoản</a>
            </nav>
        </div>
    </header>

    <main class="container" style="padding: 20px;">
        <h1 class="page-title">Giỏ hàng của bạn</h1>

        <?php if (empty($cartItems)): ?>
            <div class="empty-state">
                <p style="font-size:18px; margin-bottom:10px;">🛒 Giỏ hàng đang trống</p>
                <a href="index.php" class="btn btn-primary" style="display:inline-block; width:auto; padding:12px 28px;">
                    Mua sắm ngay
                </a>
            </div>
        <?php else: ?>
            <form action="checkout.php" method="post" id="cart-form">
                <div class="select-all-row">
                    <input type="checkbox" id="select-all" checked>
                    <label for="select-all">Chọn tất cả (<?= count($cartItems) ?> sản phẩm)</label>
                </div>

                <div id="cart-list">
                    <?php foreach ($cartItems as $item): ?>
                        <?php
                        $tonKho = (int)($item['TonKho'] ?? 99);
                        $stockHint = '';
                        $stockClass = '';
                        if ($tonKho === 0) {
                            $stockHint  = 'Hết hàng';
                            $stockClass = 'out';
                        } elseif ($tonKho <= 5) {
                            $stockHint  = "Chỉ còn $tonKho trong kho";
                            $stockClass = 'low';
                        }
                        ?>
                        <div class="cart-item-row" id="row-<?= $item['MaHang'] ?>">
                            <input type="checkbox" name="selected_items[]"
                                   value="<?= (int)$item['MaHang'] ?>"
                                   class="item-cb" checked>
                            <img src="<?= htmlspecialchars($item['DiaChiAnh']) ?>"
                                 alt="" onerror="this.style.display='none'">
                            <div class="cart-item-info">
                                <h3><?= htmlspecialchars($item['TenSanPham']) ?></h3>
                                <div class="meta">
                                    Màu: <?= htmlspecialchars($item['MauSac']) ?> &nbsp;|&nbsp;
                                    <?= htmlspecialchars($item['KichThuoc']) ?>
                                </div>
                                <div class="qty-control-row">
                                    <div class="qty-box">
                                        <button type="button"
                                                onclick="changeCartQty(<?= $item['MaHang'] ?>, -1)"
                                                <?= $item['Soluong'] <= 1 ? 'disabled' : '' ?>>−</button>
                                        <input type="number"
                                               id="qty-<?= $item['MaHang'] ?>"
                                               value="<?= (int)$item['Soluong'] ?>"
                                               min="1"
                                               max="<?= $tonKho ?>"
                                               onchange="setCartQty(<?= $item['MaHang'] ?>, this.value)">
                                        <button type="button"
                                                onclick="changeCartQty(<?= $item['MaHang'] ?>, 1)"
                                                <?= $item['Soluong'] >= $tonKho ? 'disabled' : '' ?>>+</button>
                                    </div>
                                    <?php if ($stockHint): ?>
                                        <span class="item-stock-hint <?= $stockClass ?>"><?= $stockHint ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="item-subtotal" id="sub-<?= $item['MaHang'] ?>">
                                <?= number_format($item['GiaMoi'] * $item['Soluong'], 0, ',', '.') ?>đ
                            </div>
                            <button type="button" class="btn-del"
                                    onclick="removeItem(<?= $item['MaHang'] ?>)"
                                    title="Xóa">✕</button>

                            <input type="hidden" name="item_price[<?= $item['MaHang'] ?>]"
                                   id="price-<?= $item['MaHang'] ?>"
                                   value="<?= (int)$item['GiaMoi'] ?>">
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary-card">
                    <div class="summary-row total-row">
                        <span>Tổng tiền đã chọn:</span>
                        <span class="total-price" id="grand-total">
                            <?= number_format($tongTien, 0, ',', '.') ?>đ
                        </span>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top:14px;">
                        Thanh toán →
                    </button>
                    <a href="index.php" class="btn btn-secondary">← Tiếp tục mua sắm</a>
                </div>
            </form>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <div class="container">© <?= date('Y') ?> Mobile Web - Đồ án cuối kỳ</div>
    </footer>

    <script>
    // Select all checkbox
    const selectAll = document.getElementById('select-all');
    function recalcTotal() {
        let total = 0;
        document.querySelectorAll('.item-cb').forEach(cb => {
            if (cb.checked) {
                const id = cb.value;
                const qty = parseInt(document.getElementById('qty-' + id).value) || 0;
                const price = parseInt(document.getElementById('price-' + id).value) || 0;
                total += qty * price;
            }
        });
        document.getElementById('grand-total').textContent =
            total.toLocaleString('vi-VN') + 'đ';
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.item-cb').forEach(cb => cb.checked = this.checked);
            recalcTotal();
        });
        document.querySelectorAll('.item-cb').forEach(cb => {
            cb.addEventListener('change', function() {
                const allChecked = [...document.querySelectorAll('.item-cb')].every(c => c.checked);
                selectAll.checked = allChecked;
                recalcTotal();
            });
        });
    }

    function updateSubtotal(maHang) {
        const qty   = parseInt(document.getElementById('qty-' + maHang).value) || 0;
        const price = parseInt(document.getElementById('price-' + maHang).value) || 0;
        document.getElementById('sub-' + maHang).textContent =
            (qty * price).toLocaleString('vi-VN') + 'đ';
        recalcTotal();
    }

    function changeCartQty(maHang, delta) {
        const input = document.getElementById('qty-' + maHang);
        let val = parseInt(input.value) + delta;
        const max = parseInt(input.max) || 99;
        if (val < 1) val = 1;
        if (val > max) val = max;
        input.value = val;
        setCartQty(maHang, val);
    }

    function setCartQty(maHang, qty) {
        qty = parseInt(qty);
        if (isNaN(qty) || qty < 1) qty = 1;
        fetch('cart.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=set_qty&ma_hang=${maHang}&qty=${qty}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                document.getElementById('qty-' + maHang).value = data.qty;
                updateSubtotal(maHang);
            } else {
                alert(data.msg || 'Có lỗi xảy ra.');
            }
        });
    }

    function removeItem(maHang) {
        if (!confirm('Xóa sản phẩm này khỏi giỏ?')) return;
        fetch('cart.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=remove&ma_hang=${maHang}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                document.getElementById('row-' + maHang).remove();
                recalcTotal();
                // Kiểm tra giỏ rỗng
                if (document.querySelectorAll('.cart-item-row').length === 0) {
                    location.reload();
                }
            }
        });
    }

    // Tính ngay khi load
    recalcTotal();
    </script>
</body>
</html>
