<?php
require_once 'auth.php';
require_once '../connect.php';
require_once '_layout.php';

// === Thống kê tổng quan ===
$stats = [];

$r = mysqli_query($conn, "SELECT COUNT(*) FROM hoadon");
$stats['orders'] = mysqli_fetch_row($r)[0];

$r = mysqli_query($conn, "SELECT COALESCE(SUM(TongTien),0) FROM hoadon WHERE TrangThai != 'Đã hủy'");
$stats['revenue'] = (float)mysqli_fetch_row($r)[0];

$r = mysqli_query($conn, "SELECT COUNT(*) FROM khachhang");
$stats['customers'] = mysqli_fetch_row($r)[0];

$r = mysqli_query($conn, "SELECT COUNT(*) FROM sanpham");
$stats['products'] = mysqli_fetch_row($r)[0];

$r = mysqli_query($conn, "SELECT COUNT(*) FROM hoadon WHERE TrangThai='Chưa xác nhận'");
$stats['pending'] = mysqli_fetch_row($r)[0];

// === Doanh thu 7 ngày gần nhất ===
$revenueData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = mysqli_prepare($conn,
        "SELECT COALESCE(SUM(TongTien),0) FROM hoadon WHERE NgayLap=? AND TrangThai != 'Đã hủy'");
    mysqli_stmt_bind_param($stmt, 's', $date);
    mysqli_stmt_execute($stmt);
    $val = (float)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
    mysqli_stmt_close($stmt);
    $revenueData[] = ['date' => $date, 'revenue' => $val];
}

// === Đơn hàng mới nhất ===
$recentOrders = mysqli_fetch_all(
    mysqli_query($conn,
        "SELECT h.*, k.HoTen
         FROM hoadon h LEFT JOIN khachhang k ON k.TenDangNhap = h.TenDangNhap
         ORDER BY h.MaHoaDon DESC LIMIT 8"),
    MYSQLI_ASSOC
);

// === Top sản phẩm bán chạy ===
$topProducts = mysqli_fetch_all(
    mysqli_query($conn,
        "SELECT sp.TenSanPham, SUM(ct.SoLuong) AS TongBan, SUM(ct.ThanhTien) AS DoanhThu
         FROM chitiethoadon ct
         JOIN sanpham sp ON sp.MaSanPham = ct.MaSanPham
         JOIN hoadon h ON h.MaHoaDon = ct.MaHoaDon
         WHERE h.TrangThai != 'Đã hủy'
         GROUP BY ct.MaSanPham ORDER BY TongBan DESC LIMIT 5"),
    MYSQLI_ASSOC
);

adminHeader('Dashboard', 'index');
?>

<!-- Stat Cards -->
<div class="stat-grid">
    <div class="stat-card" style="border-top:4px solid #e74c3c;">
        <div class="stat-icon">💰</div>
        <div class="stat-value"><?= number_format($stats['revenue'] / 1000000, 1) ?>M</div>
        <div class="stat-label">Doanh thu (đ)</div>
    </div>
    <div class="stat-card" style="border-top:4px solid #3498db;">
        <div class="stat-icon">🛒</div>
        <div class="stat-value"><?= $stats['orders'] ?></div>
        <div class="stat-label">Tổng đơn hàng</div>
    </div>
    <div class="stat-card" style="border-top:4px solid #f39c12;">
        <div class="stat-icon">⏳</div>
        <div class="stat-value"><?= $stats['pending'] ?></div>
        <div class="stat-label">Chờ xác nhận</div>
    </div>
    <div class="stat-card" style="border-top:4px solid #2ecc71;">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?= $stats['customers'] ?></div>
        <div class="stat-label">Khách hàng</div>
    </div>
    <div class="stat-card" style="border-top:4px solid #9b59b6;">
        <div class="stat-icon">📱</div>
        <div class="stat-value"><?= $stats['products'] ?></div>
        <div class="stat-label">Sản phẩm</div>
    </div>
</div>

<!-- Biểu đồ doanh thu -->
<div class="card">
    <div class="card-header">
        <h2>📈 Doanh thu 7 ngày gần nhất</h2>
    </div>
    <div style="display:flex; align-items:flex-end; gap:10px; height:160px; padding:10px 0;">
        <?php
        $maxRev = max(array_column($revenueData, 'revenue')) ?: 1;
        foreach ($revenueData as $d):
            $pct = $d['revenue'] / $maxRev * 100;
            $isToday = $d['date'] === date('Y-m-d');
        ?>
            <div style="flex:1; display:flex; flex-direction:column; align-items:center; gap:4px;">
                <div style="font-size:11px; color:#e74c3c; font-weight:700;">
                    <?= $d['revenue'] > 0 ? number_format($d['revenue']/1000000,1).'M' : '' ?>
                </div>
                <div style="width:100%; background:<?= $isToday ? '#3498db' : '#a9cce3' ?>;
                            height:<?= max(4, $pct) ?>%;
                            border-radius:4px 4px 0 0;
                            min-height:4px;
                            transition: height .3s;"></div>
                <div style="font-size:11px; color:#888;"><?= date('d/m', strtotime($d['date'])) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div style="display:grid; grid-template-columns:1.5fr 1fr; gap:20px;">
    <!-- Đơn hàng mới nhất -->
    <div class="card">
        <div class="card-header">
            <h2>🛒 Đơn hàng gần đây</h2>
            <a href="orders.php" class="btn btn-blue btn-sm">Xem tất cả</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Mã ĐH</th>
                    <th>Khách hàng</th>
                    <th>Ngày</th>
                    <th>Tổng tiền</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentOrders as $o): ?>
                    <tr>
                        <td><a href="orders.php?detail=<?= $o['MaHoaDon'] ?>">#<?= $o['MaHoaDon'] ?></a></td>
                        <td><?= htmlspecialchars($o['HoTenNhan'] ?? $o['TenDangNhap']) ?></td>
                        <td><?= $o['NgayLap'] ?></td>
                        <td style="color:#e74c3c; font-weight:700;"><?= number_format($o['TongTien'], 0, ',', '.') ?>đ</td>
                        <td><?= statusBadge($o['TrangThai']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Top sản phẩm -->
    <div class="card">
        <div class="card-header">
            <h2>🏆 Sản phẩm bán chạy</h2>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Sản phẩm</th>
                    <th>Đã bán</th>
                    <th>Doanh thu</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topProducts as $tp): ?>
                    <tr>
                        <td style="font-size:13px;"><?= htmlspecialchars($tp['TenSanPham']) ?></td>
                        <td style="text-align:center; font-weight:700;"><?= $tp['TongBan'] ?></td>
                        <td style="color:#e74c3c; font-size:13px;"><?= number_format($tp['DoanhThu']/1000000,1) ?>M</td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($topProducts)): ?>
                    <tr><td colspan="3" style="text-align:center;color:#aaa;">Chưa có dữ liệu</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php adminFooter(); ?>
