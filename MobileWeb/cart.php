<?php
/**
 * TRANG GIỎ HÀNG (CART)
 * - Hiển thị các sản phẩm trong bảng giohang
 * - Cho phép xóa từng sản phẩm
 * - Bấm "Thanh toán" để chuyển qua checkout.php
 *
 * Đây là trang phụ trợ tạm để bạn test checkout.
 * Sau này nhóm có ai làm cart đẹp hơn thì thay thế file này.
 */

session_start();
require_once 'connect.php';

// Bắt buộc đăng nhập
if (!isset($_SESSION['user'])) {
    header('Location: pages/login.php');
    exit;
}
$tenDangNhap = $_SESSION['user'];

// Xử lý xóa sản phẩm khỏi giỏ
if (isset($_GET['remove'])) {
    $maHang = (int)$_GET['remove'];
    $stmt = mysqli_prepare($conn, "DELETE FROM giohang WHERE MaHang = ? AND TenDangNhap = ?");
    mysqli_stmt_bind_param($stmt, 'is', $maHang, $tenDangNhap);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: cart.php?removed=1');
    exit;
}

// Lấy danh sách giỏ hàng
$stmt = mysqli_prepare($conn, "SELECT * FROM giohang WHERE TenDangNhap = ? ORDER BY MaHang DESC");
mysqli_stmt_bind_param($stmt, 's', $tenDangNhap);
mysqli_stmt_execute($stmt);
$cartItems = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Tính tổng
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
        /* CSS bổ sung riêng cho cart */
        .cart-page-item {
            display: flex;
            gap: 14px;
            background: white;
            padding: 14px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
            margin-bottom: 10px;
            align-items: center;
        }
        .cart-page-item img {
            width: 80px; height: 80px; object-fit: cover;
            border-radius: 8px; background: #f0f0f0;
        }
        .cart-page-info { flex: 1; min-width: 0; }
        .cart-page-info h3 {
            font-size: 15px; color: #2c3e50; margin-bottom: 4px;
        }
        .cart-page-info .meta {
            color: #888; font-size: 13px; margin-bottom: 6px;
        }
        .cart-page-info .qty-price {
            display: flex; justify-content: space-between; align-items: center;
        }
        .cart-page-info .qty-price .qty { color: #555; font-size: 14px; }
        .cart-page-info .qty-price .price {
            color: #e74c3c; font-weight: 700; font-size: 16px;
        }
        .btn-remove {
            background: transparent; border: none; color: #888;
            font-size: 20px; cursor: pointer; padding: 8px;
        }
        .btn-remove:hover { color: #e74c3c; }
        .cart-summary {
            background: white; padding: 20px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-top: 16px;
        }
        .cart-checkbox-row {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 12px; padding: 8px;
        }
        .cart-checkbox-row label {
            font-weight: 600; color: #2c3e50;
        }
        .select-all { transform: scale(1.2); accent-color: #3498db; }
        .item-checkbox { transform: scale(1.2); accent-color: #3498db; }
        .cart-checkout-form { margin: 0; }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a href="index.php" class="logo">Mobile Web</a>
            <nav>
                <a href="index.php">Trang chủ</a>
                <a href="cart.php" class="active">Giỏ hàng</a>
                <a href="pages/profile.php">Tài khoản</a>
            </nav>
        </div>
    </header>

    <main class="container" style="padding: 20px;">
        <h1 class="page-title">Giỏ hàng của bạn</h1>

        <?php if (isset($_GET['removed'])): ?>
            <div class="alert-box alert-success">✓ Đã xóa sản phẩm khỏi giỏ hàng.</div>
        <?php endif; ?>

        <?php if (empty($cartItems)): ?>
            <div class="empty-state">
                <p style="font-size:18px; margin-bottom:10px;">🛒 Giỏ hàng của bạn đang trống</p>
                <p>Hãy chọn sản phẩm để thêm vào giỏ nhé!</p>
                <a href="index.php" class="btn btn-primary" style="margin-top:16px;">Quay lại trang chủ</a>
            </div>
        <?php else: ?>
            <form action="checkout.php" method="post" class="cart-checkout-form">
                <div class="cart-checkbox-row">
                    <input type="checkbox" id="select-all" class="select-all" checked>
                    <label for="select-all">Chọn tất cả (<?= count($cartItems) ?> sản phẩm)</label>
                </div>

                <?php foreach ($cartItems as $item): ?>
                    <div class="cart-page-item">
                        <input type="checkbox" name="selected_items[]" value="<?= (int)$item['MaHang'] ?>"
                               class="item-checkbox" checked>
                        <img src="<?= htmlspecialchars($item['DiaChiAnh']) ?>"
                             alt="" onerror="this.style.display='none'">
                        <div class="cart-page-info">
                            <h3><?= htmlspecialchars($item['TenSanPham']) ?></h3>
                            <div class="meta">
                                Màu: <?= htmlspecialchars($item['MauSac']) ?> &nbsp;|&nbsp;
                                <?= htmlspecialchars($item['KichThuoc']) ?>
                            </div>
                            <div class="qty-price">
                                <span class="qty">Số lượng: <?= (int)$item['Soluong'] ?></span>
                                <span class="price">
                                    <?= number_format($item['GiaMoi'] * $item['Soluong'], 0, ',', '.') ?>đ
                                </span>
                            </div>
                        </div>
                        <a href="cart.php?remove=<?= (int)$item['MaHang'] ?>"
                           class="btn-remove"
                           onclick="return confirm('Xóa sản phẩm này khỏi giỏ?')"
                           title="Xóa">✕</a>
                    </div>
                <?php endforeach; ?>

                <div class="cart-summary">
                    <div class="summary-row total-row">
                        <span>Tổng tiền:</span>
                        <span class="total-price"><?= number_format($tongTien, 0, ',', '.') ?>đ</span>
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
        // Select all checkbox logic
        const selectAll = document.getElementById('select-all');
        const itemCheckboxes = document.querySelectorAll('.item-checkbox');

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                itemCheckboxes.forEach(cb => cb.checked = this.checked);
            });

            itemCheckboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    const allChecked = Array.from(itemCheckboxes).every(c => c.checked);
                    selectAll.checked = allChecked;
                });
            });
        }
    </script>
</body>
</html>
