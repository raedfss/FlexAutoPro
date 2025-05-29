<?php
/**
 * صفحة تسجيل المستخدمين الجدد - نظام FlexAuto لإدارة ورش السيارات
 * هذا الملف يتعامل مع تسجيل المستخدمين الجدد في النظام
 * يتضمن التحقق من صحة البيانات وحفظها في قاعدة البيانات
 */

// بدء جلسة PHP لحفظ بيانات المستخدم أثناء التصفح
session_start();

// تضمين ملف الاتصال بقاعدة البيانات
require_once 'includes/db.php';

// متغيرات لحفظ رسائل الخطأ والنجاح
$register_error = '';
$register_success = '';

// التحقق من أن الطلب تم إرساله عبر طريقة POST (عند إرسال النموذج)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // ===============================
    // قسم تنظيف وتحقق من البيانات المدخلة
    // ===============================
    
    // تنظيف البريد الإلكتروني والتحقق من صحته
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    
    // الحصول على كلمات المرور وإزالة المسافات الزائدة
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // تنظيف بيانات الأسماء وحمايتها من الهجمات
    $first_name = isset($_POST['first_name']) ? htmlspecialchars(trim($_POST['first_name']), ENT_QUOTES, 'UTF-8') : '';
    $middle_name = isset($_POST['middle_name']) ? htmlspecialchars(trim($_POST['middle_name']), ENT_QUOTES, 'UTF-8') : '';
    $last_name = isset($_POST['last_name']) ? htmlspecialchars(trim($_POST['last_name']), ENT_QUOTES, 'UTF-8') : '';
    $nickname = isset($_POST['nickname']) ? htmlspecialchars(trim($_POST['nickname']), ENT_QUOTES, 'UTF-8') : '';
    
    // تنظيف رقم الهاتف
    $phone = isset($_POST['phone']) ? htmlspecialchars(trim($_POST['phone']), ENT_QUOTES, 'UTF-8') : '';
    
    // إنشاء الاسم الكامل من الأسماء المدخلة
    $full_name_parts = array_filter([$first_name, $middle_name, $last_name]);
    $fullname = implode(' ', $full_name_parts);
    
    // إنشاء اسم المستخدم من الجزء الأول للبريد الإلكتروني
    $email_parts = explode('@', $email);
    $username = htmlspecialchars($email_parts[0], ENT_QUOTES, 'UTF-8');

    // ===============================
    // قسم التحقق من صحة البيانات
    // ===============================
    
    // التحقق من تطابق كلمتي المرور
    if ($password !== $confirm_password) {
        $register_error = "❌ كلمتا المرور غير متطابقتين.";
    } 
    // التحقق من طول كلمة المرور (يجب أن تكون 8 أحرف على الأقل)
    elseif (strlen($password) < 8) {
        $register_error = "❌ يجب أن تكون كلمة المرور 8 أحرف على الأقل.";
    } 
    // التحقق من صحة تنسيق البريد الإلكتروني
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = "❌ البريد الإلكتروني غير صالح.";
    }
    // التحقق من وجود الاسم الأول (إلزامي)
    elseif (empty($first_name)) {
        $register_error = "❌ الاسم الأول مطلوب.";
    }
    // التحقق من وجود اسم العائلة (إلزامي)
    elseif (empty($last_name)) {
        $register_error = "❌ اسم العائلة مطلوب.";
    }
    // إذا نجحت جميع عمليات التحقق، نبدأ بحفظ البيانات
    else {
        try {
            // ===============================
            // قسم التحقق من وجود البريد الإلكتروني مسبقاً
            // ===============================
            
            // إعداد استعلام للتحقق من وجود البريد الإلكتروني في قاعدة البيانات
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $exists = $stmt->fetchColumn();

            // إذا كان البريد موجود مسبقاً، عرض رسالة خطأ
            if ($exists) {
                $register_error = "❌ هذا البريد الإلكتروني مسجل مسبقًا.";
            } else {
                
                // ===============================
                // قسم إعداد بيانات المستخدم الجديد
                // ===============================
                
                // تحديد دور المستخدم (مدير أو مستخدم عادي)
                $role = ($email === 'raedfss@hotmail.com') ? 'admin' : 'user';
                
                // تشفير كلمة المرور لحمايتها (لا نحفظها كنص صريح)
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // ===============================
                // قسم فحص هيكل جدول قاعدة البيانات
                // ===============================
                
                // استعلام لمعرفة الأعمدة المتاحة في جدول المستخدمين
                $columnsQuery = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'users'");
                $columns = [];
                
                // حفظ أسماء الأعمدة في مصفوفة للمقارنة
                while ($column = $columnsQuery->fetch(PDO::FETCH_ASSOC)) {
                    $columns[] = strtolower($column['column_name']);
                }
                
                // ===============================
                // قسم بناء استعلام الإدراج الديناميكي
                // ===============================
                
                // الحقول الأساسية المطلوبة في كل حالة
                $fields = ['email', 'username', 'password', 'role'];
                $values = [':email', ':username', ':password', ':role'];
                $params = [
                    ':email' => $email,
                    ':username' => $username,
                    ':password' => $hashed_password,
                    ':role' => $role
                ];
                
                // إضافة الاسم الكامل إذا كان العمود موجود
                if (in_array('fullname', $columns) && !empty($fullname)) {
                    $fields[] = 'fullname';
                    $values[] = ':fullname';
                    $params[':fullname'] = $fullname;
                }
                
                // إضافة الاسم الأول إذا كان العمود موجود
                if (in_array('first_name', $columns) && !empty($first_name)) {
                    $fields[] = 'first_name';
                    $values[] = ':first_name';
                    $params[':first_name'] = $first_name;
                }
                
                // إضافة الاسم الأوسط إذا كان العمود موجود (اختياري)
                if (in_array('middle_name', $columns) && !empty($middle_name)) {
                    $fields[] = 'middle_name';
                    $values[] = ':middle_name';
                    $params[':middle_name'] = $middle_name;
                }
                
                // إضافة اسم العائلة إذا كان العمود موجود
                if (in_array('last_name', $columns) && !empty($last_name)) {
                    $fields[] = 'last_name';
                    $values[] = ':last_name';
                    $params[':last_name'] = $last_name;
                }
                
                // إضافة اسم الشهرة إذا كان العمود موجود (اختياري)
                if (in_array('nickname', $columns) && !empty($nickname)) {
                    $fields[] = 'nickname';
                    $values[] = ':nickname';
                    $params[':nickname'] = $nickname;
                }
                
                // إضافة رقم الهاتف إذا كان العمود موجود ومُدخل
                if (in_array('phone', $columns) && !empty($phone)) {
                    $fields[] = 'phone';
                    $values[] = ':phone';
                    $params[':phone'] = $phone;
                }
                
                // تفعيل الحساب تلقائياً إذا كان العمود موجود
                if (in_array('is_active', $columns)) {
                    $fields[] = 'is_active';
                    $values[] = ':is_active';
                    $params[':is_active'] = 1;
                }
                
                // إضافة تاريخ الإنشاء التلقائي إذا كان العمود موجود
                if (in_array('created_at', $columns)) {
                    $fields[] = 'created_at';
                    $values[] = 'NOW()';
                }
                
                // ===============================
                // قسم تنفيذ استعلام حفظ البيانات
                // ===============================
                
                // بناء استعلام SQL الديناميكي
                $sql = "INSERT INTO users (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";
                
                // تحضير وتنفيذ الاستعلام
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                // رسالة النجاح
                $register_success = "✅ تم إنشاء الحساب بنجاح! يمكنك تسجيل الدخول الآن.";
                
                // حفظ رسالة النجاح في الجلسة للعرض في الصفحة التالية
                $_SESSION['message'] = $register_success;
                
                // إعادة التوجيه إلى صفحة تسجيل الدخول
                header("Location: login.php");
                exit;
            }
        } catch (PDOException $e) {
            // في حالة حدوث خطأ في قاعدة البيانات
            // تسجيل الخطأ في ملف السجل وعرض رسالة عامة للمستخدم
            error_log("خطأ في قاعدة البيانات أثناء التسجيل: " . $e->getMessage());
            $register_error = "❌ حدث خطأ أثناء التسجيل. الرجاء المحاولة مرة أخرى لاحقًا.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <!-- ===============================
         قسم إعدادات الصفحة الأساسية
         =============================== -->
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="تسجيل مستخدم جديد في نظام FlexAuto لإدارة ورش السيارات">
    <meta name="keywords" content="تسجيل, ورشة سيارات, FlexAuto, نظام إدارة">
    <meta name="author" content="FlexAuto Team">
    
    <title>إنشاء حساب جديد | FlexAuto - نظام إدارة ورش السيارات</title>
    
    <!-- تحميل أيقونات Font Awesome من CDN موثوق -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" 
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" 
          crossorigin="anonymous">
    
    <!-- أنماط CSS المحسنة -->
    <style>
        /* ===============================
           إعدادات عامة للصفحة
           =============================== */
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: "Segoe UI", "Cairo", Tahoma, Arial, sans-serif;
            background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.6)), 
                        url('assets/login_bg.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #ffffff;
            line-height: 1.6;
            min-height: 100vh;
        }
        
        /* ===============================
           تصميم الرأس (Header)
           =============================== */
        
        header {
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.6));
            padding: 20px;
            text-align: center;
            font-size: clamp(24px, 5vw, 34px);
            font-weight: 700;
            color: #00ffff;
            letter-spacing: 1px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
        }
        
        /* ===============================
           صندوق التسجيل الرئيسي
           =============================== */
        
        .register-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 200px);
            padding: 20px;
        }
        
        .register-box {
            background: rgba(0, 0, 0, 0.75);
            padding: 40px;
            width: 100%;
            max-width: 500px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .register-box h2 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
            color: #00ffff;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        }
        
        /* ===============================
           تصميم النموذج والحقول
           =============================== */
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #e0e0e0;
            font-size: 14px;
        }
        
        .required {
            color: #ff6b6b;
        }
        
        .optional {
            color: #aaa;
            font-size: 12px;
            font-weight: normal;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"] {
            width: 100%;
            padding: 15px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            font-size: 16px;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="tel"]:focus {
            outline: none;
            border-color: #00ffff;
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.3);
        }
        
        input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        /* ===============================
           زر التسجيل
           =============================== */
        
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #1e90ff, #00bfff);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .submit-btn:hover {
            background: linear-gradient(135deg, #63b3ed, #4da6d9);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 144, 255, 0.4);
        }
        
        .submit-btn:active {
            transform: translateY(0);
        }
        
        /* ===============================
           رسائل التنبيه والأخطاء
           =============================== */
        
        .password-requirements {
            font-size: 12px;
            color: #ddd;
            margin-top: 8px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 5px;
            border-left: 3px solid #00ffff;
        }
        
        .error {
            color: #ff7b7b;
            text-align: center;
            margin: 15px 0;
            padding: 15px;
            background: rgba(255, 0, 0, 0.1);
            border: 1px solid rgba(255, 123, 123, 0.3);
            border-radius: 8px;
            font-weight: 500;
        }
        
        .success {
            color: #a0ffb7;
            text-align: center;
            margin: 15px 0;
            padding: 15px;
            background: rgba(0, 255, 0, 0.1);
            border: 1px solid rgba(160, 255, 183, 0.3);
            border-radius: 8px;
            font-weight: 500;
        }
        
        /* ===============================
           الروابط الإضافية
           =============================== */
        
        .extra-links {
            margin-top: 25px;
            text-align: center;
            font-size: 16px;
        }
        
        .extra-links a {
            color: #00ffff;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .extra-links a:hover {
            color: #63b3ed;
            text-decoration: underline;
        }
        
        /* ===============================
           تصميم الفوتر
           =============================== */
        
        footer {
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.9), rgba(0, 0, 0, 0.7));
            color: #eee;
            text-align: center;
            padding: 30px 20px;
            font-size: 14px;
            margin-top: 40px;
            backdrop-filter: blur(10px);
        }
        
        .footer-highlight {
            font-size: 20px;
            font-weight: 700;
            color: #00ffff;
            margin-bottom: 10px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        }
        
        /* ===============================
           التصميم المتجاوب للأجهزة المحمولة
           =============================== */
        
        @media (max-width: 768px) {
            .register-box {
                padding: 30px 20px;
                margin: 20px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            header {
                padding: 15px;
            }
            
            .register-box h2 {
                font-size: 24px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                font-size: 14px;
            }
            
            .register-box {
                padding: 20px 15px;
            }
            
            input[type="text"],
            input[type="email"],
            input[type="password"],
            input[type="tel"] {
                padding: 12px;
                font-size: 16px; /* منع التكبير التلقائي في iOS */
            }
            
            .submit-btn {
                padding: 12px;
                font-size: 16px;
            }
        }
        
        /* ===============================
           تحسينات إضافية للأداء
           =============================== */
        
        /* تحسين الرسوم المتحركة */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
        
        /* تحسين الوضع المظلم */
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #000;
            }
        }
    </style>
</head>

<body>
    <!-- ===============================
         رأس الصفحة
         =============================== -->
    
    <header>
        FlexAuto - نظام ورشة السيارات الذكي
    </header>

    <!-- ===============================
         صندوق التسجيل الرئيسي
         =============================== -->
    
    <div class="register-container">
        <div class="register-box">
            <h2>
                تسجيل مستخدم جديد
            </h2>
            
            <!-- نموذج التسجيل -->
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" 
                  id="registerForm" novalidate>
                
                <!-- البريد الإلكتروني -->
                <div class="form-group">
                    <label for="email">
                        البريد الإلكتروني <span class="required">*</span>
                    </label>
                    <input type="email" name="email" id="email" 
                           placeholder="example@domain.com" required
                           maxlength="150" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           autocomplete="email">
                </div>
                
                <!-- صف الأسماء الأول -->
                <div class="form-row">
                    <!-- الاسم الأول -->
                    <div class="form-group">
                        <label for="first_name">
                            الاسم الأول <span class="required">*</span>
                        </label>
                        <input type="text" name="first_name" id="first_name" 
                               placeholder="الاسم الأول" required
                               maxlength="50"
                               value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>"
                               autocomplete="given-name">
                    </div>
                    
                    <!-- الاسم الأوسط -->
                    <div class="form-group">
                        <label for="middle_name">
                            الاسم الأوسط <span class="optional">(اختياري)</span>
                        </label>
                        <input type="text" name="middle_name" id="middle_name" 
                               placeholder="الاسم الأوسط"
                               maxlength="50"
                               value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>"
                               autocomplete="additional-name">
                    </div>
                </div>
                
                <!-- صف الأسماء الثاني -->
                <div class="form-row">
                    <!-- اسم العائلة -->
                    <div class="form-group">
                        <label for="last_name">
                            اسم العائلة <span class="required">*</span>
                        </label>
                        <input type="text" name="last_name" id="last_name" 
                               placeholder="اسم العائلة" required
                               maxlength="50"
                               value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>"
                               autocomplete="family-name">
                    </div>
                    
                    <!-- اسم الشهرة -->
                    <div class="form-group">
                        <label for="nickname">
                            اسم الشهرة <span class="optional">(اختياري)</span>
                        </label>
                        <input type="text" name="nickname" id="nickname" 
                               placeholder="اسم الشهرة"
                               maxlength="50"
                               value="<?php echo isset($_POST['nickname']) ? htmlspecialchars($_POST['nickname']) : ''; ?>"
                               autocomplete="nickname">
                    </div>
                </div>
                
                <!-- رقم الهاتف -->
                <div class="form-group">
                    <label for="phone">
                        رقم الهاتف <span class="optional">(اختياري)</span>
                    </label>
                    <input type="tel" name="phone" id="phone" 
                           placeholder="+962 79 XXX XXXX"
                           pattern="[0-9+\-\s]{8,20}" 
                           title="يرجى إدخال رقم هاتف صحيح"
                           maxlength="20"
                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                           autocomplete="tel">
                </div>
                
                <!-- كلمة المرور -->
                <div class="form-group">
                    <label for="password">
                        كلمة المرور <span class="required">*</span>
                    </label>
                    <input type="password" name="password" id="password" 
                           placeholder="كلمة المرور" required
                           minlength="8" maxlength="255"
                           autocomplete="new-password">
                    <div class="password-requirements">
                        يجب أن تحتوي كلمة المرور على 8 أحرف على الأقل لضمان الأمان
                    </div>
                </div>
                
                <!-- تأكيد كلمة المرور -->
                <div class="form-group">
                    <label for="confirm_password">
                        تأكيد كلمة المرور <span class="required">*</span>
                    </label>
                    <input type="password" name="confirm_password" id="confirm_password" 
                           placeholder="أعد كتابة كلمة المرور" required
                           minlength="8" maxlength="255"
                           autocomplete="new-password">
                </div>
                
                <!-- زر التسجيل -->
                <button type="submit" class="submit-btn">
                    إنشاء الحساب
                </button>
            </form>

            <!-- عرض رسائل الخطأ والنجاح -->
            <?php if (!empty($register_error)): ?>
                <div class="error">
                    <?php echo $register_error; ?>
                </div>
            <?php elseif (!empty($register_success)): ?>
                <div class="success">
                    <?php echo htmlspecialchars($register_success); ?>
                </div>
            <?php endif; ?>

            <!-- روابط إضافية -->
            <div class="extra-links">
                لديك حساب بالفعل؟ 
                <a href="login.php">
                    سجِّل الدخول
                </a>
            </div>
        </div>
    </div>

    <!-- ===============================
         فوتر الصفحة
         =============================== -->
    
    <footer>
        <div class="footer-highlight">
            ذكاءٌ في الخدمة، سرعةٌ في الاستجابة، جودةٌ بلا حدود
        </div>
        <div>
            Smart service, fast response, unlimited quality
        </div>
        <div style="margin-top: 10px;">
            📧 contact@flexauto.com | ☎️ +962796519007
        </div>
        <div style="margin-top: 8px;">
            &copy; <?php echo date('Y'); ?> FlexAuto. جميع الحقوق محفوظة.
        </div>
    </footer>

    <!-- ===============================
         جافا سكريبت للتحقق من البيانات
         =============================== -->
    
    <script>
        /**
         * وظائف التحقق من صحة البيانات في جانب العميل
         * هذه الوظائف تعمل قبل إرسال النموذج لتوفير تجربة مستخدم أفضل
         */
        
        // الحصول على عناصر النموذج
        const registerForm = document.getElementById('registerForm');
        const passwordField = document.querySelector('input[name="password"]');
        const confirmPasswordField = document.querySelector('input[name="confirm_password"]');
        const firstNameField = document.querySelector('input[name="first_name"]');
        const lastNameField = document.querySelector('input[name="last_name"]');
        const emailField = document.querySelector('input[name="email"]');
        
        /**
         * وظيفة التحقق من صحة البيانات عند إرسال النموذج
         */
        registerForm.addEventListener('submit', function(event) {
            let isValid = true;
            let errorMessage = '';
            
            // التحقق من الاسم الأول
            if (firstNameField.value.trim() === '') {
                isValid = false;
                errorMessage = 'الاسم الأول مطلوب';
                firstNameField.focus();
            }
            // التحقق من اسم العائلة
            else if (lastNameField.value.trim() === '') {
                isValid = false;
                errorMessage = 'اسم العائلة مطلوب';
                lastNameField.focus();
            }
            // التحقق من البريد الإلكتروني
            else if (!isValidEmail(emailField.value)) {
                isValid = false;
                errorMessage = 'يرجى إدخال بريد إلكتروني صحيح';
                emailField.focus();
            }
            // التحقق من طول كلمة المرور
            else if (passwordField.value.length < 8) {
                isValid = false;
                errorMessage = 'يجب أن تكون كلمة المرور 8 أحرف على الأقل';
                passwordField.focus();
            }
            // التحقق من تطابق كلمتي المرور
            else if (passwordField.value !== confirmPasswordField.value) {
                isValid = false;
                errorMessage = 'كلمتا المرور غير متطابقتين';
                confirmPasswordField.focus();
            }
            
            // منع إرسال النموذج إذا كانت البيانات غير صحيحة
            if (!isValid) {
                event.preventDefault();
                showErrorMessage(errorMessage);
            }
        });
        
        /**
         * وظيفة التحقق من صحة البريد الإلكتروني
         */
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
        
        /**
         * وظيفة عرض رسالة خطأ للمستخدم
         */
        function showErrorMessage(message) {
            // البحث عن رسالة خطأ موجودة وإزالتها
            const existingError = document.querySelector('.client-error');
            if (existingError) {
                existingError.remove();
            }
            
            // إنشاء عنصر رسالة خطأ جديد
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error client-error';
            errorDiv.innerHTML = message;
            
            // إدراج رسالة الخطأ بعد النموذج
            registerForm.insertAdjacentElement('afterend', errorDiv);
            
            // إزالة رسالة الخطأ تلقائياً بعد 5 ثواني
            setTimeout(() => {
                errorDiv.remove();
            }, 5000);
        }
        
        /**
         * التحقق الفوري من تطابق كلمتي المرور أثناء الكتابة
         */
        confirmPasswordField.addEventListener('input', function() {
            const password = passwordField.value;
            const confirmPassword = this.value;
            
            // تغيير لون الحدود حسب التطابق
            if (confirmPassword === '') {
                this.style.borderColor = 'rgba(255, 255, 255, 0.2)';
            } else if (password === confirmPassword) {
                this.style.borderColor = '#4CAF50'; // أخضر للتطابق
            } else {
                this.style.borderColor = '#f44336'; // أحمر لعدم التطابق
            }
        });
        
        /**
         * التحقق من قوة كلمة المرور أثناء الكتابة
         */
        passwordField.addEventListener('input', function() {
            const password = this.value;
            const length = password.length;
            
            // تغيير لون الحدود حسب قوة كلمة المرور
            if (length === 0) {
                this.style.borderColor = 'rgba(255, 255, 255, 0.2)';
            } else if (length < 8) {
                this.style.borderColor = '#ff9800'; // برتقالي للضعيف
            } else {
                this.style.borderColor = '#4CAF50'; // أخضر للقوي
            }
        });
        
        /**
         * تحسين تجربة المستخدم على الأجهزة المحمولة
         */
        
        // منع التكبير التلقائي عند التركيز على الحقول في iOS
        if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.fontSize = '16px';
                });
            });
        }
        
        /**
         * تحسين الأداء - تحميل البيانات المحفوظة محلياً
         */
        
        // حفظ البيانات تلقائياً أثناء الكتابة (ما عدا كلمات المرور)
        const fieldsToSave = ['email', 'first_name', 'middle_name', 'last_name', 'nickname', 'phone'];
        
        fieldsToSave.forEach(fieldName => {
            const field = document.querySelector(`input[name="${fieldName}"]`);
            if (field) {
                // تحميل البيانات المحفوظة
                const savedValue = localStorage.getItem(`flexauto_register_${fieldName}`);
                if (savedValue && !field.value) {
                    field.value = savedValue;
                }
                
                // حفظ البيانات أثناء الكتابة
                field.addEventListener('input', function() {
                    localStorage.setItem(`flexauto_register_${fieldName}`, this.value);
                });
            }
        });
        
        // مسح البيانات المحفوظة عند نجاح التسجيل
        if (document.querySelector('.success')) {
            fieldsToSave.forEach(fieldName => {
                localStorage.removeItem(`flexauto_register_${fieldName}`);
            });
        }
    </script>
</body>
</html>