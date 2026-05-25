<?php
/**
 * CHI TIẾT 1 ĐƠN HÀNG
 * - Bảo mật: chỉ user chủ đơn mới xem được
 * - Có nút Hủy đơn nếu trạng thái là "Chưa xác nhận"
 */

session_start();
require_once 'connect.php';

if (!isset($_SESSION['user'])) {
    header("Location: pages/login.php");
    exit();
}
$tenDangNhap = $_SESSION['user'];

$maHoaDon = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($maHoaDon <= 0) {
    header("Location: my_orders.php");
    exit();
}

// BẢO MẬT: chỉ lấy đơn của user đang đăng nhập
$sql = "SELECT * FROM hoadon WHERE MaHoaDon = ? AND TenDangNhap = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "is", $maHoaDon, $tenDangNhap);
mysqli_stmt_execute($stmt);
$hoaDon = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$hoaDon) {
    die("Không tìm thấy đơn hàng hoặc bạn không có quyền xem.");
}

// Lấy chi tiết sản phẩm
$sqlCT = "SELECT ct.*, sp.TenSanPham,
          (SELECT DiaChiAnh FROM image WHERE MaSanPham = ct.MaSanPham LIMIT 1) AS Anh
          FROM chitiethoadon ct
          JOIN sanpham sp ON ct.MaSanPham = sp.MaSanPham
          WHERE ct.MaHoaDon = ?";
$stmt = mysqli_prepare($conn, $sqlCT);
mysqli_stmt_bind_param($stmt, "i", $maHoaDon);
mysqli_stmt_execute($stmt);
$chiTiet = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

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

$canCancel = $hoaDon['TrangThai'] === 'Chưa xác nhận';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết đơn hàng #<?= $hoaDon['MaHoaDon'] ?> - Mobile Web</title>
    <link rel="stylesheet" href="assets/css/checkout.css">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a href="index.php" class="logo">Mobile Web</a>
            <nav>
                <a href="index.php">Trang chủ</a>
                <a href="cart.php">Giỏ hàng</a>
                <a href="pages/profile.php" class="active">Tài khoản</a>
            </nav>
        </div>
    </header>

    <main class="container order-detail-page">
        <div class="breadcrumb">
            <a href="my_orders.php">← Quay lại danh sách đơn hàng</a>
        </div>

        <div id="alert-box" class="alert-box hidden"></div>

        <div class="order-detail-header">
            <div>
                <h1>Đơn hàng #<?= htmlspecialchars($hoaDon['MaHoaDon']) ?></h1>
                <p>Đặt ngày: <?= htmlspecialchars($hoaDon['NgayLap']) ?></p>
            </div>
            <span class="badge <?= statusClass($hoaDon['TrangThai']) ?>">
                <?= htmlspecialchars($hoaDon['TrangThai']) ?>
            </span>
        </div>

        <div class="detail-grid">
            <!-- Cột trái: thông tin -->
            <section class="card">
                <h2 class="card-title">Thông tin giao hàng</h2>
                <div class="info-row">
                    <span class="info-label">Người nhận:</span>
                    <span><?= htmlspecialchars($hoaDon['HoTenNhan']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Số điện thoại:</span>
                    <span><?= htmlspecialchars($hoaDon['SoDienThoaiNhan']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Địa chỉ:</span>
                    <span><?= htmlspecialchars($hoaDon['DiaChiNhan']) ?></span>
                </div>
                <?php if (!empty($hoaDon['GhiChu'])): ?>
                <div class="info-row">
                    <span class="info-label">Ghi chú:</span>
                    <span><?= htmlspecialchars($hoaDon['GhiChu']) ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Thanh toán:</span>
                    <span>
                        <?= $hoaDon['PhuongThucThanhToan'] === 'COD' ? 'Tiền mặt khi nhận hàng (COD)' : 'Chuyển khoản ngân hàng' ?>
                    </span>
                </div>
            </section>

            <!-- Cột phải: sản phẩm -->
            <section class="card">
                <h2 class="card-title">Sản phẩm (<?= count($chiTiet) ?>)</h2>
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th>Số lượng</th>
                            <th>Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($chiTiet as $item): ?>
                            <tr>
                                <td>
                                    <div class="detail-product">
                                        <img src="<?= htmlspecialchars($item['Anh'] ?? '') ?>"
                                             alt="" onerror="this.style.display='none'">
                                        <div>
                                            <div><b><?= htmlspecialchars($item['TenSanPham']) ?></b></div>
                                            <div class="detail-meta">
                                                <?= htmlspecialchars($item['TenMau']) ?> |
                                                <?= htmlspecialchars($item['KichThuoc']) ?>
                                            </div>
                                            <?php
                                            // Tính đơn giá để hiển thị
                                            $donGia = $item['SoLuong'] > 0 ? $item['ThanhTien'] / $item['SoLuong'] : 0;
                                            ?>
                                            <div class="detail-price">
                                                <?= number_format($donGia, 0, ',', '.') ?>đ
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center"><?= (int)$item['SoLuong'] ?></td>
                                <td class="text-right">
                                    <b><?= number_format($item['ThanhTien'], 0, ',', '.') ?>đ</b>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="text-right"><b>Tổng tiền:</b></td>
                            <td class="text-right">
                                <b class="total-price"><?= number_format($hoaDon['TongTien'], 0, ',', '.') ?>đ</b>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </section>
        </div>

        <?php if ($canCancel): ?>
            <div class="action-bar">
                <button id="btn-cancel-order"
                        class="btn btn-danger"
                        data-id="<?= $hoaDon['MaHoaDon'] ?>">
                    Hủy đơn hàng
                </button>
                <p class="hint">Bạn chỉ có thể hủy đơn khi đơn chưa được xác nhận.</p>
            </div>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <div class="container">© <?= date('Y') ?> Mobile Web - Đồ án cuối kỳ</div>
    </footer>

    <script src="assets/js/checkout.js"></script>
</body>
</html>
