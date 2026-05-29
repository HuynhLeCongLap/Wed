<?php
require_once 'auth.php';
require_once '../connect.php';

$maSP = (int)($_GET['ma_sp'] ?? 0);
if ($maSP <= 0) { echo 'Lỗi'; exit; }

// Xử lý cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ma_gia'])) {
    $maGia   = (int)$_POST['ma_gia'];
    $soLuong = max(0, (int)$_POST['so_luong']);
    $upd = mysqli_prepare($conn, "UPDATE giasanpham SET SoLuong=? WHERE MaGia=?");
    mysqli_stmt_bind_param($upd, 'ii', $soLuong, $maGia);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);
    echo json_encode(['ok' => true]);
    exit;
}

// Lấy variants
$stmt = mysqli_prepare($conn,
    "SELECT g.MaGia, g.GiaMoi, g.GiaCu, g.SoLuong,
            c.TenMau, r.KichThuoc
     FROM giasanpham g
     JOIN colors c ON c.MaMau = g.MaMau
     JOIN ram_rom_option r ON r.MaRam = g.MaRam
     WHERE g.MaSanPham = ?
     ORDER BY r.KichThuoc, c.TenMau");
mysqli_stmt_bind_param($stmt, 'i', $maSP);
mysqli_stmt_execute($stmt);
$variants = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

if (empty($variants)) {
    echo '<p style="color:#888; text-align:center;">Sản phẩm này chưa có variant nào.</p>';
    exit;
}
?>
<table style="width:100%; border-collapse:collapse; font-size:14px;">
    <thead>
        <tr style="background:#f8fafc;">
            <th style="padding:10px; text-align:left;">Dung lượng</th>
            <th style="padding:10px; text-align:left;">Màu sắc</th>
            <th style="padding:10px; text-align:right;">Giá bán</th>
            <th style="padding:10px; text-align:center; width:140px;">Tồn kho</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($variants as $v): ?>
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:9px 10px;"><?= htmlspecialchars($v['KichThuoc']) ?></td>
                <td style="padding:9px 10px;"><?= htmlspecialchars($v['TenMau']) ?></td>
                <td style="padding:9px 10px; text-align:right; color:#e74c3c; font-weight:600;">
                    <?= number_format($v['GiaMoi'],0,',','.')?>đ
                </td>
                <td style="padding:9px 10px; text-align:center;">
                    <div style="display:flex; align-items:center; gap:6px; justify-content:center;">
                        <input type="number" id="stock-<?= $v['MaGia'] ?>"
                               value="<?= (int)$v['SoLuong'] ?>" min="0"
                               style="width:70px; padding:6px; border:1px solid #ddd; border-radius:4px; text-align:center; font-size:14px;">
                        <button onclick="saveStock(<?= $v['MaGia'] ?>, <?= $maSP ?>)"
                                style="padding:6px 10px; background:#3498db; color:white; border:none; border-radius:4px; cursor:pointer; font-size:12px;">
                            Lưu
                        </button>
                    </div>
                    <div id="msg-<?= $v['MaGia'] ?>" style="font-size:11px; margin-top:3px;"></div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<script>
function saveStock(maGia, maSP) {
    const qty = document.getElementById('stock-' + maGia).value;
    const msgEl = document.getElementById('msg-' + maGia);
    fetch('ajax_stock.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ma_gia=${maGia}&so_luong=${qty}&ma_sp=${maSP}`
    })
    .then(r => r.json())
    .then(d => {
        msgEl.style.color = d.ok ? 'green' : 'red';
        msgEl.textContent = d.ok ? '✓ Đã lưu' : '✗ Lỗi';
        setTimeout(() => { msgEl.textContent = ''; }, 2000);
    });
}
</script>
