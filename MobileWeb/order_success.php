<?php
/**
 * TRANG THÔNG BÁO ĐẶT HÀNG THÀNH CÔNG
 * Hiển thị mã đơn hàng + thông tin tóm tắt
 */

session_start();
require_once 'connect.php';

if (!isset($_SESSION['user'])) {
    header("Location: pages/login.php");
    exit();
}

$maHoaDon = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($maHoaDon <= 0) {
    header("Location: index.php");
    exit();
}

$tenDangNhap = $_SESSION['user'];

// Lấy thông tin đơn hàng - CHỈ user chủ đơn mới xem được
$sql = "SELECT * FROM hoadon WHERE MaHoaDon = ? AND TenDangNhap = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "is", $maHoaDon, $tenDangNhap);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$hoaDon = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$hoaDon) {
    die("Không tìm thấy đơn hàng hoặc bạn không có quyền xem.");
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt hàng thành công - Mobile Web</title>
    <link rel="stylesheet" href="assets/css/checkout.css">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a href="index.php" class="logo">Mobile Web</a>
            <nav>
                <a href="index.php">Trang chủ</a>
                <a href="cart.php">Giỏ hàng</a>
                <a href="my_orders.php">Đơn hàng của tôi</a>
            </nav>
        </div>
    </header>

    <main class="container success-page">
        <div class="success-card">
            <div class="success-icon">✓</div>
            <h1>Đặt hàng thành công!</h1>
            <p class="success-sub">Cảm ơn bạn đã mua hàng tại Mobile Web. Chúng tôi sẽ liên hệ sớm.</p>

            <div class="order-info-box">
                <div class="info-row">
                    <span class="info-label">Mã đơn hàng:</span>
                    <span class="info-value highlight">#<?= htmlspecialchars($hoaDon['MaHoaDon']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Ngày đặt:</span>
                    <span class="info-value"><?= htmlspecialchars($hoaDon['NgayLap']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Người nhận:</span>
                    <span class="info-value"><?= htmlspecialchars($hoaDon['HoTenNhan']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Số điện thoại:</span>
                    <span class="info-value"><?= htmlspecialchars($hoaDon['SoDienThoaiNhan']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Địa chỉ giao hàng:</span>
                    <span class="info-value"><?= htmlspecialchars($hoaDon['DiaChiNhan']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phương thức thanh toán:</span>
                    <span class="info-value">
                        <?= $hoaDon['PhuongThucThanhToan'] === 'COD' ? 'Thanh toán khi nhận hàng (COD)' : 'Chuyển khoản ngân hàng' ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tổng tiền:</span>
                    <span class="info-value highlight"><?= number_format($hoaDon['TongTien'], 0, ',', '.') ?>đ</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Trạng thái:</span>
                    <span class="info-value">
                        <span class="badge badge-pending"><?= htmlspecialchars($hoaDon['TrangThai']) ?></span>
                    </span>
                </div>
            </div>

            <?php if ($hoaDon['PhuongThucThanhToan'] === 'BANK'): ?>
                <div class="bank-info">
                    <h3>Thông tin chuyển khoản</h3>
                    <p>Ngân hàng: <b>Vietcombank</b></p>
                    <p>Số tài khoản: <b>0123456789</b></p>
                    <p>Chủ tài khoản: <b>Mobile Web</b></p>
                    <p>Nội dung: <b>#<?= $hoaDon['MaHoaDon'] ?> <?= htmlspecialchars($hoaDon['HoTenNhan']) ?></b></p>
                </div>
            <?php endif; ?>

            <div class="action-buttons">
                <a href="my_orders.php" class="btn btn-primary">Xem đơn hàng của tôi</a>
                <a href="index.php" class="btn btn-secondary">Tiếp tục mua sắm</a>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">© <?= date('Y') ?> Mobile Web - Đồ án cuối kỳ</div>
    </footer>
</body>
</html>
