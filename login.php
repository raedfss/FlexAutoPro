<?php
session_start();

// تهيئة متغير رسالة الخطأ
$login_error = '';
$success_message = '';

// إذا كان المستخدم مسجل الدخول بالفعل، يتم إعادة توجيهه
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

// التحقق من وجود رسالة مرحلة من صفحة أخرى
if (isset($_SESSION['message'])) {
    $success_message = $_SESSION['message'];
    unset($_SESSION['message']); // مسح الرسالة بعد عرضها
}

// إنشاء توكن CSRF للنموذج
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// استيراد ملف الاتصال بقاعدة البيانات
require_once 'includes/db.php';

// معالجة نموذج تسجيل الدخول
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // التحقق من توكن CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $login_error = "❌ خطأ في التحقق من الأمان. الرجاء المحاولة مرة أخرى.";
    } else {
        // تنظيف المدخلات
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = trim($_POST['password']);
        
        // التحقق من صحة البريد الإلكتروني
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $login_error = "❌ يرجى إدخال بريد إلكتروني صالح.";
        } else {
            try {
                // البحث عن المستخدم في قاعدة البيانات
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // إذا تم العثور على المستخدم، نتحقق من كلمة المرور
                if ($user) {
                    // تحقق مما إذا كان المستخدم محظورًا
                    if (isset($user['is_active']) && $user['is_active'] == 0) {
                        $login_error = "❌ هذا الحساب معطل. يرجى الاتصال بالمسؤول.";
                    } 
                    // تحقق من عدد محاولات تسجيل الدخول الفاشلة
                    elseif (isset($user['login_attempts']) && $user['login_attempts'] >= 5) {
                        // تحقق مما إذا كان الوقت قد انقضى
                        if (isset($user['lockout_time']) && strtotime($user['lockout_time']) > time()) {
                            $login_error = "❌ تم تأمين الحساب مؤقتًا. يرجى المحاولة بعد 15 دقيقة.";
                        } else {
                            // إعادة تعيين محاولات تسجيل الدخول
                            $resetStmt = $pdo->prepare("UPDATE users SET login_attempts = 0, lockout_time = NULL WHERE id = :id");
                            $resetStmt->execute(['id' => $user['id']]);
                        }
                    }
                    // المتابعة للتحقق من كلمة المرور إذا لم يكن الحساب محظورًا
                    if (empty($login_error)) {
                        // إذا كانت كلمة المرور مخزنة بشكل نصي صريح (للتوافق مع النظام القديم)
                        if ($user['password'] === $password) {
                            // تحديث كلمة المرور إلى التجزئة الآمنة
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $updateStmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
                            $updateStmt->execute([
                                'password' => $hashed_password,
                                'id' => $user['id']
                            ]);

                            // تسجيل الدخول الناجح
                            loginSuccess($user);
                        } 
                        // التحقق مما إذا كانت كلمة المرور تستخدم التجزئة (للحسابات المحدثة)
                        elseif (isset($user['password']) && password_verify($password, $user['password'])) {
                            // تسجيل الدخول الناجح
                            loginSuccess($user);
                        } 
                        // كلمة المرور غير صحيحة
                        else {
                            // زيادة عدد محاولات تسجيل الدخول الفاشلة
                            $attempts = ($user['login_attempts'] ?? 0) + 1;
                            $lockout_time = ($attempts >= 5) ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : null;
                            
                            $attemptsStmt = $pdo->prepare("UPDATE users SET login_attempts = :attempts, lockout_time = :lockout_time WHERE id = :id");
                            $attemptsStmt->execute([
                                'attempts' => $attempts,
                                'lockout_time' => $lockout_time,
                                'id' => $user['id']
                            ]);

                            $login_error = "❌ البريد الإلكتروني أو كلمة المرور غير صحيحة.";
                        }
                    }
                } else {
                    // لا يوجد مستخدم بهذا البريد الإلكتروني
                    $login_error = "❌ البريد الإلكتروني أو كلمة المرور غير صحيحة.";
                    
                    // تأخير زمني لمنع هجمات التخمين السريع (تأخير 1 ثانية)
                    sleep(1);
                }
            } catch (PDOException $e) {
                // تسجيل الخطأ في ملف سجل الأخطاء بدلاً من عرضه للمستخدم
                error_log("Database error in login.php: " . $e->getMessage());
                $login_error = "❌ حدث خطأ أثناء محاولة تسجيل الدخول. الرجاء المحاولة مرة أخرى لاحقًا.";
            }
        }
    }
    
    // إنشاء توكن CSRF جديد بعد المحاولة
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $csrf_token = $_SESSION['csrf_token'];
}

/**
 * دالة لمعالجة تسجيل الدخول الناجح
 * @param array $user بيانات المستخدم
 */
function loginSuccess($user) {
    global $pdo;
    
    // إعادة تعيين محاولات تسجيل الدخول
    $resetStmt = $pdo->prepare("UPDATE users SET login_attempts = 0, lockout_time = NULL, last_login = NOW() WHERE id = :id");
    $resetStmt->execute(['id' => $user['id']]);
    
    // تخزين بيانات المستخدم في الجلسة
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['username'] = $user['username'];
    
    // تحديد دور المستخدم
    if ($user['email'] === 'raedfss@hotmail.com' || (isset($user['role']) && $user['role'] === 'admin')) {
        $_SESSION['user_role'] = 'admin';
    } else {
        $_SESSION['user_role'] = $user['role'] ?? 'user';
    }
    
    // إنشاء معرف الجلسة الجديد لمنع هجمات اختطاف الجلسة
    session_regenerate_id(true);
    
    // تسجيل عملية تسجيل الدخول (يجب إنشاء جدول لسجل الدخول)
    try {
        $logStmt = $pdo->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, user_agent) VALUES (:user_id, NOW(), :ip, :agent)");
        $logStmt->execute([
            'user_id' => $user['id'],
            'ip' => $_SERVER['REMOTE_ADDR'],
            'agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (PDOException $e) {
        // في حالة عدم وجود جدول سجل الدخول، نتجاهل الخطأ
        error_log("Login log error: " . $e->getMessage());
    }
    
    // إعادة التوجيه إلى الصفحة الرئيسية
    header("Location: home.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول | FlexAuto</title>
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
            width: 350px;
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
            display: block;
            margin: 8px 0;
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
            color: #7bff7b;
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
        /* تحسينات متجاوبة للهواتف المحمولة */
        @media (max-width: 480px) {
            .login-box {
                width: 85%;
                padding: 20px;
                margin: 50px auto;
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
    <h2>تسجيل الدخول</h2>
    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" autocomplete="off">
        <!-- إضافة توكن CSRF -->
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        
        <input type="email" name="email" placeholder="البريد الإلكتروني" required
               maxlength="100" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        
        <input type="password" name="password" placeholder="كلمة المرور" required
               minlength="8" maxlength="100">
        
        <input type="submit" value="دخول">
    </form>

    <?php if (!empty($login_error)): ?>
        <div class="error"><?php echo $login_error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <div class="extra-links">
        <a href="forgot_password.php">🔒 نسيت كلمة المرور؟</a>
        <a href="register.php">📝 مستخدم جديد؟ إنشاء حساب</a>
    </div>
</div>

<footer>
    <div class="footer-highlight">ذكاءٌ في الخدمة، سرعةٌ في الاستجابة، جودةٌ بلا حدود.</div>
    <div>Smart service, fast response, unlimited quality.</div>
    <div style="margin-top: 8px;">📧 contact@flexauto.com | ☎️ +962796519007</div>
    <div style="margin-top: 5px;">&copy; <?php echo date('Y'); ?> FlexAuto. جميع الحقوق محفوظة.</div>
</footer>

<!-- إضافة سكريبت لتحسين الأمان -->
<script>
    // تعطيل التخزين المؤقت للصفحة لمنع عودة البيانات عند الضغط على زر الرجوع
    window.onpageshow = function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    };
    
    // تعطيل النسخ واللصق لحقل كلمة المرور (حماية إضافية)
    document.querySelector('input[type="password"]').addEventListener('copy', function(e) {
        e.preventDefault();
    });
    document.querySelector('input[type="password"]').addEventListener('paste', function(e) {
        e.preventDefault();
    });
</script>

</body>
</html>