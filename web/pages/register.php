<?php
require '../connect.php';

if (isset($_POST['btn'])) {
    $u = $_POST['user'];
    $p = $_POST['pass'];
    $e = $_POST['email'];
    $phone = $_POST['phone'];

    $messages = [];

    $checkUser = "SELECT 1 FROM khachhang WHERE TenDangNhap='$u' LIMIT 1";
    $checkEmail = "SELECT 1 FROM khachhang WHERE Email='$e' LIMIT 1";
    $checkPhone = "SELECT 1 FROM khachhang WHERE SoDienThoai='$phone' LIMIT 1";

    if (mysqli_num_rows(mysqli_query($conn, $checkUser)) > 0) {
        $messages[] = 'Tên đăng nhập này đã được đăng ký.';
    }
    if (mysqli_num_rows(mysqli_query($conn, $checkEmail)) > 0) {
        $messages[] = 'Email này đã được đăng ký.';
    }
    if (mysqli_num_rows(mysqli_query($conn, $checkPhone)) > 0) {
        $messages[] = 'Số điện thoại này đã được đăng ký.';
    }

    if (!empty($messages)) {
        $alert = implode(' ', $messages);
        echo "<script>alert('$alert'); window.history.back();</script>";
    } else {
        $sql = "INSERT INTO khachhang (TenDangNhap, MatKhau, Email, SoDienThoai) VALUES ('$u', '$p', '$e', '$phone')";

        if (mysqli_query($conn, $sql)) {
            echo "<script>alert('Đăng ký xong rồi đó!'); window.location='login.php';</script>";
        } else {
            echo "Lỗi: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Đăng ký thành viên</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <form method="POST">
        <h2 style="color:#e74c3c">ĐĂNG KÝ</h2>
        <input type="text" name="user" placeholder="Tên đăng nhập" required>
        <input type="password" name="pass" placeholder="Mật khẩu" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="tel" name="phone" placeholder="Số điện thoại" required>
        <button type="submit" name="btn" class="btn-red">XÁC NHẬN ĐĂNG KÝ</button>
        <p>Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a></p>
    </form>
</body>

</html>