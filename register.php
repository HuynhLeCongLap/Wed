<?php
$conn = new mysqli("localhost", "root", "", "user_db");
if ($conn->connect_error) die("Lỗi kết nối");

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $fullname = $_POST["fullname"];
    $username = $_POST["username"];
    $phone = $_POST["phone"];
    $password = $_POST["password"];
    $confirm = $_POST["confirm"];
    $birthday = $_POST["birthday"];
    $address = $_POST["address"];
    $email = $_POST["email"];

    // ===== VALIDATE =====
    if ($fullname == "" || $username == "" || $phone == "" || $password == "" || $confirm == "" || $address == "" || $email == "") {
        $errors[] = "Không được để trống!";
    }

    if (strlen($password) < 6) {
        $errors[] = "Mật khẩu >= 6 ký tự";
    }

    if ($password != $confirm) {
        $errors[] = "Mật khẩu không khớp";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email không hợp lệ";
    }

    // check trùng
    $check = $conn->query("SELECT * FROM users WHERE username='$username' OR email='$email'");
    if ($check->num_rows > 0) {
        $errors[] = "Trùng username hoặc email";
    }

    // ===== INSERT =====
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users(fullname, username, phone, password, birthday, address, email)
                VALUES('$fullname','$username','$phone','$hash','$birthday','$address','$email')";

        if ($conn->query($sql)) {
            header("Location: login.php");
            exit();
        } else {
            $errors[] = "Lỗi khi đăng ký";
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Register</title>

    <style>
        body {
            font-family: Arial;
            background: #eee;
        }

        .box {
            width: 320px;
            margin: 40px auto;
            background: white;
            padding: 20px;
        }

        input {
            width: 100%;
            margin: 5px 0;
            padding: 5px;
        }

        button {
            width: 100%;
            padding: 8px;
        }

        .error {
            color: red;
        }
    </style>

</head>

<body>

    <div class="box">
        <h2>Đăng ký</h2>

        <?php foreach ($errors as $e) echo "<p class='error'>$e</p>"; ?>

        <form method="POST">
            <input name="fullname" placeholder="Họ tên">
            <input name="username" placeholder="Username">
            <input name="phone" placeholder="SĐT">
            <input type="password" name="password" placeholder="Mật khẩu">
            <input type="password" name="confirm" placeholder="Nhập lại mật khẩu">
            <input type="date" name="birthday">
            <input name="address" placeholder="Địa chỉ">
            <input name="email" placeholder="Email">

            <button>Đăng ký</button>
        </form>

        <p>Đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
    </div>

</body>

</html>