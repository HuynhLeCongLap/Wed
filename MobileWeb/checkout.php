<?php
/**
 * TRANG THANH TOÁN
 * - Hiển thị giỏ hàng (các sản phẩm đã chọn từ trang giỏ)
 * - Form nhập thông tin người nhận
 * - Chọn phương thức thanh toán
 * - Nút "Đặt hàng" và "Tiếp tục mua sắm"
 */

session_start();
require_once 'connect.php';

// ============= 1. KIỂM TRA ĐĂNG NHẬP =============
if (!isset($_SESSION['user'])) {
    // Lưu trang hiện tại để sau khi đăng nhập xong quay lại
    $_SESSION['redirect_after_login'] = 'checkout.php';
    header("Location: pages/login.php");
    exit();
}

$tenDangNhap = $_SESSION['user'];

// ============= 2. LẤY THÔNG TIN KHÁCH HÀNG ĐỂ ĐIỀN SẴN FORM =============
$stmt = mysqli_prepare($conn, "SELECT HoTen, SoDienThoai, DiaChi, Email FROM khachhang WHERE TenDangNhap = ?");
mysqli_stmt_bind_param($stmt, "s", $tenDangNhap);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$khachHang = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$khachHang) {
    die("Không tìm thấy thông tin khách hàng. Vui lòng đăng nhập lại.");
}

// ============= 3. LẤY DANH SÁCH SẢN PHẨM TRONG GIỎ HÀNG =============
// Hỗ trợ 2 chế độ:
//   a) Có POST 'selected_items[]' -> chỉ lấy các sản phẩm được chọn (từ trang giỏ hàng)
//   b) Không có -> lấy toàn bộ giỏ hàng
$selectedItems = isset($_POST['selected_items']) ? $_POST['selected_items'] : [];

if (!empty($selectedItems) && is_array($selectedItems)) {
    // Lọc chỉ giữ số nguyên (chống SQL injection)
    $selectedItems = array_filter(array_map('intval', $selectedItems), function($v) { return $v > 0; });
}

if (!empty($selectedItems)) {
    $placeholders = implode(',', array_fill(0, count($selectedItems), '?'));
    $types = str_repeat('i', count($selectedItems)) . 's';
    $params = array_merge($selectedItems, [$tenDangNhap]);

    $sql = "SELECT * FROM giohang WHERE MaHang IN ($placeholders) AND TenDangNhap = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
} else {
    // Lấy hết giỏ hàng của user
    $sql = "SELECT * FROM giohang WHERE TenDangNhap = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $tenDangNhap);
}

mysqli_stmt_execute($stmt);
$cartResult = mysqli_stmt_get_result($stmt);
$cartItems = [];
$tongTien = 0;
while ($row = mysqli_fetch_assoc($cartResult)) {
    $cartItems[] = $row;
    $tongTien += $row['GiaMoi'] * $row['Soluong'];
}
mysqli_stmt_close($stmt);

// ============= 4. KIỂM TRA GIỎ HÀNG KHÔNG RỖNG =============
if (empty($cartItems)) {
    // Giỏ trống -> chuyển về trang giỏ hàng
    header("Location: cart.php?error=empty_cart");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán - Mobile Web</title>
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

    <main class="container checkout-page">
        <h1 class="page-title">Thanh toán</h1>

        <!-- Khu vực hiển thị thông báo lỗi từ AJAX -->
        <div id="alert-box" class="alert-box hidden"></div>

        <form id="checkout-form" class="checkout-grid">
            <!-- =================== CỘT TRÁI: THÔNG TIN GIAO HÀNG =================== -->
            <section class="checkout-left">
                <div class="card">
                    <h2 class="card-title">Thông tin người nhận</h2>

                    <div class="form-group">
                        <label for="hoTen">Họ tên người nhận <span class="required">*</span></label>
                        <input type="text" id="hoTen" name="hoTen"
                               value="<?= htmlspecialchars($khachHang['HoTen'] ?? '') ?>"
                               placeholder="Nhập họ và tên" required>
                        <small class="error-msg" id="err-hoTen"></small>
                    </div>

                    <div class="form-group">
                        <label for="soDienThoai">Số điện thoại <span class="required">*</span></label>
                        <input type="tel" id="soDienThoai" name="soDienThoai"
                               value="<?= htmlspecialchars($khachHang['SoDienThoai'] ?? '') ?>"
                               placeholder="VD: 0901234567" required>
                        <small class="error-msg" id="err-soDienThoai"></small>
                    </div>

                    <div class="form-group">
                        <label for="diaChi">Địa chỉ nhận hàng <span class="required">*</span></label>
                        <textarea id="diaChi" name="diaChi" rows="2"
                                  placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành" required><?= htmlspecialchars($khachHang['DiaChi'] ?? '') ?></textarea>
                        <small class="error-msg" id="err-diaChi"></small>
                    </div>

                    <div class="form-group">
                        <label for="ghiChu">Ghi chú (không bắt buộc)</label>
                        <textarea id="ghiChu" name="ghiChu" rows="2"
                                  placeholder="VD: Giao giờ hành chính, gọi trước khi giao..."></textarea>
                    </div>
                </div>

                <div class="card">
                    <h2 class="card-title">Phương thức thanh toán</h2>

                    <label class="payment-option">
                        <input type="radio" name="phuongThuc" value="COD" checked>
                        <div class="payment-info">
                            <strong>Thanh toán khi nhận hàng (COD)</strong>
                            <p>Bạn sẽ thanh toán bằng tiền mặt khi nhận được hàng.</p>
                        </div>
                    </label>

                    <label class="payment-option">
                        <input type="radio" name="phuongThuc" value="BANK">
                        <div class="payment-info">
                            <strong>Chuyển khoản ngân hàng</strong>
                            <p>Chuyển khoản đến: <b>Mobile Web</b> - STK <b>0123456789</b> - Vietcombank.<br>
                            Nội dung: <b>Họ tên + SĐT</b>.</p>
                        </div>
                    </label>
                </div>
            </section>

            <!-- =================== CỘT PHẢI: ĐƠN HÀNG =================== -->
            <aside class="checkout-right">
                <div class="card order-summary">
                    <h2 class="card-title">Đơn hàng (<?= count($cartItems) ?> sản phẩm)</h2>

                    <div class="cart-items">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item">
                                <img src="<?= htmlspecialchars($item['DiaChiAnh']) ?>"
                                     alt="<?= htmlspecialchars($item['TenSanPham']) ?>"
                                     onerror="this.src='assets/images/no-image.png'">
                                <div class="cart-item-info">
                                    <div class="cart-item-name"><?= htmlspecialchars($item['TenSanPham']) ?></div>
                                    <div class="cart-item-variant">
                                        Màu: <?= htmlspecialchars($item['MauSac']) ?> |
                                        <?= htmlspecialchars($item['KichThuoc']) ?>
                                    </div>
                                    <div class="cart-item-price-row">
                                        <span class="cart-item-qty">SL: <?= (int)$item['Soluong'] ?></span>
                                        <span class="cart-item-price">
                                            <?= number_format($item['GiaMoi'] * $item['Soluong'], 0, ',', '.') ?>đ
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <!-- Gửi kèm MaHang để biết item nào sẽ được đặt -->
                            <input type="hidden" name="ma_hang[]" value="<?= (int)$item['MaHang'] ?>">
                        <?php endforeach; ?>
                    </div>

                    <div class="summary-row">
                        <span>Tạm tính:</span>
                        <span><?= number_format($tongTien, 0, ',', '.') ?>đ</span>
                    </div>
                    <div class="summary-row">
                        <span>Phí vận chuyển:</span>
                        <span>Miễn phí</span>
                    </div>
                    <div class="summary-row total-row">
                        <span>Tổng tiền:</span>
                        <span class="total-price"><?= number_format($tongTien, 0, ',', '.') ?>đ</span>
                    </div>

                    <button type="submit" class="btn btn-primary" id="btn-place-order">
                        Đặt hàng
                    </button>
                    <a href="index.php" class="btn btn-secondary">Tiếp tục mua sắm</a>
                </div>
            </aside>
        </form>
    </main>

    <footer class="site-footer">
        <div class="container">© <?= date('Y') ?> Mobile Web - Đồ án cuối kỳ</div>
    </footer>

    <script src="assets/js/checkout.js"></script>
</body>
</html>
