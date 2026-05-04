<?php
require 'connect.php';

if (isset($_POST['btn'])) {
    $u = $_POST['user'];
    $p = $_POST['pass'];
    $e = $_POST['email'];


    $sql = "INSERT INTO khachhang (TenDangNhap, MatKhau, Email) VALUES ('$u', '$p', '$e')";

    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('Đăng ký xong rồi đó!'); window.location='dang-nhap.php';</script>";
    } else {
        echo "Loi: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Đăng ký</title>
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
            background: red;
            color: white;
            padding: 10px;
            border: none;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <form method="POST">
        <h2 style="color:red">ĐĂNG KÝ</h2>
        <input type="text" name="user" placeholder="Tên đăng nhập" required>
        <input type="password" name="pass" placeholder="Mật khẩu" required>
        <input type="email" name="email" placeholder="Email" required>
        <button type="submit" name="btn">XÁC NHẬN ĐĂNG KÝ</button>
        <p>Đã có tài khoản? <a href="dang-nhap.php">Đăng nhập</a></p>
    </form>
</body>

</html>