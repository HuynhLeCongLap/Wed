<?php
require_once 'auth.php';
require_once '../connect.php';
require_once '_layout.php';

$msg = '';

// Cập nhật trạng thái
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $maHD     = (int)$_POST['ma_hoadon'];
    $newStatus = trim($_POST['trang_thai']);
    $ghiChu   = trim($_POST['ghi_chu'] ?? '');

    $validStatus = ['Chưa xác nhận','Đã xác nhận','Đang giao','Hoàn thành','Đã hủy'];
    if (in_array($newStatus, $validStatus) && $maHD > 0) {
        // Cập nhật trạng thái
        $upd = mysqli_prepare($conn, "UPDATE hoadon SET TrangThai=? WHERE MaHoaDon=?");
        mysqli_stmt_bind_param($upd, 'si', $newStatus, $maHD);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);

        // Ghi tracking
        $ins = mysqli_prepare($conn,
            "INSERT INTO order_tracking (MaHoaDon, TrangThai, GhiChu) VALUES (?,?,?)");
        $noteText = $ghiChu ?: 'Cập nhật bởi admin';
        mysqli_stmt_bind_param($ins, 'iss', $maHD, $newStatus, $noteText);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);

        $msg = 'success|Đã cập nhật trạng thái đơn hàng #' . $maHD;
    }
}

// Lọc
$filterStatus = $_GET['status'] ?? 'all';
$search       = trim($_GET['q'] ?? '');
$validStatuses = ['all','Chưa xác nhận','Đã xác nhận','Đang giao','Hoàn thành','Đã hủy'];
if (!in_array($filterStatus, $validStatuses)) $filterStatus = 'all';

$sql  = "SELECT h.*, k.HoTen FROM hoadon h LEFT JOIN khachhang k ON k.TenDangNhap=h.TenDangNhap WHERE 1=1";
$params = [];
$types  = '';

if ($filterStatus !== 'all') {
    $sql .= " AND h.TrangThai=?";
    $params[] = $filterStatus; $types .= 's';
}
if ($search !== '') {
    $sql .= " AND (h.MaHoaDon LIKE ? OR h.TenDangNhap LIKE ? OR h.HoTenNhan LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}
$sql .= " ORDER BY h.MaHoaDon DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($params) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$orders = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

adminHeader('Quản lý đơn hàng', 'orders');

$msgParts = $msg ? explode('|', $msg, 2) : [];
if (!empty($msgParts)):
?>
<div class="alert alert-<?= $msgParts[0] === 'success' ? 'success' : 'error' ?>">
    <?= htmlspecialchars($msgParts[1]) ?>
</div>
<?php endif; ?>

<!-- Toolbar -->
<div class="card" style="margin-bottom:16px; padding:14px 20px;">
    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <input type="text" name="q" class="form-control" style="max-width:220px;"
               placeholder="Tìm mã ĐH, khách hàng..." value="<?= htmlspecialchars($search) ?>">
        <select name="status" class="form-control" style="max-width:180px;">
            <?php
            $opts = ['all'=>'Tất cả trạng thái','Chưa xác nhận'=>'Chưa xác nhận',
                     'Đã xác nhận'=>'Đã xác nhận','Đang giao'=>'Đang giao',
                     'Hoàn thành'=>'Hoàn thành','Đã hủy'=>'Đã hủy'];
            foreach ($opts as $v => $l):
            ?>
                <option value="<?= $v ?>" <?= $filterStatus===$v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-blue">Tìm kiếm</button>
        <?php if ($filterStatus !== 'all' || $search !== ''): ?>
            <a href="orders.php" class="btn btn-gray">Xóa lọc</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2>🛒 Đơn hàng (<?= count($orders) ?>)</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Mã ĐH</th>
                <th>Khách hàng</th>
                <th>Ngày đặt</th>
                <th>Tổng tiền</th>
                <th>Thanh toán</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($orders)): ?>
            <tr><td colspan="7" style="text-align:center; color:#aaa; padding:30px;">Không có đơn hàng nào.</td></tr>
        <?php endif; ?>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><b>#<?= $o['MaHoaDon'] ?></b></td>
                <td>
                    <div style="font-weight:600;"><?= htmlspecialchars($o['HoTenNhan'] ?? $o['TenDangNhap']) ?></div>
                    <div style="font-size:12px;color:#888;"><?= htmlspecialchars($o['SoDienThoaiNhan'] ?? '') ?></div>
                </td>
                <td><?= $o['NgayLap'] ?></td>
                <td style="color:#e74c3c; font-weight:700;"><?= number_format($o['TongTien'],0,',','.') ?>đ</td>
                <td><?= ($o['PhuongThucThanhToan'] ?? 'COD') === 'COD' ? 'COD' : 'Chuyển khoản' ?></td>
                <td><?= statusBadge($o['TrangThai']) ?></td>
                <td>
                    <button class="btn btn-orange btn-sm"
                            onclick="showUpdateModal(<?= $o['MaHoaDon'] ?>, '<?= addslashes($o['TrangThai']) ?>')">
                        Cập nhật
                    </button>
                    <a href="../print_bill.php?id=<?= $o['MaHoaDon'] ?>" target="_blank"
                       class="btn btn-gray btn-sm">In ĐH</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal cập nhật trạng thái -->
<div id="update-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:999; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:12px; padding:28px; width:420px; max-width:95vw;">
        <h3 style="margin-bottom:16px; color:#2c3e50;">Cập nhật trạng thái đơn hàng</h3>
        <form method="POST" id="update-form">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="ma_hoadon" id="modal-ma-hd">
            <div style="margin-bottom:14px;">
                <label style="font-size:13px;font-weight:600;color:#555;display:block;margin-bottom:6px;">Trạng thái mới</label>
                <select name="trang_thai" id="modal-status" class="form-control">
                    <option>Chưa xác nhận</option>
                    <option>Đã xác nhận</option>
                    <option>Đang giao</option>
                    <option>Hoàn thành</option>
                    <option>Đã hủy</option>
                </select>
            </div>
            <div style="margin-bottom:18px;">
                <label style="font-size:13px;font-weight:600;color:#555;display:block;margin-bottom:6px;">Ghi chú (không bắt buộc)</label>
                <input type="text" name="ghi_chu" class="form-control" placeholder="VD: Đã xác nhận và đóng gói...">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn btn-gray" onclick="closeModal()">Hủy</button>
                <button type="submit" class="btn btn-blue">Lưu</button>
            </div>
        </form>
    </div>
</div>

<script>
function showUpdateModal(maHD, currentStatus) {
    document.getElementById('modal-ma-hd').value = maHD;
    document.getElementById('modal-status').value = currentStatus;
    const modal = document.getElementById('update-modal');
    modal.style.display = 'flex';
}
function closeModal() {
    document.getElementById('update-modal').style.display = 'none';
}
document.getElementById('update-modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
<?php adminFooter(); ?>
