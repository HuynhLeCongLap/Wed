<?php
/**
 * DANH SÁCH ĐƠN HÀNG CỦA TÔI
 * - Chỉ hiển thị đơn của user đang đăng nhập
 * - Có thể filter theo trạng thái
 */

session_start();
require_once 'connect.php';

if (!isset($_SESSION['user'])) {
    header("Location: pages/login.php");
    exit();
}
$tenDangNhap = $_SESSION['user'];

// Filter theo trạng thái (nếu có)
$filterStatus = $_GET['status'] ?? 'all';
$validStatuses = ['all', 'Chưa xác nhận', 'Đã xác nhận', 'Đang giao', 'Hoàn thành', 'Đã hủy'];
if (!in_array($filterStatus, $validStatuses)) {
    $filterStatus = 'all';
}

if ($filterStatus === 'all') {
    $sql = "SELECT * FROM hoadon WHERE TenDangNhap = ? ORDER BY MaHoaDon DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $tenDangNhap);
} else {
    $sql = "SELECT * FROM hoadon WHERE TenDangNhap = ? AND TrangThai = ? ORDER BY MaHoaDon DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $tenDangNhap, $filterStatus);
}
mysqli_stmt_execute($stmt);
$orders = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

/** Hàm trả về class CSS theo trạng thái */
function statusClass($status) {
    switch ($status) {
        case 'Chưa xác nhận': return 'badge-pending';
        case 'Đã xác nhận':   return 'badge-confirmed';
        case 'Đang giao':     return 'badge-shipping';
        case 'Hoàn thành':    return 'badge-done';
        case 'Đã hủy':        return 'badge-cancelled';
        default: return 'badge-pending';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng của tôi - Mobile Web</title>
    <link rel="stylesheet" href="assets/css/checkout.css">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a href="index.php" class="logo">Mobile Web</a>
            <nav>
                <a href="index.php">Trang chủ</a>
                <a href="cart.php">Giỏ hàng</a>
                <a href="my_orders.php" class="active">Đơn hàng của tôi</a>
            </nav>
        </div>
    </header>

    <main class="container my-orders-page">
        <h1 class="page-title">Đơn hàng của tôi</h1>

        <!-- Filter -->
        <div class="status-filter">
            <?php
            $tabs = [
                'all' => 'Tất cả',
                'Chưa xác nhận' => 'Chưa xác nhận',
                'Đã xác nhận' => 'Đã xác nhận',
                'Đang giao' => 'Đang giao',
                'Hoàn thành' => 'Hoàn thành',
                'Đã hủy' => 'Đã hủy'
            ];
            foreach ($tabs as $key => $label):
                $isActive = $filterStatus === $key ? 'active' : '';
            ?>
                <a href="?status=<?= urlencode($key) ?>" class="filter-tab <?= $isActive ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <p>Bạn chưa có đơn hàng nào.</p>
                <a href="index.php" class="btn btn-primary">Mua sắm ngay</a>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-card-header">
                            <div>
                                <span class="order-id">Mã đơn: #<?= htmlspecialchars($order['MaHoaDon']) ?></span>
                                <span class="order-date">Ngày đặt: <?= htmlspecialchars($order['NgayLap']) ?></span>
                            </div>
                            <span class="badge <?= statusClass($order['TrangThai']) ?>">
                                <?= htmlspecialchars($order['TrangThai']) ?>
                            </span>
                        </div>

                        <div class="order-card-body">
                            <?php
                            // Lấy preview vài sản phẩm trong đơn
                            $sqlItems = "SELECT ct.*, sp.TenSanPham,
                                         (SELECT DiaChiAnh FROM image WHERE MaSanPham = ct.MaSanPham LIMIT 1) AS Anh
                                         FROM chitiethoadon ct
                                         JOIN sanpham sp ON ct.MaSanPham = sp.MaSanPham
                                         WHERE ct.MaHoaDon = ?";
                            $stmtItems = mysqli_prepare($conn, $sqlItems);
                            mysqli_stmt_bind_param($stmtItems, "i", $order['MaHoaDon']);
                            mysqli_stmt_execute($stmtItems);
                            $items = mysqli_fetch_all(mysqli_stmt_get_result($stmtItems), MYSQLI_ASSOC);
                            mysqli_stmt_close($stmtItems);
                            ?>

                            <?php foreach (array_slice($items, 0, 2) as $item): ?>
                                <div class="order-item-preview">
                                    <img src="<?= htmlspecialchars($item['Anh'] ?? '') ?>"
                                         alt="<?= htmlspecialchars($item['TenSanPham']) ?>"
                                         onerror="this.style.display='none'">
                                    <div>
                                        <div class="order-item-name"><?= htmlspecialchars($item['TenSanPham']) ?></div>
                                        <div class="order-item-meta">
                                            <?= htmlspecialchars($item['TenMau']) ?> |
                                            <?= htmlspecialchars($item['KichThuoc']) ?> |
                                            SL: <?= (int)$item['SoLuong'] ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (count($items) > 2): ?>
                                <div class="more-items">và <?= count($items) - 2 ?> sản phẩm khác...</div>
                            <?php endif; ?>
                        </div>

                        <div class="order-card-footer">
                            <span class="order-total">
                                Tổng: <b><?= number_format($order['TongTien'], 0, ',', '.') ?>đ</b>
                            </span>
                            <a href="order_detail.php?id=<?= $order['MaHoaDon'] ?>" class="btn btn-outline">
                                Xem chi tiết
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <div class="container">© <?= date('Y') ?> Mobile Web - Đồ án cuối kỳ</div>
    </footer>
</body>
</html>
