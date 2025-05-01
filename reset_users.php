<?php
/**
 * سكربت إعادة تهيئة المستخدمين
 * يقوم بحذف جميع المستخدمين وإنشاء مجموعة جديدة للمشروع الأكاديمي
 * 
 * تنبيه: هذا الملف خطير ويجب حذفه بعد الاستخدام!
 */

// تشغيل فقط من localhost أو CLI
if (php_sapi_name() !== 'cli' && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    die('يجب تشغيل هذا الملف محليًا فقط!');
}

// عرض نموذج التأكيد
if (!isset($_POST['confirm']) && php_sapi_name() !== 'cli') {
    echo <<<HTML
    <!DOCTYPE html>
    <html dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>إعادة تهيئة المستخدمين</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
            .warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
            .btn { padding: 10px 15px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; }
            h1 { color: #343a40; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>إعادة تهيئة المستخدمين</h1>
            <div class="warning">
                <strong>تحذير!</strong> سيقوم هذا السكربت بحذف جميع المستخدمين الحاليين وإنشاء مجموعة جديدة من المستخدمين للمشروع الأكاديمي.
            </div>
            <p>سيتم إنشاء المستخدمين التاليين:</p>
            <ol>
                <li><strong>المسؤول الرئيسي:</strong> raedfss@hotmail.com</li>
                <li><strong>مستخدم 1:</strong> user1@example.com</li>
                <li><strong>مستخدم 2:</strong> user2@example.com</li>
                <li><strong>الضحية:</strong> victim@example.com</li>
            </ol>
            <form method="post">
                <input type="hidden" name="confirm" value="yes">
                <button type="submit" class="btn">تأكيد وبدء العملية</button>
            </form>
        </div>
    </body>
    </html>
    HTML;
    exit;
}

// اتصال بقاعدة البيانات
require_once 'includes/db.php';

// الآن سنقوم بالعملية
try {
    // بدء المعاملة لضمان التنفيذ الكامل أو الإلغاء الكامل
    $pdo->beginTransaction();
    
    // 1. حذف جميع البيانات المرتبطة
    echo "جاري حذف سجلات النشاط...\n";
    $pdo->exec("DELETE FROM activity_logs");
    
    echo "جاري حذف سجلات تسجيل الدخول...\n";
    $pdo->exec("DELETE FROM login_logs");
    
    // حذف السجلات من الجداول الأخرى التي قد تحتوي على foreign keys
    // يمكن إضافة المزيد حسب هيكل قاعدة البيانات
    echo "جاري حذف البيانات من الجداول المرتبطة...\n";
    $pdo->exec("DELETE FROM airbag_resets"); // مثال - أضف المزيد حسب هيكل قاعدة البيانات
    
    // 2. حذف جميع المستخدمين
    echo "جاري حذف جميع المستخدمين...\n";
    $pdo->exec("DELETE FROM users");
    
    // 3. إنشاء المستخدمين الجدد
    echo "جاري إنشاء المستخدمين الجدد...\n";
    
    // تهيئة المستخدمين
    $users = [
        // المسؤول الرئيسي
        [
            'email' => 'raedfss@hotmail.com',
            'username' => 'admin',
            'password' => 'Admin@123',
            'fullname' => 'مدير النظام',
            'role' => 'admin',
            'is_active' => 1
        ],
        // مستخدم 1
        [
            'email' => 'user1@example.com',
            'username' => 'user1',
            'password' => 'User1@123',
            'fullname' => 'مستخدم تجريبي 1',
            'role' => 'user',
            'is_active' => 1
        ],
        // مستخدم 2
        [
            'email' => 'user2@example.com',
            'username' => 'user2',
            'password' => 'User2@123',
            'fullname' => 'مستخدم تجريبي 2',
            'role' => 'user',
            'is_active' => 1
        ],
        // الضحية
        [
            'email' => 'victim@example.com',
            'username' => 'victim',
            'password' => 'Victim@123',
            'fullname' => 'حساب الضحية',
            'role' => 'user',
            'is_active' => 1
        ]
    ];
    
    // تحضير استعلام الإدخال
    $stmt = $pdo->prepare("
        INSERT INTO users (email, username, password, fullname, role, is_active, created_at) 
        VALUES (:email, :username, :password, :fullname, :role, :is_active, NOW())
    ");
    
    // إدخال كل مستخدم
    foreach ($users as $user) {
        // تشفير كلمة المرور
        $hashed_password = password_hash($user['password'], PASSWORD_DEFAULT);
        
        $stmt->execute([
            'email' => $user['email'],
            'username' => $user['username'],
            'password' => $hashed_password,
            'fullname' => $user['fullname'],
            'role' => $user['role'],
            'is_active' => $user['is_active']
        ]);
        
        echo "تم إنشاء المستخدم: {$user['email']} (كلمة المرور: {$user['password']})\n";
    }
    
    // إتمام العملية
    $pdo->commit();
    
    echo "\n==============================================\n";
    echo "تمت العملية بنجاح!\n";
    echo "تم إنشاء حساب المسؤول: raedfss@hotmail.com (كلمة المرور: Admin@123)\n";
    echo "تم إنشاء 3 حسابات إضافية للمستخدمين\n";
    echo "==============================================\n";
    echo "\nيرجى حذف هذا الملف الآن للحفاظ على أمان النظام!\n";
    
} catch (PDOException $e) {
    // التراجع عن التغييرات في حالة حدوث خطأ
    $pdo->rollBack();
    die("حدث خطأ: " . $e->getMessage());
}

// إنشاء ملف تأكيد لتسجيل الدخول
if (!isset($_SERVER['HTTP_HOST'])) {
    // نحن نعمل في وضع CLI
    exit;
}

// عرض صفحة التأكيد النهائية
echo <<<HTML
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تمت العملية بنجاح</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 8px; border: 1px solid #ddd; }
        .table th { background: #f5f5f5; }
        .btn { padding: 10px 15px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 20px; }
        h1 { color: #343a40; }
    </style>
</head>
<body>
    <div class="container">
        <h1>تمت العملية بنجاح!</h1>
        <div class="success">
            <strong>تم إعادة تهيئة المستخدمين بنجاح.</strong>
        </div>
        <p>تم إنشاء الحسابات التالية:</p>
        <table class="table">
            <thead>
                <tr>
                    <th>البريد الإلكتروني</th>
                    <th>اسم المستخدم</th>
                    <th>كلمة المرور</th>
                    <th>الدور</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>raedfss@hotmail.com</td>
                    <td>admin</td>
                    <td>Admin@123</td>
                    <td>مسؤول</td>
                </tr>
                <tr>
                    <td>user1@example.com</td>
                    <td>user1</td>
                    <td>User1@123</td>
                    <td>مستخدم</td>
                </tr>
                <tr>
                    <td>user2@example.com</td>
                    <td>user2</td>
                    <td>User2@123</td>
                    <td>مستخدم</td>
                </tr>
                <tr>
                    <td>victim@example.com</td>
                    <td>victim</td>
                    <td>Victim@123</td>
                    <td>مستخدم (ضحية)</td>
                </tr>
            </tbody>
        </table>
        
        <p><strong>تحذير:</strong> احذف هذا الملف فورًا لأسباب أمنية!</p>
        <a href="login.php" class="btn">الذهاب لصفحة تسجيل الدخول</a>
    </div>
</body>
</html>
HTML;