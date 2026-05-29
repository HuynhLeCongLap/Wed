<?php
require '../connect.php';

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u     = trim($_POST['user']  ?? '');
    $p     = trim($_POST['pass']  ?? '');
    $p2    = trim($_POST['pass2'] ?? '');
    $e     = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $errors = [];
    if (strlen($u) < 3)                              $errors[] = 'Tên đăng nhập phải có ít nhất 3 ký tự.';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $u))        $errors[] = 'Tên đăng nhập chỉ được dùng chữ, số, dấu gạch dưới.';
    if (strlen($p) < 6)                              $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    if ($p !== $p2)                                  $errors[] = 'Mật khẩu nhập lại không khớp.';
    if (!filter_var($e, FILTER_VALIDATE_EMAIL))       $errors[] = 'Email không hợp lệ.';
    if (!preg_match('/^(0|\+84)[0-9]{9}$/', $phone)) $errors[] = 'Số điện thoại không hợp lệ (VD: 0901234567).';

    if (empty($errors)) {
        foreach ([
            ["SELECT 1 FROM khachhang WHERE TenDangNhap=? LIMIT 1", $u,     'Tên đăng nhập đã tồn tại.'],
            ["SELECT 1 FROM khachhang WHERE Email=? LIMIT 1",        $e,     'Email này đã được đăng ký.'],
            ["SELECT 1 FROM khachhang WHERE SoDienThoai=? LIMIT 1",  $phone, 'Số điện thoại đã được đăng ký.'],
        ] as [$sql, $val, $msg]) {
            $st = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($st, 's', $val);
            mysqli_stmt_execute($st);
            if (mysqli_num_rows(mysqli_stmt_get_result($st)) > 0) $errors[] = $msg;
            mysqli_stmt_close($st);
        }
    }

    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    } else {
        // DaXacThuc = 1: kích hoạt ngay, không cần xác thực email
        $ins = mysqli_prepare($conn,
            "INSERT INTO khachhang (TenDangNhap, MatKhau, Email, SoDienThoai, DaXacThuc)
             VALUES (?, ?, ?, ?, 1)");
        mysqli_stmt_bind_param($ins, 'ssssi', $u, $p, $e, $phone, 1);
        if (mysqli_stmt_execute($ins)) {
            $success = true;
        } else {
            $error = 'Lỗi hệ thống: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($ins);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký — Mobile Web</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            padding: 24px 16px;
        }

        .card {
            background: #fff;
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 32px 80px rgba(0,0,0,.4);
        }

        .brand {
            text-align: center;
            margin-bottom: 28px;
        }
        .brand-logo { font-size: 34px; margin-bottom: 8px; }
        .brand h1 { font-size: 22px; font-weight: 800; color: #1a202c; }
        .brand p  { font-size: 13px; color: #718096; margin-top: 4px; }

        /* Success state */
        .success-box {
            text-align: center;
            padding: 12px 0;
        }
        .success-icon { font-size: 56px; margin-bottom: 16px; }
        .success-box h2 { font-size: 22px; font-weight: 800; color: #2ecc71; margin-bottom: 10px; }
        .success-box p  { color: #718096; font-size: 14px; line-height: 1.6; margin-bottom: 24px; }
        .success-box .username {
            background: #f0fff4;
            border: 1px solid #c6f6d5;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 700;
            color: #276749;
            font-size: 16px;
            margin-bottom: 20px;
            display: inline-block;
        }

        /* Error */
        .error-msg {
            background: #fff5f5;
            border: 1px solid #fc8181;
            color: #c53030;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 13px;
            margin-bottom: 18px;
            line-height: 1.6;
        }

        /* Fields */
        .field { margin-bottom: 14px; }
        .field label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 6px;
        }
        .input-wrap { position: relative; }
        .input-icon {
            position: absolute;
            left: 13px; top: 50%;
            transform: translateY(-50%);
            font-size: 16px; color: #a0aec0;
            pointer-events: none;
        }
        .field input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            color: #2d3748;
            background: #f7fafc;
            outline: none;
            transition: all .2s;
        }
        .field input:focus {
            border-color: #e74c3c;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(231,76,60,.1);
        }
        .toggle-btn {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            cursor: pointer; font-size: 16px;
            color: #a0aec0; padding: 4px; line-height: 1;
        }
        .toggle-btn:hover { color: #4a5568; }

        /* Field grid */
        .field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .btn-register {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
            transition: all .2s;
            font-family: inherit;
        }
        .btn-register:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(231,76,60,.35);
        }
        .btn-register:active { transform: translateY(0); }

        .btn-login-link {
            display: block;
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: all .2s;
        }
        .btn-login-link:hover {
            background: linear-gradient(135deg, #2980b9, #2471a3);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(52,152,219,.35);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            color: #cbd5e0;
            font-size: 12px;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px; background: #e2e8f0;
        }

        .footer-links {
            text-align: center;
            font-size: 13px;
            color: #718096;
            margin-top: 16px;
        }
        .footer-links a {
            color: #3498db; font-weight: 600; text-decoration: none;
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
        }
        .back-home a:hover { color: #fff; }
    </style>
</head>
<body>
    <div>
        <div class="card">
            <div class="brand">
                <div class="brand-logo">📝</div>
                <h1>Tạo tài khoản</h1>
                <p>Đăng ký để bắt đầu mua sắm tại Mobile Web</p>
            </div>

            <?php if ($success): ?>
                <div class="success-box">
                    <div class="success-icon">🎉</div>
                    <h2>Đăng ký thành công!</h2>
                    <p>Tài khoản của bạn đã được tạo.<br>Đăng nhập ngay để bắt đầu mua sắm.</p>
                    <div class="username">👤 <?= htmlspecialchars($_POST['user']) ?></div>
                    <a href="login.php" class="btn-login-link">Đăng nhập ngay →</a>
                </div>

            <?php else: ?>

                <?php if ($error): ?>
                    <div class="error-msg">⚠️ <?= $error ?></div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <div class="field">
                        <label>Tên đăng nhập</label>
                        <div class="input-wrap">
                            <span class="input-icon">👤</span>
                            <input type="text" name="user"
                                   value="<?= htmlspecialchars($_POST['user'] ?? '') ?>"
                                   placeholder="Tối thiểu 3 ký tự" required autofocus>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label>Mật khẩu</label>
                            <div class="input-wrap">
                                <span class="input-icon">🔒</span>
                                <input type="password" name="pass" id="pass1"
                                       placeholder="Tối thiểu 6 ký tự" required>
                                <button type="button" class="toggle-btn"
                                        onclick="toggle('pass1',this)">👁</button>
                            </div>
                        </div>
                        <div class="field">
                            <label>Nhập lại</label>
                            <div class="input-wrap">
                                <span class="input-icon">🔒</span>
                                <input type="password" name="pass2" id="pass2"
                                       placeholder="Xác nhận" required>
                                <button type="button" class="toggle-btn"
                                        onclick="toggle('pass2',this)">👁</button>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label>Email</label>
                        <div class="input-wrap">
                            <span class="input-icon">✉️</span>
                            <input type="email" name="email"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   placeholder="example@gmail.com" required>
                        </div>
                    </div>

                    <div class="field">
                        <label>Số điện thoại</label>
                        <div class="input-wrap">
                            <span class="input-icon">📞</span>
                            <input type="tel" name="phone"
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                   placeholder="0901234567" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-register">Tạo tài khoản →</button>
                </form>

                <div class="divider">đã có tài khoản?</div>

                <div style="text-align:center;">
                    <a href="login.php" class="btn-login-link">Đăng nhập</a>
                </div>

            <?php endif; ?>
        </div>

        <div class="back-home">
            <a href="../index.php">← Quay về trang chủ</a>
        </div>
    </div>

    <script>
    function toggle(id, btn) {
        const inp = document.getElementById(id);
        const isPass = inp.type === 'password';
        inp.type = isPass ? 'text' : 'password';
        btn.textContent = isPass ? '🙈' : '👁';
    }
    </script>
</body>
</html>
