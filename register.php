<?php
session_start();

// تهيئة متغيرات الرسائل
$register_error = '';
$register_success = '';

// إذا كان المستخدم مسجل الدخول بالفعل، يتم إعادة توجيهه
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

// إنشاء توكن CSRF للنموذج
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// استيراد ملف الاتصال بقاعدة البيانات
require_once 'includes/db.php';

// معالجة نموذج التسجيل
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // التحقق من توكن CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $register_error = "❌ خطأ في التحقق من الأمان. الرجاء المحاولة مرة أخرى.";
    } else {
        // تنظيف وتحقق من المدخلات
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        $fullname = filter_var(trim($_POST['fullname'] ?? ''), FILTER_SANITIZE_STRING);
        $phone = filter_var(trim($_POST['phone'] ?? ''), FILTER_SANITIZE_STRING);
        $username = filter_var(explode('@', $email)[0], FILTER_SANITIZE_STRING); // استخلاص اسم المستخدم من الإيميل

        // سلسلة من التحققات
        $errors = [];

        // التحقق من صحة البريد الإلكتروني
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "يرجى إدخال بريد إلكتروني صالح";
        }

        // التحقق من طول كلمة المرور
        if (strlen($password) < 8) {
            $errors[] = "يجب أن تكون كلمة المرور 8 أحرف على الأقل";
        }

        // التحقق من تعقيد كلمة المرور (الحروف الكبيرة والصغيرة والأرقام والرموز)
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
            $errors[] = "يجب أن تحتوي كلمة المرور على حروف كبيرة وصغيرة وأرقام";
        }

        // التحقق من تطابق كلمتي المرور
        if ($password !== $confirm_password) {
            $errors[] = "كلمتا المرور غير متطابقتين";
        }

        // جمع كل الأخطاء
        if (!empty($errors)) {
            $register_error = "❌ " . implode(". ", $errors) . ".";
        } else {
            try {
                // التحقق من وجود البريد الإلكتروني في قاعدة البيانات
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
                $stmt->execute(['email' => $email]);
                $exists = $stmt->fetchColumn();

                if ($exists) {
                    $register_error = "❌ هذا البريد الإلكتروني مسجل مسبقًا.";
                } else {
                    // تشفير كلمة المرور
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // تحديد الدور تلقائيًا بناءً على البريد
                    $role = ($email === 'raedfss@hotmail.com') ? 'admin' : 'user';

                    // إنشاء كود تفعيل (إذا كنت تريد نظام تفعيل عبر البريد)
                    $activation_code = bin2hex(random_bytes(16));
                    $is_active = ($role === 'admin') ? 1 : 0; // تفعيل حساب المسؤول تلقائيًا
                    
                    // إضافة رقم الهاتف الاختياري
                    $phone = !empty($phone) ? $phone : null;
                    
                    // إدخال بيانات المستخدم الجديد في قاعدة البيانات
                    $stmt = $pdo->prepare("
                        INSERT INTO users (email, password, username, fullname, phone, role, activation_code, is_active, created_at) 
                        VALUES (:email, :password, :username, :fullname, :phone, :role, :activation_code, :is_active, NOW())
                    ");
                    
                    $stmt->execute([
                        'email' => $email,
                        'password' => $hashed_password,
                        'username' => $username,
                        'fullname' => $fullname,
                        'phone' => $phone,
                        'role' => $role,
                        'activation_code' => $activation_code,
                        'is_active' => $is_active
                    ]);
                    
                    // الحصول على معرف المستخدم الجديد
                    $user_id = $pdo->lastInsertId();
                    
                    // تسجيل عملية التسجيل
                    $logStmt = $pdo->prepare("
                        INSERT INTO activity_logs (user_id, activity_type, description, ip_address, created_at) 
                        VALUES (:user_id, 'register', 'تم إنشاء حساب جديد', :ip, NOW())
                    ");
                    
                    $logStmt->execute([
                        'user_id' => $user_id,
                        'ip' => $_SERVER['REMOTE_ADDR']
                    ]);
                    
                    // إرسال رسالة نجاح مع تعليمات إضافية
                    if ($is_active) {
                        $register_success = "✅ تم إنشاء الحساب بنجاح! يمكنك تسجيل الدخول الآن.";
                    } else {
                        // هنا يمكنك إضافة كود لإرسال بريد التفعيل
                        $register_success = "✅ تم إنشاء الحساب بنجاح! يرجى مراجعة بريدك الإلكتروني لتفعيل الحساب.";
                        
                        // يمكن إضافة كود لإرسال بريد تفعيل هنا
                        // sendActivationEmail($email, $activation_code);
                    }
                    
                    // إعادة تعيين قيم النموذج بعد التسجيل الناجح
                    $email = $fullname = $phone = '';
                }
            } catch (PDOException $e) {
                // تسجيل الخطأ بشكل آمن
                error_log("Database error in register.php: " . $e->getMessage());
                $register_error = "❌ حدث خطأ أثناء التسجيل. الرجاء المحاولة مرة أخرى لاحقًا.";
            }
        }
    }
    
    // إنشاء توكن CSRF جديد بعد المحاولة
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $csrf_token = $_SESSION['csrf_token'];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب | FlexAuto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            background-color: #1e90ff;
            color: white;
            cursor: pointer;
            margin-top: 20px;
            font-weight: bold;
        }
        .login-box input[type="submit"]:hover {
            background-color: #63b3ed;
        }
        .password-requirements {
            margin-top: 5px;
            font-size: 12px;
            color: #ddd;
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
    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" autocomplete="off" id="registerForm">
        <!-- إضافة توكن CSRF -->
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        
        <input type="email" name="email" placeholder="البريد الإلكتروني *" required
               maxlength="100" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        
        <input type="text" name="fullname" placeholder="الاسم الكامل *" required
               maxlength="100" value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>">
        
        <input type="tel" name="phone" placeholder="رقم الهاتف (اختياري)"
               pattern="[0-9+\-\s]{8,15}" title="يرجى إدخال رقم هاتف صحيح"
               maxlength="15" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
        <span class="optional-label">رقم الهاتف اختياري</span>
        
        <input type="password" name="password" id="password" placeholder="كلمة المرور *" required
               minlength="8" maxlength="100">
        <div class="password-requirements">
            * يجب أن تحتوي كلمة المرور على 8 أحرف على الأقل، وتشمل حرف كبير وحرف صغير ورقم واحد على الأقل.
        </div>
        
        <input type="password" name="confirm_password" placeholder="تأكيد كلمة المرور *" required
               minlength="8" maxlength="100">
        
        <input type="submit" value="تسجيل">
    </form>

    <?php if (!empty($register_error)): ?>
        <div class="error"><?php echo $register_error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($register_success)): ?>
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

<!-- إضافة سكريبت للتحقق من كلمة المرور في جانب العميل -->
<script>
    // التحقق من جودة كلمة المرور
    document.getElementById("password").addEventListener("input", function() {
        const password = this.value;
        const requirements = document.querySelector(".password-requirements");
        
        // تحقق من قوة كلمة المرور
        const hasUpperCase = /[A-Z]/.test(password);
        const hasLowerCase = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const isLongEnough = password.length >= 8;
        
        // تغيير لون متطلبات كلمة المرور
        if (isLongEnough && hasUpperCase && hasLowerCase && hasNumber) {
            requirements.style.color = "#a0ffb7"; // أخضر فاتح
        } else {
            requirements.style.color = "#ddd"; // اللون الافتراضي
        }
    });
    
    // التحقق من تطابق كلمتي المرور
    document.getElementById("registerForm").addEventListener("submit", function(event) {
        const password = document.querySelector('input[name="password"]').value;
        const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
        
        if (password !== confirmPassword) {
            event.preventDefault();
            alert("كلمتا المرور غير متطابقتين. يرجى التحقق.");
        }
    });
    
    // تعطيل التخزين المؤقت للصفحة
    window.onpageshow = function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    };
</script>

</body>
</html>