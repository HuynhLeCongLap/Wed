<?php
$conn = new mysqli("localhost", "root", "", "user_db");
if ($conn->connect_error) die("Lỗi kết nối");

$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = $_POST["username"];
    $password = $_POST["password"];

    $sql = "SELECT * FROM users WHERE username='$username'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user["password"])) {
            $msg = "Đăng nhập thành công!";
        } else {
            $msg = "Sai mật khẩu!";
        }
    } else {
        $msg = "Không tồn tại user!";
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Login</title>

    <style>
        body {
            font-family: Arial;
            background: #eee;
        }

        .box {
            width: 300px;
            margin: 50px auto;
            background: white;
            padding: 20px;
        }

        input {
            width: 100%;
            margin: 5px 0;
        }

        button {
            width: 100%;
        }

        .error {
            color: red;
        }
    </style>

</head>

<body>

    <div class="box">
        <h2>Đăng nhập</h2>

        <p class="error"><?php echo $msg; ?></p>

        <form method="POST">
            <input name="username" placeholder="Username">
            <input type="password" name="password" placeholder="Password">

            <button>Login</button>
        </form>

        <p>Chưa có tài khoản? <a href="register.php">Đăng ký</a></p>
    </div>

</body>

</html>