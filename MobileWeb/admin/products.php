<?php
require_once 'auth.php';
require_once '../connect.php';
require_once '_layout.php';

$msg = '';

// ===== XỬ LÝ FORM =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Cập nhật số lượng tồn kho
    if ($action === 'update_stock') {
        $maGia   = (int)$_POST['ma_gia'];
        $soLuong = max(0, (int)$_POST['so_luong']);

        $upd = mysqli_prepare($conn, "UPDATE giasanpham SET SoLuong=? WHERE MaGia=?");
        mysqli_stmt_bind_param($upd, 'ii', $soLuong, $maGia);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
        $msg = 'success|Đã cập nhật tồn kho.';
    }

    // Thêm sản phẩm mới
    if ($action === 'add_product') {
        $ten  = trim($_POST['ten_san_pham']);
        $hang = trim($_POST['hang']);
        $ngay = date('Y-m-d');

        if ($ten && $hang) {
            $ins = mysqli_prepare($conn,
                "INSERT INTO sanpham (TenSanPham, Hang, NgayNhap) VALUES (?,?,?)");
            mysqli_stmt_bind_param($ins, 'sss', $ten, $hang, $ngay);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
            $msg = 'success|Đã thêm sản phẩm "' . htmlspecialchars($ten) . '".';
        }
    }

    // Xóa sản phẩm
    if ($action === 'delete_product') {
        $maSP = (int)$_POST['ma_san_pham'];
        // Xóa cascade nếu có FK; nếu không, xóa thủ công các bảng liên quan
        mysqli_query($conn, "DELETE FROM giohang WHERE MaSanPham=$maSP");
        mysqli_query($conn, "DELETE FROM chitiethoadon WHERE MaSanPham=$maSP");
        mysqli_query($conn, "DELETE FROM giasanpham WHERE MaSanPham=$maSP");
        mysqli_query($conn, "DELETE FROM chitietsanpham WHERE MaSanPham=$maSP");
        mysqli_query($conn, "DELETE FROM colors WHERE MaSanPham=$maSP");
        mysqli_query($conn, "DELETE FROM ram_rom_option WHERE MaSanPham=$maSP");
        mysqli_query($conn, "DELETE FROM image WHERE MaSanPham=$maSP");
        mysqli_query($conn, "DELETE FROM video WHERE MaSanPham=$maSP");
        mysqli_query($conn, "DELETE FROM sanpham WHERE MaSanPham=$maSP");
        $msg = 'success|Đã xóa sản phẩm #' . $maSP;
    }
}

// ===== HIỂN THỊ =====
$search  = trim($_GET['q'] ?? '');
$filterH = trim($_GET['hang'] ?? '');

$sql = "SELECT sp.MaSanPham, sp.TenSanPham, sp.Hang, sp.NgayNhap,
        COALESCE(SUM(g.SoLuong),0) AS TongTon,
        COALESCE(MIN(g.GiaMoi),0) AS GiaMin
        FROM sanpham sp
        LEFT JOIN giasanpham g ON g.MaSanPham = sp.MaSanPham
        WHERE 1=1";
$params = []; $types = '';
if ($search !== '') {
    $sql .= " AND sp.TenSanPham LIKE ?";
    $params[] = "%$search%"; $types .= 's';
}
if ($filterH !== '') {
    $sql .= " AND sp.Hang=?";
    $params[] = $filterH; $types .= 's';
}
$sql .= " GROUP BY sp.MaSanPham ORDER BY sp.Hang, sp.TenSanPham";

$stmt = mysqli_prepare($conn, $sql);
if ($params) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$products = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Brands
$brands = ['Samsung','Apple','Xiaomi','Oppo','Realme','Poco'];

adminHeader('Quản lý sản phẩm', 'products');
$msgParts = $msg ? explode('|',$msg,2) : [];
if (!empty($msgParts)): ?>
<div class="alert alert-<?= $msgParts[0]==='success'?'success':'error' ?>">
    <?= $msgParts[1] ?>
</div>
<?php endif; ?>

<!-- Toolbar -->
<div class="card" style="margin-bottom:16px; padding:14px 20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap;">
            <input type="text" name="q" class="form-control" style="max-width:220px;"
                   placeholder="Tìm tên sản phẩm..." value="<?= htmlspecialchars($search) ?>">
            <select name="hang" class="form-control" style="max-width:140px;">
                <option value="">Tất cả hãng</option>
                <?php foreach ($brands as $b): ?>
                    <option value="<?= $b ?>" <?= $filterH===$b?'selected':'' ?>><?= $b ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-blue">Tìm</button>
        </form>
        <button class="btn btn-green" onclick="document.getElementById('add-modal').style.display='flex'">
            + Thêm sản phẩm
        </button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>📱 Danh sách sản phẩm (<?= count($products) ?>)</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Mã SP</th>
                <th>Tên sản phẩm</th>
                <th>Hãng</th>
                <th>Giá từ</th>
                <th>Tổng tồn kho</th>
                <th>Ngày nhập</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($products)): ?>
            <tr><td colspan="7" style="text-align:center;color:#aaa;padding:30px;">Không có sản phẩm.</td></tr>
        <?php endif; ?>
        <?php foreach ($products as $p): ?>
            <?php
            $ton = (int)$p['TongTon'];
            $tonClass = $ton === 0 ? 'color:#e74c3c' : ($ton <= 20 ? 'color:#d68910' : 'color:#196f3d');
            ?>
            <tr>
                <td>#<?= $p['MaSanPham'] ?></td>
                <td>
                    <a href="../product_detail.php?id=<?= $p['MaSanPham'] ?>" target="_blank"
                       style="font-weight:600;">
                        <?= htmlspecialchars($p['TenSanPham']) ?>
                    </a>
                </td>
                <td><?= htmlspecialchars($p['Hang']) ?></td>
                <td style="color:#e74c3c; font-weight:700;">
                    <?= $p['GiaMin'] > 0 ? number_format($p['GiaMin'],0,',','.').'đ' : '-' ?>
                </td>
                <td style="<?= $tonClass ?>; font-weight:700;"><?= $ton ?></td>
                <td><?= $p['NgayNhap'] ?></td>
                <td>
                    <button class="btn btn-orange btn-sm"
                            onclick="showStockModal(<?= $p['MaSanPham'] ?>, '<?= addslashes($p['TenSanPham']) ?>')">
                        Tồn kho
                    </button>
                    <button class="btn btn-red btn-sm"
                            onclick="confirmDelete(<?= $p['MaSanPham'] ?>, '<?= addslashes($p['TenSanPham']) ?>')">
                        Xóa
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal quản lý tồn kho theo variant -->
<div id="stock-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:999; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:12px; padding:28px; width:600px; max-width:95vw; max-height:85vh; overflow-y:auto;">
        <h3 id="stock-modal-title" style="margin-bottom:16px; color:#2c3e50;"></h3>
        <div id="stock-modal-body">Đang tải...</div>
        <div style="margin-top:16px; text-align:right;">
            <button class="btn btn-gray" onclick="closeStockModal()">Đóng</button>
        </div>
    </div>
</div>

<!-- Modal thêm sản phẩm -->
<div id="add-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:999; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:12px; padding:28px; width:420px; max-width:95vw;">
        <h3 style="margin-bottom:16px;">➕ Thêm sản phẩm mới</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_product">
            <div style="margin-bottom:14px;">
                <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px;">Tên sản phẩm</label>
                <input type="text" name="ten_san_pham" class="form-control" required placeholder="VD: iPhone 16 Pro">
            </div>
            <div style="margin-bottom:18px;">
                <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px;">Hãng</label>
                <select name="hang" class="form-control" required>
                    <?php foreach ($brands as $b): ?>
                        <option><?= $b ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn btn-gray" onclick="document.getElementById('add-modal').style.display='none'">Hủy</button>
                <button type="submit" class="btn btn-green">Thêm</button>
            </div>
        </form>
    </div>
</div>

<!-- Form xóa (ẩn) -->
<form id="delete-form" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete_product">
    <input type="hidden" name="ma_san_pham" id="delete-ma-sp">
</form>

<script>
function confirmDelete(maSP, ten) {
    if (confirm(`Xóa sản phẩm "${ten}" và toàn bộ dữ liệu liên quan?\nHành động này không thể hoàn tác!`)) {
        document.getElementById('delete-ma-sp').value = maSP;
        document.getElementById('delete-form').submit();
    }
}

function showStockModal(maSP, ten) {
    document.getElementById('stock-modal-title').textContent = '📦 Tồn kho: ' + ten;
    document.getElementById('stock-modal-body').innerHTML = '<p style="color:#888;">Đang tải...</p>';
    document.getElementById('stock-modal').style.display = 'flex';

    fetch('ajax_stock.php?ma_sp=' + maSP)
        .then(r => r.text())
        .then(html => { document.getElementById('stock-modal-body').innerHTML = html; });
}

function closeStockModal() {
    document.getElementById('stock-modal').style.display = 'none';
}
</script>
<?php adminFooter(); ?>
