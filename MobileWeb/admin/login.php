<?php
session_start();
require_once '../connect.php';

if (!empty($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if (isset($_POST['btn'])) {
    $u = trim($_POST['user']);
    $p = trim($_POST['pass']);

    $stmt = mysqli_prepare($conn,
        "SELECT TenDangNhap, HoTen FROM admin_inf WHERE TenDangNhap = ? AND MatKhau = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ss', $u, $p);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($row) {
        $_SESSION['admin']      = $row['TenDangNhap'];
        $_SESSION['admin_name'] = $row['HoTen'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Sai tài khoản hoặc mật khẩu admin!';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin Login - Mobile Web</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            background: white;
            border-radius: 16px;
            padding: 40px;
            width: 360px;
            box-shadow: 0 20px 60px rgba(0,0,0,.3);
        }
        .logo { text-align: center; margin-bottom: 8px; }
        .logo h1 { font-size: 26px; color: #3498db; }
        .logo p  { color: #888; font-size: 14px; margin-bottom: 28px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:6px; }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }
        .form-group input:focus { border-color: #3498db; }
        .btn-login {
            width: 100%;
            padding: 13px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
        }
        .btn-login:hover { background: #2980b9; }
        .error { background:#fce; color:#c00; padding:10px; border-radius:6px; margin-bottom:16px; font-size:14px; }
        .back-link { text-align:center; margin-top:16px; font-size:13px; }
        .back-link a { color: #3498db; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">
            <h1>⚙️ Admin</h1>
            <p>Quản trị Mobile Web</p>
        </div>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Tên đăng nhập</label>
                <input type="text" name="user" value="<?= htmlspecialchars($_POST['user'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="pass" required>
            </div>
            <button type="submit" name="btn" class="btn-login">Đăng nhập</button>
        </form>
        <div class="back-link">
            <a href="../index.php">← Về trang chủ</a>
        </div>
    </div>
</body>
</html>
