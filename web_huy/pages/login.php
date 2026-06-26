<?php
require '../connect.php';
session_start();

if (isset($_POST['btn'])) {
    $u = trim($_POST['user'] ?? '');
    $p = trim($_POST['pass'] ?? '');

    if ($u === '' || $p === '') {
        $error = 'Vui lòng nhập đầy đủ tài khoản và mật khẩu.';
    } else {
        // Prepared Statement – chống SQL Injection
        $stmt = $conn->prepare("SELECT MaKhachHang, TenDangNhap, HoTen FROM khachhang WHERE TenDangNhap = ? AND MatKhau = ? LIMIT 1");
        $stmt->bind_param('ss', $u, $p);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION['user_id']  = $user['MaKhachHang'];
            $_SESSION['username'] = $user['HoTen'] ?: $user['TenDangNhap'];
            $_SESSION['cart']     = $_SESSION['cart'] ?? [];
            $stmt->close();
            $redirect = isset($_GET['redirect']) ? '../pages/' . basename($_GET['redirect']) : '../index.php';
            echo "<script>alert('Chào mừng " . htmlspecialchars($user['HoTen'] ?: $u, ENT_QUOTES) . "!'); window.location='" . $redirect . "';</script>";
        } else {
            $stmt->close();
            $error = 'Sai tài khoản hoặc mật khẩu!';
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Đăng nhập hệ thống</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <form method="POST">
        <h2 style="color:#2563eb">ĐĂNG NHẬP</h2>
        <?php if (!empty($error)): ?>
            <p style="color:#e74c3c; background:#fff0f0; padding:10px; border-radius:6px; margin-bottom:12px;">
                ⚠️ <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>
        <input type="text"     name="user" placeholder="Tên đăng nhập" required value="<?= htmlspecialchars($_POST['user'] ?? '') ?>">
        <input type="password" name="pass" placeholder="Mật khẩu" required>
        <button type="submit" name="btn" class="btn-blue">VÀO HỆ THỐNG</button>
        <p>Chưa có tài khoản <a href="register.php">Đăng ký tài khoản</a></p>
    </form>
</body>

</html>