<?php
/**
 * XỬ LÝ ĐẶT HÀNG (AJAX endpoint)
 * - Nhận POST từ checkout.php qua AJAX
 * - Validate dữ liệu (server-side)
 * - Kiểm tra tồn kho
 * - Lưu hoa don, chi tiet hoa don
 * - Trừ kho, xóa các sản phẩm đã đặt khỏi giỏ hàng
 * - Trả về JSON {success, MaHoaDon, message}
 */

session_start();
require_once 'connect.php';

// Báo response là JSON
header('Content-Type: application/json; charset=utf-8');

/** Hàm trả về JSON và kết thúc */
function jsonResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// ============= 1. KIỂM TRA ĐĂNG NHẬP =============
if (!isset($_SESSION['user'])) {
    jsonResponse(['success' => false, 'message' => 'Bạn cần đăng nhập trước khi đặt hàng.']);
}
$tenDangNhap = $_SESSION['user'];

// ============= 2. KIỂM TRA PHƯƠNG THỨC =============
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
}

// ============= 3. LẤY VÀ LÀM SẠCH DỮ LIỆU =============
$hoTen        = trim($_POST['hoTen']        ?? '');
$soDienThoai  = trim($_POST['soDienThoai']  ?? '');
$diaChi       = trim($_POST['diaChi']       ?? '');
$ghiChu       = trim($_POST['ghiChu']       ?? '');
$phuongThuc   = trim($_POST['phuongThuc']   ?? 'COD');
$maHangArr    = $_POST['ma_hang'] ?? [];

// ============= 4. VALIDATE =============
$errors = [];

if ($hoTen === '') {
    $errors['hoTen'] = 'Vui lòng nhập họ tên người nhận.';
} elseif (mb_strlen($hoTen) < 2) {
    $errors['hoTen'] = 'Họ tên quá ngắn.';
}

if ($soDienThoai === '') {
    $errors['soDienThoai'] = 'Vui lòng nhập số điện thoại.';
} elseif (!preg_match('/^(0|\+84)[0-9]{9,10}$/', $soDienThoai)) {
    // SĐT Việt Nam: bắt đầu bằng 0 hoặc +84, tổng 10-11 số
    $errors['soDienThoai'] = 'Số điện thoại không đúng định dạng (VD: 0901234567).';
}

if ($diaChi === '') {
    $errors['diaChi'] = 'Vui lòng nhập địa chỉ nhận hàng.';
} elseif (mb_strlen($diaChi) < 10) {
    $errors['diaChi'] = 'Địa chỉ quá ngắn, vui lòng nhập đầy đủ.';
}

if (!in_array($phuongThuc, ['COD', 'BANK'])) {
    $errors['phuongThuc'] = 'Phương thức thanh toán không hợp lệ.';
}

if (!is_array($maHangArr) || empty($maHangArr)) {
    $errors['cart'] = 'Giỏ hàng trống, không thể đặt hàng.';
}

if (!empty($errors)) {
    jsonResponse(['success' => false, 'message' => 'Vui lòng kiểm tra lại thông tin.', 'errors' => $errors]);
}

// Lọc chỉ giữ số nguyên cho MaHang
$maHangArr = array_filter(array_map('intval', $maHangArr), function($v) { return $v > 0; });
if (empty($maHangArr)) {
    jsonResponse(['success' => false, 'message' => 'Giỏ hàng không hợp lệ.']);
}

// ============= 5. LẤY THÔNG TIN GIỎ HÀNG ĐỂ XỬ LÝ =============
$placeholders = implode(',', array_fill(0, count($maHangArr), '?'));
$types = str_repeat('i', count($maHangArr)) . 's';
$params = array_merge($maHangArr, [$tenDangNhap]);

$sql = "SELECT * FROM giohang WHERE MaHang IN ($placeholders) AND TenDangNhap = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$cartItems = [];
$tongTien = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $cartItems[] = $row;
    $tongTien += $row['GiaMoi'] * $row['Soluong'];
}
mysqli_stmt_close($stmt);

if (empty($cartItems)) {
    jsonResponse(['success' => false, 'message' => 'Không tìm thấy sản phẩm trong giỏ hàng.']);
}

// ============= 6. KIỂM TRA TỒN KHO =============
// Stock được lưu ở bảng giasanpham, key (MaRam, MaSanPham, MaMau)
// Cart chỉ có MaSanPham, MauSac (string), KichThuoc (string) -> phải JOIN qua ram_rom_option và colors
foreach ($cartItems as $item) {
    $sqlStock = "
        SELECT gs.SoLuong
        FROM giasanpham gs
        JOIN ram_rom_option rro ON gs.MaRam = rro.MaRam
        JOIN colors c ON gs.MaMau = c.MaMau
        WHERE gs.MaSanPham = ?
          AND rro.KichThuoc = ?
          AND c.TenMau = ?
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sqlStock);
    mysqli_stmt_bind_param($stmt, "iss", $item['MaSanPham'], $item['KichThuoc'], $item['MauSac']);
    mysqli_stmt_execute($stmt);
    $stockRes = mysqli_stmt_get_result($stmt);
    $stockRow = mysqli_fetch_assoc($stockRes);
    mysqli_stmt_close($stmt);

    if (!$stockRow) {
        jsonResponse([
            'success' => false,
            'message' => "Sản phẩm \"{$item['TenSanPham']}\" không còn tồn tại trong kho."
        ]);
    }

    if ((int)$stockRow['SoLuong'] < (int)$item['Soluong']) {
        jsonResponse([
            'success' => false,
            'message' => "Sản phẩm \"{$item['TenSanPham']}\" ({$item['MauSac']}, {$item['KichThuoc']}) chỉ còn {$stockRow['SoLuong']} sản phẩm."
        ]);
    }
}

// ============= 7. LƯU ĐƠN HÀNG (DÙNG TRANSACTION) =============
mysqli_begin_transaction($conn);

try {
    // 7a. INSERT vào bảng hoadon
    $trangThai = 'Chưa xác nhận';
    $ngayLap = date('Y-m-d');

    $sqlHD = "INSERT INTO hoadon
              (TenDangNhap, HoTenNhan, SoDienThoaiNhan, DiaChiNhan, GhiChu, PhuongThucThanhToan, NgayLap, TongTien, TrangThai)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sqlHD);
    mysqli_stmt_bind_param($stmt, "sssssssds",
        $tenDangNhap, $hoTen, $soDienThoai, $diaChi, $ghiChu, $phuongThuc, $ngayLap, $tongTien, $trangThai
    );
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Lỗi khi tạo đơn hàng: " . mysqli_error($conn));
    }
    $maHoaDon = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // 7b. INSERT vào bảng chitiethoadon cho từng sản phẩm
    $sqlCT = "INSERT INTO chitiethoadon (MaHoaDon, MaSanPham, TenMau, KichThuoc, SoLuong, ThanhTien)
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sqlCT);

    foreach ($cartItems as $item) {
        $thanhTien = $item['GiaMoi'] * $item['Soluong'];
        mysqli_stmt_bind_param($stmt, "iissid",
            $maHoaDon, $item['MaSanPham'], $item['MauSac'], $item['KichThuoc'], $item['Soluong'], $thanhTien
        );
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Lỗi khi lưu chi tiết đơn hàng: " . mysqli_error($conn));
        }
    }
    mysqli_stmt_close($stmt);

    // 7c. Trừ tồn kho
    $sqlUpdateStock = "
        UPDATE giasanpham gs
        JOIN ram_rom_option rro ON gs.MaRam = rro.MaRam
        JOIN colors c ON gs.MaMau = c.MaMau
        SET gs.SoLuong = gs.SoLuong - ?
        WHERE gs.MaSanPham = ?
          AND rro.KichThuoc = ?
          AND c.TenMau = ?
          AND gs.SoLuong >= ?
    ";
    $stmt = mysqli_prepare($conn, $sqlUpdateStock);
    foreach ($cartItems as $item) {
        $sl = (int)$item['Soluong'];
        mysqli_stmt_bind_param($stmt, "iissi",
            $sl, $item['MaSanPham'], $item['KichThuoc'], $item['MauSac'], $sl
        );
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Lỗi khi cập nhật tồn kho: " . mysqli_error($conn));
        }
        if (mysqli_stmt_affected_rows($stmt) === 0) {
            // Có ai khác đặt mất rồi
            throw new Exception("Sản phẩm \"{$item['TenSanPham']}\" vừa hết hàng. Vui lòng thử lại.");
        }
    }
    mysqli_stmt_close($stmt);

    // 7d. Xóa các sản phẩm vừa đặt khỏi giỏ hàng (chỉ xóa item đã thanh toán, không xóa hết giỏ)
    $sqlDel = "DELETE FROM giohang WHERE MaHang IN ($placeholders) AND TenDangNhap = ?";
    $stmt = mysqli_prepare($conn, $sqlDel);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Lỗi khi xóa sản phẩm khỏi giỏ: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);

    // 7e. (Tùy chọn) Cập nhật tổng tiền thanh toán của khách hàng
    $sqlUpdateUser = "UPDATE khachhang SET TongTienThanhToan = COALESCE(TongTienThanhToan, 0) + ? WHERE TenDangNhap = ?";
    $stmt = mysqli_prepare($conn, $sqlUpdateUser);
    mysqli_stmt_bind_param($stmt, "ds", $tongTien, $tenDangNhap);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // OK -> commit
    mysqli_commit($conn);

    jsonResponse([
        'success'   => true,
        'message'   => 'Đặt hàng thành công!',
        'MaHoaDon'  => $maHoaDon
    ]);

} catch (Exception $e) {
    // Có lỗi -> rollback
    mysqli_rollback($conn);
    jsonResponse([
        'success' => false,
        'message' => 'Đặt hàng thất bại: ' . $e->getMessage()
    ]);
}
?>
