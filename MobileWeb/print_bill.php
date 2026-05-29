<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['user'])) {
    header('Location: pages/login.php');
    exit;
}

$maHoaDon    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tenDangNhap = $_SESSION['user'];

// Bảo mật: chỉ chủ đơn mới in được
$stmt = mysqli_prepare($conn, "SELECT * FROM hoadon WHERE MaHoaDon = ? AND TenDangNhap = ?");
mysqli_stmt_bind_param($stmt, 'is', $maHoaDon, $tenDangNhap);
mysqli_stmt_execute($stmt);
$hoaDon = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$hoaDon) {
    die('Không tìm thấy đơn hàng.');
}

// Chi tiết sản phẩm
$stmt = mysqli_prepare($conn,
    "SELECT ct.*, sp.TenSanPham
     FROM chitiethoadon ct
     JOIN sanpham sp ON sp.MaSanPham = ct.MaSanPham
     WHERE ct.MaHoaDon = ?");
mysqli_stmt_bind_param($stmt, 'i', $maHoaDon);
mysqli_stmt_execute($stmt);
$chiTiet = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Thông tin khách hàng
$stmt = mysqli_prepare($conn,
    "SELECT HoTen, SoDienThoai, Email FROM khachhang WHERE TenDangNhap = ?");
mysqli_stmt_bind_param($stmt, 's', $tenDangNhap);
mysqli_stmt_execute($stmt);
$khachHang = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hóa đơn #<?= $maHoaDon ?> - Mobile Web</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 14px;
            color: #333;
            background: white;
        }
        .bill-wrap {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px 40px;
        }
        /* Header */
        .bill-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #3498db;
        }
        .company-name {
            font-size: 28px;
            font-weight: 800;
            color: #3498db;
        }
        .company-sub {
            font-size: 13px;
            color: #666;
            margin-top: 4px;
        }
        .bill-title {
            text-align: right;
        }
        .bill-title h1 {
            font-size: 22px;
            color: #2c3e50;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .bill-title .bill-no {
            font-size: 18px;
            color: #e74c3c;
            font-weight: 700;
            margin-top: 4px;
        }
        .bill-title .bill-date {
            font-size: 13px;
            color: #888;
            margin-top: 4px;
        }

        /* Info grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .info-block h3 {
            font-size: 13px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            border-bottom: 1px solid #eee;
            padding-bottom: 6px;
        }
        .info-block p {
            margin-bottom: 4px;
            font-size: 14px;
        }
        .info-block p strong { color: #2c3e50; }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        thead tr {
            background: #3498db;
            color: white;
        }
        thead th {
            padding: 12px 10px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
        }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody td {
            padding: 11px 10px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Totals */
        .totals {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 24px;
        }
        .totals-box {
            width: 280px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 7px 0;
            font-size: 14px;
            border-bottom: 1px solid #eee;
        }
        .total-row:last-child {
            border-bottom: none;
            font-size: 17px;
            font-weight: 700;
            color: #e74c3c;
            padding-top: 12px;
            margin-top: 4px;
            border-top: 2px solid #eee;
        }

        /* Status */
        .status-row {
            text-align: center;
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 18px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        .badge-pending    { background: #fef9e7; color: #d68910; }
        .badge-confirmed  { background: #d6eaf8; color: #2874a6; }
        .badge-shipping   { background: #d4e6f1; color: #1f618d; }
        .badge-done       { background: #d5f5e3; color: #196f3d; }
        .badge-cancelled  { background: #fadbd8; color: #922b21; }

        /* Footer */
        .bill-footer {
            text-align: center;
            color: #aaa;
            font-size: 12px;
            padding-top: 20px;
            border-top: 1px dashed #ddd;
            margin-top: 10px;
        }

        /* Print controls */
        .print-controls {
            text-align: center;
            padding: 16px;
            background: #f0f2f5;
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .print-controls button, .print-controls a {
            padding: 10px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }
        .btn-print { background: #3498db; color: white; }
        .btn-close  { background: #95a5a6; color: white; }

        @media print {
            .print-controls { display: none !important; }
            body { padding: 0; }
            .bill-wrap { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="print-controls">
        <button class="btn-print" onclick="window.print()">🖨 In hóa đơn</button>
        <a class="btn-close" href="order_detail.php?id=<?= $maHoaDon ?>">← Quay lại</a>
    </div>

    <div class="bill-wrap">
        <!-- Header -->
        <div class="bill-header">
            <div>
                <div class="company-name">Mobile Web</div>
                <div class="company-sub">Cửa hàng điện thoại chính hãng</div>
                <div class="company-sub">Email: info@mobileweb.vn | ĐT: 0123 456 789</div>
            </div>
            <div class="bill-title">
                <h1>Hóa đơn bán hàng</h1>
                <div class="bill-no">#<?= str_pad($hoaDon['MaHoaDon'], 6, '0', STR_PAD_LEFT) ?></div>
                <div class="bill-date">Ngày: <?= date('d/m/Y', strtotime($hoaDon['NgayLap'])) ?></div>
            </div>
        </div>

        <!-- Info -->
        <div class="info-grid">
            <div class="info-block">
                <h3>Thông tin người mua</h3>
                <p><strong><?= htmlspecialchars($khachHang['HoTen'] ?? $tenDangNhap) ?></strong></p>
                <p>ĐT: <?= htmlspecialchars($khachHang['SoDienThoai'] ?? '') ?></p>
                <p>Email: <?= htmlspecialchars($khachHang['Email'] ?? '') ?></p>
                <p>TK: <?= htmlspecialchars($tenDangNhap) ?></p>
            </div>
            <div class="info-block">
                <h3>Thông tin giao hàng</h3>
                <p><strong><?= htmlspecialchars($hoaDon['HoTenNhan'] ?? '') ?></strong></p>
                <p>ĐT: <?= htmlspecialchars($hoaDon['SoDienThoaiNhan'] ?? '') ?></p>
                <p>Địa chỉ: <?= htmlspecialchars($hoaDon['DiaChiNhan'] ?? '') ?></p>
                <p>Thanh toán:
                    <?= ($hoaDon['PhuongThucThanhToan'] ?? 'COD') === 'COD'
                        ? 'Tiền mặt (COD)' : 'Chuyển khoản' ?>
                </p>
            </div>
        </div>

        <!-- Trạng thái -->
        <div class="status-row">
            Trạng thái đơn hàng:&nbsp;
            <?php
            $statusClassMap = [
                'Chưa xác nhận' => 'badge-pending',
                'Đã xác nhận'   => 'badge-confirmed',
                'Đang giao'     => 'badge-shipping',
                'Hoàn thành'    => 'badge-done',
                'Đã hủy'        => 'badge-cancelled',
            ];
            $cls = $statusClassMap[$hoaDon['TrangThai']] ?? 'badge-pending';
            ?>
            <span class="status-badge <?= $cls ?>"><?= htmlspecialchars($hoaDon['TrangThai']) ?></span>
        </div>

        <!-- Bảng sản phẩm -->
        <table>
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Sản phẩm</th>
                    <th>Màu / Dung lượng</th>
                    <th class="text-center">SL</th>
                    <th class="text-right">Đơn giá</th>
                    <th class="text-right">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($chiTiet as $idx => $item): ?>
                    <?php $donGia = $item['SoLuong'] > 0 ? $item['ThanhTien'] / $item['SoLuong'] : 0; ?>
                    <tr>
                        <td class="text-center"><?= $idx + 1 ?></td>
                        <td><strong><?= htmlspecialchars($item['TenSanPham']) ?></strong></td>
                        <td><?= htmlspecialchars($item['TenMau']) ?> / <?= htmlspecialchars($item['KichThuoc']) ?></td>
                        <td class="text-center"><?= (int)$item['SoLuong'] ?></td>
                        <td class="text-right"><?= number_format($donGia, 0, ',', '.') ?>đ</td>
                        <td class="text-right"><strong><?= number_format($item['ThanhTien'], 0, ',', '.') ?>đ</strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Tổng tiền -->
        <div class="totals">
            <div class="totals-box">
                <div class="total-row">
                    <span>Tạm tính:</span>
                    <span><?= number_format($hoaDon['TongTien'], 0, ',', '.') ?>đ</span>
                </div>
                <div class="total-row">
                    <span>Phí vận chuyển:</span>
                    <span>Miễn phí</span>
                </div>
                <div class="total-row">
                    <span>Tổng cộng:</span>
                    <span><?= number_format($hoaDon['TongTien'], 0, ',', '.') ?>đ</span>
                </div>
            </div>
        </div>

        <?php if (!empty($hoaDon['GhiChu'])): ?>
        <p style="margin-bottom:16px; font-size:13px; color:#666;">
            <strong>Ghi chú:</strong> <?= htmlspecialchars($hoaDon['GhiChu']) ?>
        </p>
        <?php endif; ?>

        <div class="bill-footer">
            <p>Cảm ơn bạn đã mua hàng tại Mobile Web!</p>
            <p>Mọi thắc mắc vui lòng liên hệ: 0123 456 789 | info@mobileweb.vn</p>
            <p style="margin-top:6px;">
                In ngày: <?= date('d/m/Y H:i') ?>
            </p>
        </div>
    </div>
</body>
</html>
