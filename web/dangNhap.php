<?php
require 'connect.php';
session_start();

if (isset($_POST['btn'])) {
    $u = $_POST['user'];
    $p = $_POST['pass'];


    $sql = "SELECT * FROM khachhang WHERE TenDangNhap='$u' AND MatKhau='$p'";
    $kq = mysqli_query($conn, $sql);

    if (mysqli_num_rows($kq) > 0) {
        $_SESSION['user'] = $u;
        echo "<script>alert('Chào mừng bạn!'); window.location='index.php';</script>";
    } else {
        echo "<script>alert('Sai rồi bạn ơi!');</script>";
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Đăng nhập</title>
    <style>
        body {
            font-family: sans-serif;
            background: #eee;
            padding: 50px;
        }

        form {
            background: white;
            padding: 20px;
            width: 300px;
            margin: auto;
            border-radius: 10px;
        }

        input {
            width: 90%;
            padding: 10px;
            margin-bottom: 10px;
        }

        button {
            width: 100%;
            background: blue;
            color: white;
            padding: 10px;
            border: none;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <form method="POST">
        <h2 style="color:blue">ĐĂNG NHẬP</h2>
        <input type="text" name="user" placeholder="Tên đăng nhập">
        <input type="password" name="pass" placeholder="Mật khẩu">
        <button type="submit" name="btn">VÀO HỆ THỐNG</button>
        <p>Chưa có nick? <a href="dang-ky.php">Đăng ký đi</a></p>
    </form>
</body>

</html>