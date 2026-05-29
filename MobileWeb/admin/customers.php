<?php
require_once 'auth.php';
require_once '../connect.php';
require_once '_layout.php';

$search = trim($_GET['q'] ?? '');
$sql = "SELECT k.*,
        (SELECT COUNT(*) FROM hoadon WHERE TenDangNhap=k.TenDangNhap) AS SoDonHang,
        (SELECT COALESCE(SUM(TongTien),0) FROM hoadon WHERE TenDangNhap=k.TenDangNhap AND TrangThai != 'Đã hủy') AS TongChiTieu
        FROM khachhang k WHERE 1=1";
$params = []; $types = '';
if ($search !== '') {
    $sql .= " AND (k.TenDangNhap LIKE ? OR k.HoTen LIKE ? OR k.Email LIKE ? OR k.SoDienThoai LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like, $like];
    $types  = 'ssss';
}
$sql .= " ORDER BY k.MaKhachHang DESC";
$stmt = mysqli_prepare($conn, $sql);
if ($params) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$customers = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

adminHeader('Quản lý khách hàng', 'customers');
?>

<!-- Search -->
<div class="card" style="margin-bottom:16px; padding:14px 20px;">
    <form method="GET" style="display:flex; gap:10px;">
        <input type="text" name="q" class="form-control" style="max-width:300px;"
               placeholder="Tìm tên, email, SĐT..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-blue">Tìm kiếm</button>
        <?php if ($search !== ''): ?>
            <a href="customers.php" class="btn btn-gray">Xóa lọc</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2>👥 Khách hàng (<?= count($customers) ?>)</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Mã KH</th>
                <th>Tài khoản</th>
                <th>Họ tên</th>
                <th>Email</th>
                <th>Số điện thoại</th>
                <th>Xác thực</th>
                <th>Đơn hàng</th>
                <th>Tổng chi tiêu</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($customers)): ?>
            <tr><td colspan="8" style="text-align:center;color:#aaa;padding:30px;">Không có khách hàng nào.</td></tr>
        <?php endif; ?>
        <?php foreach ($customers as $c): ?>
            <tr>
                <td>#<?= $c['MaKhachHang'] ?></td>
                <td><b><?= htmlspecialchars($c['TenDangNhap']) ?></b></td>
                <td><?= htmlspecialchars($c['HoTen'] ?? '') ?></td>
                <td style="font-size:13px;"><?= htmlspecialchars($c['Email']) ?></td>
                <td><?= htmlspecialchars($c['SoDienThoai']) ?></td>
                <td>
                    <?php if (isset($c['DaXacThuc']) && $c['DaXacThuc']): ?>
                        <span style="color:#196f3d; font-size:13px;">✅ Đã xác thực</span>
                    <?php else: ?>
                        <span style="color:#d68910; font-size:13px;">⏳ Chưa xác thực</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center; font-weight:700;"><?= $c['SoDonHang'] ?></td>
                <td style="color:#e74c3c; font-weight:700;">
                    <?= $c['TongChiTieu'] > 0 ? number_format($c['TongChiTieu'],0,',','.').'đ' : '-' ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php adminFooter(); ?>
