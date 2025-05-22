<?php
session_start();
require_once 'includes/db.php';

$register_error = '';
$register_success = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // تنظيف وتحقق من المدخلات
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // استبدال FILTER_SANITIZE_STRING بـ htmlspecialchars
    $fname = isset($_POST['fullname']) ? htmlspecialchars(trim($_POST['fullname']), ENT_QUOTES, 'UTF-8') : '';
    $flname = isset($_POST['fullname']) ? htmlspecialchars(trim($_POST['fullname']), ENT_QUOTES, 'UTF-8') : '';
    $fullname = $fname . $flname
    $phone = isset($_POST['phone']) ? htmlspecialchars(trim($_POST['phone']), ENT_QUOTES, 'UTF-8') : '';
    $username = htmlspecialchars(explode('@', $email)[0], ENT_QUOTES, 'UTF-8');

    if ($password !== $confirm_password) {
        $register_error = "❌ كلمتا المرور غير متطابقتين.";
    } elseif (strlen($password) < 8) {
        $register_error = "❌ يجب أن تكون كلمة المرور 8 أحرف على الأقل.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = "❌ البريد الإلكتروني غير صالح.";
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
                
                // تشفير كلمة المرور بدلاً من تخزينها كنص صريح
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // تحضير استعلام الإدخال بناءً على الأعمدة المتاحة
                // التحقق من وجود الأعمدة الإضافية
                $columnsQuery = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'users'");
                $columns = [];
                while ($column = $columnsQuery->fetch(PDO::FETCH_ASSOC)) {
                    $columns[] = strtolower($column['column_name']);
                }
                
                // بناء استعلام ديناميكي
                $fields = ['email', 'username', 'password', 'role'];
                $values = [':email', ':username', ':password', ':role'];
                $params = [
                    ':email' => $email,
                    ':username' => $username,
                    ':password' => $hashed_password,
                    ':role' => $role
                ];
                
                // إضافة الحقول الإضافية حسب الحاجة
                if (in_array('fullname', $columns) && !empty($fullname)) {
                    $fields[] = 'fullname';
                    $values[] = ':fullname';
                    $params[':fullname'] = $fullname;
                }
                
                if (in_array('phone', $columns) && !empty($phone)) {
                    $fields[] = 'phone';
                    $values[] = ':phone';
                    $params[':phone'] = $phone;
                }
                
                if (in_array('is_active', $columns)) {
                    $fields[] = 'is_active';
                    $values[] = ':is_active';
                    $params[':is_active'] = 1; // تفعيل الحساب مباشرة
                }
                
                if (in_array('created_at', $columns)) {
                    $fields[] = 'created_at';
                    $values[] = 'NOW()';
                }
                
                // إنشاء استعلام SQL
                $sql = "INSERT INTO users (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $register_success = "✅ تم إنشاء الحساب بنجاح! يمكنك تسجيل الدخول الآن.";
                
                // إعادة التوجيه إلى صفحة تسجيل الدخول بعد نجاح التسجيل
                $_SESSION['message'] = $register_success;
                header("Location: login.php");
                exit;
            }
        } catch (PDOException $e) {
            error_log("خطأ في قاعدة البيانات: " . $e->getMessage());
            $register_error = "❌ حدث خطأ أثناء التسجيل. الرجاء المحاولة مرة أخرى لاحقًا.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            margin: 50px auto;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.2);
        }
        .login-box h2 {
            text-align: center;
            margin-bottom: 25px;
        }
        .login-box input[type="text"],
        .login-box input[type="email"],
        .login-box input[type="password"],
        .login-box input[type="tel"],
        .login-box input[type="submit"] {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            box-sizing: border-box;
        }
        .login-box input[type="submit"] {
            background-color:rgb(255, 30, 30);
            color: white;
            cursor: pointer;
            margin-top: 20px;
        }
        .login-box input[type="submit"]:hover {
            background-color: #63b3ed;
        }
        .password-requirements {
            font-size: 12px;
            color: #ddd;
            margin-top: 5px;
            padding-right: 10px;
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
            padding: 10px;
            background-color: rgba(255, 0, 0, 0.1);
            border-radius: 5px;
        }
        .success {
            color: #a0ffb7;
            text-align: center;
            margin-top: 15px;
            padding: 10px;
            background-color: rgba(0, 255, 0, 0.1);
            border-radius: 5px;
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
        .optional-label {
            color: #aaa;
            font-size: 12px;
        }
        /* تحسينات متجاوبة للهواتف المحمولة */
        @media (max-width: 480px) {
            .login-box {
                width: 85%;
                padding: 20px;
                margin: 30px auto;
            }
            header {
                font-size: 24px;
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<header>FlexAuto - نظام ورشة السيارات الذكي</header>

<div class="login-box">
    <h2>تسجيل مستخدم جديد</h2>
    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="registerForm">
        <input type="email" name="email" placeholder="البريد الإلكتروني *" required
               maxlength="150" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        
        <input type="text" name="fullname" placeholder="الاسم الكامل *" required
               maxlength="150" value="<?php echo isset($_POST['lname']) ? htmlspecialchars($_POST['fname']) : ''; ?>">
        <input type="text" name="fullname" placeholder="الاسم الكامل *" required
               maxlength="150" value="<?php echo isset($_POST['fname']) ? htmlspecialchars($_POST['lname']) : ''; ?>">
        
        <input type="tel" name="phone" placeholder="رقم الهاتف (اختياري)"
               pattern="[0-9+\-\s]{8,15}" title="يرجى إدخال رقم هاتف صحيح"
               maxlength="20" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
        <span class="optional-label">رقم الهاتف اختياري</span>
        
        <input type="password" name="password" id="password" placeholder="كلمة المرور *" required
               minlength="8" maxlength="255">
        <div class="password-requirements">
            * يجب أن تحتوي كلمة المرور على 8 أحرف على الأقل.
        </div>
        
        <input type="password" name="confirm_password" placeholder="تأكيد كلمة المرور *" required
               minlength="8" maxlength="255">
        
        <input type="submit" value="تسجيل">
    </form>

    <?php if (!empty($register_error)): ?>
        <div class="error"><?php echo $register_error; ?></div>
    <?php elseif (!empty($register_success)): ?>
        <div class="success"><?php echo htmlspecialchars($register_success); ?></div>
    <?php endif; ?>

    <div class="extra-links">
        لديك حساب؟ <a href="login.php">سجِّل الدخول</a>
    </div>
</div>

<footer>
    <div class="footer-highlight">ذكاءٌ في الخدمة، سرعةٌ في الاستجابة، جودةٌ بلا حدود.</div>
    <div>Smart service, fast response, unlimited quality.</div>
    <div style="margin-top: 8px;">📧 contact@flexauto.com | ☎️ +962796519007</div>
    <div style="margin-top: 5px;">&copy; <?php echo date('Y'); ?> FlexAuto. جميع الحقوق محفوظة.</div>
</footer>

<!-- التحقق من التسجيل في جانب العميل -->
<script>
    // التحقق من تطابق كلمات المرور
    document.getElementById("registerForm").addEventListener("submit", function(event) {
        const password = document.querySelector('input[name="password"]').value;
        const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
        
        if (password !== confirmPassword) {
            event.preventDefault();
            alert("كلمتا المرور غير متطابقتين. يرجى التحقق.");
        }
        
        if (password.length < 8) {
            event.preventDefault();
            alert("يجب أن تكون كلمة المرور 8 أحرف على الأقل.");
        }
    });
</script>

</body>
</html>