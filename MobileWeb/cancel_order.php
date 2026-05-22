<?php
/**
 * HỦY ĐƠN HÀNG (AJAX endpoint)
 * - Chỉ user chủ đơn mới được hủy
 * - Chỉ hủy được khi đơn ở trạng thái "Chưa xác nhận"
 * - Hoàn lại tồn kho khi hủy
 */

session_start();
require_once 'connect.php';

header('Content-Type: application/json; charset=utf-8');

function jsonResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// 1. Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    jsonResponse(['success' => false, 'message' => 'Bạn cần đăng nhập.']);
}
$tenDangNhap = $_SESSION['user'];

// 2. Kiểm tra method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
}

$maHoaDon = isset($_POST['MaHoaDon']) ? intval($_POST['MaHoaDon']) : 0;
if ($maHoaDon <= 0) {
    jsonResponse(['success' => false, 'message' => 'Mã đơn hàng không hợp lệ.']);
}

// 3. Kiểm tra đơn có tồn tại và thuộc về user này
$sql = "SELECT * FROM hoadon WHERE MaHoaDon = ? AND TenDangNhap = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "is", $maHoaDon, $tenDangNhap);
mysqli_stmt_execute($stmt);
$hoaDon = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$hoaDon) {
    jsonResponse(['success' => false, 'message' => 'Không tìm thấy đơn hàng.']);
}

// 4. Chỉ cho hủy khi đơn ở trạng thái "Chưa xác nhận"
if ($hoaDon['TrangThai'] !== 'Chưa xác nhận') {
    jsonResponse([
        'success' => false,
        'message' => 'Không thể hủy đơn ở trạng thái "' . $hoaDon['TrangThai'] . '".'
    ]);
}

// 5. Thực hiện hủy đơn (dùng transaction để đảm bảo nhất quán)
mysqli_begin_transaction($conn);

try {
    // 5a. Đổi trạng thái đơn -> "Đã hủy"
    $sqlUp = "UPDATE hoadon SET TrangThai = 'Đã hủy' WHERE MaHoaDon = ? AND TenDangNhap = ? AND TrangThai = 'Chưa xác nhận'";
    $stmt = mysqli_prepare($conn, $sqlUp);
    mysqli_stmt_bind_param($stmt, "is", $maHoaDon, $tenDangNhap);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Lỗi cập nhật trạng thái: " . mysqli_error($conn));
    }
    if (mysqli_stmt_affected_rows($stmt) === 0) {
        throw new Exception("Đơn hàng đã được xử lý, không thể hủy.");
    }
    mysqli_stmt_close($stmt);

    // 5b. Hoàn lại tồn kho cho từng sản phẩm
    $sqlGetItems = "SELECT * FROM chitiethoadon WHERE MaHoaDon = ?";
    $stmt = mysqli_prepare($conn, $sqlGetItems);
    mysqli_stmt_bind_param($stmt, "i", $maHoaDon);
    mysqli_stmt_execute($stmt);
    $items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $sqlReturnStock = "
        UPDATE giasanpham gs
        JOIN ram_rom_option rro ON gs.MaRam = rro.MaRam
        JOIN colors c ON gs.MaMau = c.MaMau
        SET gs.SoLuong = gs.SoLuong + ?
        WHERE gs.MaSanPham = ?
          AND rro.KichThuoc = ?
          AND c.TenMau = ?
    ";
    $stmt = mysqli_prepare($conn, $sqlReturnStock);
    foreach ($items as $item) {
        $sl = (int)$item['SoLuong'];
        mysqli_stmt_bind_param($stmt, "iiss",
            $sl, $item['MaSanPham'], $item['KichThuoc'], $item['TenMau']
        );
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);

    // 5c. Trừ lại TongTienThanhToan của khách hàng
    $sqlUpdateUser = "UPDATE khachhang SET TongTienThanhToan = GREATEST(COALESCE(TongTienThanhToan, 0) - ?, 0) WHERE TenDangNhap = ?";
    $stmt = mysqli_prepare($conn, $sqlUpdateUser);
    mysqli_stmt_bind_param($stmt, "ds", $hoaDon['TongTien'], $tenDangNhap);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
    jsonResponse(['success' => true, 'message' => 'Đã hủy đơn hàng thành công.']);

} catch (Exception $e) {
    mysqli_rollback($conn);
    jsonResponse(['success' => false, 'message' => $e->getMessage()]);
}
?>
