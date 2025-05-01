<?php
/**
 * سكربت إنشاء هيكل قاعدة البيانات
 * 
 * يقوم بإنشاء جداول النظام وإضافة مستخدم مسؤول
 * 
 * تنبيه: يجب حذف هذا الملف بعد الاستخدام!
 */

// تشغيل فقط من localhost أو CLI
if (php_sapi_name() !== 'cli' && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    die('يجب تشغيل هذا الملف محليًا فقط!');
}

// الاتصال بقاعدة البيانات
require_once 'includes/db.php';

// نموذج التأكيد
if (!isset($_POST['confirm']) && php_sapi_name() !== 'cli') {
    echo <<<HTML
    <!DOCTYPE html>
    <html dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>إنشاء هيكل قاعدة البيانات</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
            .warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
            .danger { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
            .btn { padding: 10px 15px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; margin-top: 10px; }
            h1 { color: #343a40; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>إنشاء هيكل قاعدة البيانات</h1>
            
            <div class="danger">
                <strong>تحذير خطير!</strong> سيقوم هذا السكربت بحذف كل الجداول الموجودة وإنشائها من جديد. جميع البيانات الموجودة سيتم فقدانها بشكل دائم!
            </div>
            
            <div class="warning">
                <strong>ملاحظة:</strong> تأكد من أن إعدادات الاتصال بقاعدة البيانات صحيحة في ملف includes/db.php.
            </div>
            
            <p>سيتم إنشاء الجداول التالية:</p>
            <ol>
                <li><strong>users</strong> - جدول المستخدمين</li>
                <li><strong>login_logs</strong> - سجلات تسجيل الدخول</li>
                <li><strong>activity_logs</strong> - سجلات نشاط المستخدمين</li>
                <li><strong>airbag_resets</strong> - طلبات إعادة ضبط Airbag</li>
            </ol>
            
            <p>سيتم إنشاء المستخدمين التاليين:</p>
            <ol>
                <li><strong>المسؤول:</strong> raedfss@hotmail.com (كلمة المرور: Admin@123)</li>
                <li><strong>مستخدم 1:</strong> user1@example.com (كلمة المرور: User1@123)</li>
                <li><strong>مستخدم 2:</strong> user2@example.com (كلمة المرور: User2@123)</li>
                <li><strong>الضحية:</strong> victim@example.com (كلمة المرور: Victim@123)</li>
            </ol>
            
            <form method="post">
                <input type="hidden" name="confirm" value="yes">
                <button type="submit" class="btn">تأكيد وإنشاء هيكل قاعدة البيانات</button>
            </form>
        </div>
    </body>
    </html>
    HTML;
    exit;
}

try {
    // بدء المعاملة
    $pdo->beginTransaction();
    
    echo "بدء إنشاء هيكل قاعدة البيانات...\n";
    
    // 1. حذف الجداول إذا كانت موجودة
    $tables = [
        'airbag_resets',
        'activity_logs',
        'login_logs',
        'users'
    ];
    
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS {$table} CASCADE");
        echo "تم حذف جدول {$table} (إذا كان موجودًا).\n";
    }
    
    // 2. إنشاء جدول المستخدمين
    $pdo->exec("
        CREATE TABLE users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            fullname VARCHAR(150),
            phone VARCHAR(20),
            role VARCHAR(10) NOT NULL DEFAULT 'user',
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            login_attempts INTEGER DEFAULT 0,
            lockout_time TIMESTAMP NULL,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "تم إنشاء جدول users.\n";
    
    // 3. إنشاء جدول سجلات تسجيل الدخول
    $pdo->exec("
        CREATE TABLE login_logs (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id),
            login_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            user_agent TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'success'
        )
    ");
    echo "تم إنشاء جدول login_logs.\n";
    
    // 4. إنشاء جدول سجلات النشاط
    $pdo->exec("
        CREATE TABLE activity_logs (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id),
            activity_type VARCHAR(50) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "تم إنشاء جدول activity_logs.\n";
    
    // 5. إنشاء جدول طلبات Airbag
    $pdo->exec("
        CREATE TABLE airbag_resets (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id),
            ecu_number VARCHAR(50) NOT NULL,
            vehicle_type VARCHAR(100) NOT NULL,
            uploaded_file VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            notes TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "تم إنشاء جدول airbag_resets.\n";
    
    // 6. إضافة المستخدمين
    $users = [
        // المسؤول
        [
            'email' => 'raedfss@hotmail.com',
            'username' => 'raedfss',
            'password' => 'Admin@123',
            'fullname' => 'مدير النظام',
            'role' => 'admin'
        ],
        // مستخدم 1
        [
            'email' => 'user1@example.com',
            'username' => 'user1',
            'password' => 'User1@123',
            'fullname' => 'مستخدم تجريبي 1',
            'role' => 'user'
        ],
        // مستخدم 2
        [
            'email' => 'user2@example.com',
            'username' => 'user2',
            'password' => 'User2@123',
            'fullname' => 'مستخدم تجريبي 2',
            'role' => 'user'
        ],
        // الضحية
        [
            'email' => 'victim@example.com',
            'username' => 'victim',
            'password' => 'Victim@123',
            'fullname' => 'حساب الضحية',
            'role' => 'user'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO users (email, username, password, fullname, role, created_at, updated_at)
        VALUES (:email, :username, :password, :fullname, :role, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    
    foreach ($users as $user) {
        $hashed_password = password_hash($user['password'], PASSWORD_DEFAULT);
        $stmt->execute([
            'email' => $user['email'],
            'username' => $user['username'],
            'password' => $hashed_password,
            'fullname' => $user['fullname'],
            'role' => $user['role']
        ]);
        echo "تم إنشاء المستخدم: {$user['email']} (كلمة المرور: {$user['password']})\n";
    }
    
    // 7. إضافة بعض سجلات النشاط للاختبار
    $admin_id = $pdo->query("SELECT id FROM users WHERE email = 'raedfss@hotmail.com'")->fetchColumn();
    $activities = [
        ['login', 'تسجيل دخول المستخدم'],
        ['view_dashboard', 'عرض لوحة التحكم'],
        ['update_profile', 'تحديث الملف الشخصي'],
        ['system_config', 'تعديل إعدادات النظام']
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, activity_type, description, ip_address, created_at)
        VALUES (:user_id, :type, :description, '127.0.0.1', CURRENT_TIMESTAMP)
    ");
    
    foreach ($activities as $activity) {
        $stmt->execute([
            'user_id' => $admin_id,
            'type' => $activity[0],
            'description' => $activity[1]
        ]);
    }
    echo "تم إضافة سجلات نشاط تجريبية.\n";
    
    // 8. إضافة سجل تسجيل دخول
    $stmt = $pdo->prepare("
        INSERT INTO login_logs (user_id, login_time, ip_address, user_agent)
        VALUES (:user_id, CURRENT_TIMESTAMP, '127.0.0.1', 'Setup Script')
    ");
    $stmt->execute(['user_id' => $admin_id]);
    echo "تم إضافة سجل تسجيل دخول تجريبي.\n";
    
    // 9. إضافة طلب Airbag تجريبي
    $stmt = $pdo->prepare("
        INSERT INTO airbag_resets (user_id, ecu_number, vehicle_type, uploaded_file, status, created_at, updated_at)
        VALUES (:user_id, 'ECU123456', 'BMW X5 2020', 'sample_file.bin', 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    $stmt->execute(['user_id' => $admin_id]);
    echo "تم إضافة طلب Airbag تجريبي.\n";
    
    // 10. إتمام المعاملة
    $pdo->commit();
    
    echo "\n==============================================\n";
    echo "تم إنشاء هيكل قاعدة البيانات بنجاح!\n";
    echo "==============================================\n";
    echo "تفاصيل حساب المسؤول:\n";
    echo "البريد الإلكتروني: raedfss@hotmail.com\n";
    echo "كلمة المرور: Admin@123\n";
    echo "==============================================\n";
    echo "\nيرجى حذف هذا الملف الآن للحفاظ على أمان النظام!\n";
    
} catch (Exception $e) {
    // التراجع عن التغييرات في حالة حدوث خطأ
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("حدث خطأ: " . $e->getMessage());
}

// عرض صفحة التأكيد
if (!isset($_SERVER['HTTP_HOST'])) {
    // نحن نعمل في وضع CLI
    exit;
}

echo <<<HTML
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تم إنشاء هيكل قاعدة البيانات بنجاح</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin: 20px 0; }
        .info { background: #e3f2fd; color: #0c5460; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .btn { padding: 10px 15px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 20px; }
        h1, h2 { color: #343a40; }
        .table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .table th, .table td { padding: 8px; border: 1px solid #ddd; text-align: right; }
        .table th { background: #f5f5f5; }
    </style>
</head>
<body>
    <div class="container">
        <h1>تم إنشاء هيكل قاعدة البيانات بنجاح!</h1>
        
        <div class="success">
            <strong>تم إنشاء جميع الجداول وإدخال البيانات الأساسية بنجاح.</strong>
        </div>
        
        <h2>الجداول التي تم إنشاؤها:</h2>
        <ul>
            <li><strong>users</strong> - جدول المستخدمين</li>
            <li><strong>login_logs</strong> - سجلات تسجيل الدخول</li>
            <li><strong>activity_logs</strong> - سجلات نشاط المستخدمين</li>
            <li><strong>airbag_resets</strong> - طلبات إعادة ضبط Airbag</li>
        </ul>
        
        <h2>المستخدمون الذين تم إنشاؤهم:</h2>
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
                    <td>raedfss</td>
                    <td>Admin@123</td>
                    <td>admin</td>
                </tr>
                <tr>
                    <td>user1@example.com</td>
                    <td>user1</td>
                    <td>User1@123</td>
                    <td>user</td>
                </tr>
                <tr>
                    <td>user2@example.com</td>
                    <td>user2</td>
                    <td>User2@123</td>
                    <td>user</td>
                </tr>
                <tr>
                    <td>victim@example.com</td>
                    <td>victim</td>
                    <td>Victim@123</td>
                    <td>user</td>
                </tr>
            </tbody>
        </table>
        
        <div class="info">
            <p>تم أيضًا إنشاء بعض البيانات التجريبية في جداول سجلات النشاط وتسجيل الدخول وطلبات Airbag.</p>
        </div>
        
        <div class="warning">
            <strong>تحذير أمني:</strong> يرجى حذف هذا الملف فورًا بعد الاستخدام لتجنب المخاطر الأمنية!
        </div>
        
        <a href="login.php" class="btn">الذهاب لصفحة تسجيل الدخول</a>
    </div>
</body>
</html>
HTML;