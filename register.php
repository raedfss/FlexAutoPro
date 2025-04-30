<?php
session_start();
require_once 'includes/db.php';

$register_error = '';
$register_success = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $username = explode('@', $email)[0]; // استخلاص اسم المستخدم من الإيميل

    if ($password !== $confirm_password) {
        $register_error = "❌ كلمتا المرور غير متطابقتين.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $exists = $stmt->fetchColumn();

            if ($exists) {
                $register_error = "❌ هذا البريد الإلكتروني مسجل مسبقًا.";
            } else {
                // تحديد الدور تلقائيًا بناءً على البريد
                $role = ($email === 'raedfss@hotmail.com') ? 'admin' : 'user';

                $stmt = $pdo->prepare("INSERT INTO users (email, password, username, role) VALUES (:email, :password, :username, :role)");
                $stmt->execute([
                    'email' => $email,
                    'password' => $password,
                    'username' => $username,
                    'role' => $role
                ]);
                $register_success = "✅ تم إنشاء الحساب بنجاح! يمكنك تسجيل الدخول الآن.";
            }
        } catch (PDOException $e) {
            $register_error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إنشاء حساب | FlexAuto</title>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: url('assets/login_bg.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: "Segoe UI", Tahoma, sans-serif;
            color: white;
        }
        header {
            background-color: rgba(0, 0, 0, 0.75);
            padding: 20px;
            text-align: center;
            font-size: 34px;
            font-weight: bold;
            color: #00ffff;
            letter-spacing: 1px;
        }
        .login-box {
            background: rgba(0, 0, 0, 0.6);
            padding: 40px;
            width: 400px;
            margin: 100px auto;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.2);
        }
        .login-box h2 {
            text-align: center;
            margin-bottom: 25px;
        }
        .login-box input[type="email"],
        .login-box input[type="password"],
        .login-box input[type="submit"] {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
        }
        .login-box input[type="submit"] {
            background-color: #1e90ff;
            color: white;
            cursor: pointer;
        }
        .login-box input[type="submit"]:hover {
            background-color: #63b3ed;
        }
        .extra-links {
            margin-top: 20px;
            text-align: center;
        }
        .extra-links a {
            color: #00ffff;
            text-decoration: none;
        }
        .error {
            color: #ff7b7b;
            text-align: center;
            margin-top: 15px;
        }
        .success {
            color: #a0ffb7;
            text-align: center;
            margin-top: 15px;
        }
        footer {
            background-color: rgba(0, 0, 0, 0.8);
            color: #eee;
            text-align: center;
            padding: 20px;
            font-size: 14px;
            margin-top: 40px;
        }
        .footer-highlight {
            font-size: 20px;
            font-weight: bold;
            color: #00ffff;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<header>FlexAuto - نظام ورشة السيارات الذكي</header>

<div class="login-box">
    <h2>تسجيل مستخدم جديد</h2>
    <form method="post" action="register.php">
        <input type="email" name="email" placeholder="البريد الإلكتروني" required>
        <input type="password" name="password" placeholder="كلمة المرور" required>
        <input type="password" name="confirm_password" placeholder="تأكيد كلمة المرور" required>
        <input type="submit" value="تسجيل">
    </form>

    <?php if (!empty($register_error)): ?>
        <div class="error"><?= htmlspecialchars($register_error) ?></div>
    <?php elseif (!empty($register_success)): ?>
        <div class="success"><?= htmlspecialchars($register_success) ?></div>
    <?php endif; ?>

    <div class="extra-links">
        لديك حساب؟ <a href="login.php">سجِّل الدخول</a>
    </div>
</div>

<footer>
    <div class="footer-highlight">ذكاءٌ في الخدمة، سرعةٌ في الاستجابة، جودةٌ بلا حدود.</div>
    <div>Smart service, fast response, unlimited quality.</div>
    <div style="margin-top: 8px;">📧 raedfss@hotmail.com | ☎️ +962796519007</div>
    <div style="margin-top: 5px;">&copy; <?= date('Y') ?> FlexAuto. جميع الحقوق محفوظة.</div>
</footer>

</body>
</html>
