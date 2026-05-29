<?php
require '../connect.php';
session_start();

if (isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['user'] ?? '');
    $p = trim($_POST['pass'] ?? '');

    if ($u === '' || $p === '') {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT * FROM khachhang WHERE TenDangNhap = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $u);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row || $row['MatKhau'] !== $p) {
            $error = 'Sai tên đăng nhập hoặc mật khẩu!';
        } else {
            $_SESSION['user'] = $row['TenDangNhap'];
            $redirect = $_SESSION['redirect_after_login'] ?? '../index.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập — Mobile Web</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f2027;
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
        }

        .card {
            background: #fff;
            border-radius: 24px;
            padding: 44px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 32px 80px rgba(0,0,0,.4);
        }

        .brand {
            text-align: center;
            margin-bottom: 32px;
        }
        .brand-logo {
            font-size: 36px;
            margin-bottom: 8px;
        }
        .brand h1 {
            font-size: 24px;
            font-weight: 800;
            color: #1a202c;
            letter-spacing: -0.5px;
        }
        .brand p {
            font-size: 14px;
            color: #718096;
            margin-top: 4px;
        }

        .error-msg {
            background: #fff5f5;
            border: 1px solid #fc8181;
            color: #c53030;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .field {
            margin-bottom: 16px;
        }
        .field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 6px;
        }
        .input-wrap {
            position: relative;
        }
        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 17px;
            color: #a0aec0;
            pointer-events: none;
        }
        .field input {
            width: 100%;
            padding: 13px 14px 13px 44px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            color: #2d3748;
            background: #f7fafc;
            outline: none;
            transition: all .2s;
        }
        .field input:focus {
            border-color: #3498db;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(52,152,219,.12);
        }
        .toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 17px;
            color: #a0aec0;
            padding: 4px;
            line-height: 1;
            transition: color .2s;
        }
        .toggle-btn:hover { color: #4a5568; }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
            transition: all .2s;
            font-family: inherit;
            letter-spacing: .3px;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #2980b9, #2471a3);
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(52,152,219,.35);
        }
        .btn-login:active { transform: translateY(0); }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 22px 0;
            color: #cbd5e0;
            font-size: 12px;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .footer-links {
            text-align: center;
            font-size: 14px;
            color: #718096;
        }
        .footer-links a {
            color: #3498db;
            font-weight: 600;
            text-decoration: none;
        }
        .footer-links a:hover { text-decoration: underline; }

        .back-home {
            text-align: center;
            margin-top: 20px;
        }
        .back-home a {
            font-size: 13px;
            color: rgba(255,255,255,.6);
            text-decoration: none;
            transition: color .2s;
        }
        .back-home a:hover { color: #fff; }
    </style>
</head>
<body>

    <div>
        <div class="card">
            <div class="brand">
                <div class="brand-logo">📱</div>
                <h1>Mobile Web</h1>
                <p>Đăng nhập để tiếp tục mua sắm</p>
            </div>

            <?php if ($error): ?>
                <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="field">
                    <label>Tên đăng nhập</label>
                    <div class="input-wrap">
                        <span class="input-icon">👤</span>
                        <input type="text" name="user"
                               value="<?= htmlspecialchars($_POST['user'] ?? '') ?>"
                               placeholder="Nhập tên đăng nhập" required autofocus>
                    </div>
                </div>

                <div class="field">
                    <label>Mật khẩu</label>
                    <div class="input-wrap">
                        <span class="input-icon">🔒</span>
                        <input type="password" name="pass" id="passInput"
                               placeholder="Nhập mật khẩu" required>
                        <button type="button" class="toggle-btn" id="togglePass">👁</button>
                    </div>
                </div>

                <button type="submit" class="btn-login">Đăng nhập →</button>
            </form>

            <div class="divider">hoặc</div>

            <div class="footer-links">
                Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
            </div>
        </div>

        <div class="back-home">
            <a href="../index.php">← Quay về trang chủ</a>
        </div>
    </div>

    <script>
    const passInput  = document.getElementById('passInput');
    const togglePass = document.getElementById('togglePass');
    togglePass.addEventListener('click', function() {
        const isPass = passInput.type === 'password';
        passInput.type = isPass ? 'text' : 'password';
        this.textContent = isPass ? '🙈' : '👁';
    });
    </script>
</body>
</html>
