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

// دالة للتحقق من وجود الجدول
function tableExists($pdo, $table) {
    try {
        $result = $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}

// دالة لحذف بيانات الجدول إذا كان موجودًا
function safeDeleteTable($pdo, $table) {
    if (tableExists($pdo, $table)) {
        echo "جاري حذف البيانات من جدول {$table}...\n";
        $pdo->exec("DELETE FROM {$table}");
        return true;
    } else {
        echo "جدول {$table} غير موجود، تم تخطي الحذف.\n";
        return false;
    }
}

// الآن سنقوم بالعملية
try {
    // بدء المعاملة لضمان التنفيذ الكامل أو الإلغاء الكامل
    $pdo->beginTransaction();
    
    // 1. حذف البيانات المرتبطة من الجداول المحتملة
    // لن نستخدم DELETE مباشرة، بل سنتحقق أولاً من وجود الجدول
    $related_tables = [
        'activity_logs', 
        'login_logs', 
        'airbag_resets',
        'password_resets',
        'user_sessions',
        'user_logs',
        'user_devices',
        // يمكنك إضافة المزيد من الجداول هنا
    ];
    
    // التحقق من كل جدول وحذف البيانات منه إذا كان موجودًا
    foreach ($related_tables as $table) {
        safeDeleteTable($pdo, $table);
    }
    
    // 2. التحقق من وجود جدول المستخدمين
    if (!tableExists($pdo, 'users')) {
        throw new Exception("جدول المستخدمين (users) غير موجود في قاعدة البيانات! تأكد من الاتصال بقاعدة البيانات الصحيحة.");
    }
    
    // 3. حذف جميع المستخدمين
    echo "جاري حذف جميع المستخدمين...\n";
    $pdo->exec("DELETE FROM users");
    
    // 4. جلب هيكل جدول المستخدمين للتأكد من الأعمدة المتاحة
    echo "جاري التحقق من هيكل جدول المستخدمين...\n";
    $columns = [];
    $columnsQuery = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'users'");
    while ($column = $columnsQuery->fetchColumn()) {
        $columns[] = strtolower($column);
    }
    
    echo "الأعمدة المتاحة في جدول المستخدمين: " . implode(", ", $columns) . "\n";
    
    // 5. إنشاء المستخدمين الجدد
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
    
    // التحقق من وجود الأعمدة وإنشاء استعلام الإدخال المناسب
    $availableColumns = [];
    $values = [];
    
    // الأعمدة الأساسية المطلوبة
    $requiredColumns = ['email', 'username', 'password'];
    
    // التحقق من وجود الأعمدة الأساسية
    foreach ($requiredColumns as $col) {
        if (!in_array(strtolower($col), $columns)) {
            throw new Exception("العمود الأساسي '{$col}' غير موجود في جدول المستخدمين!");
        }
    }
    
    // الأعمدة المحتملة وقيمها الافتراضية
    $potentialColumns = [
        'email' => null,
        'username' => null,
        'password' => null,
        'fullname' => null,
        'role' => 'user',
        'is_active' => 1,
        'created_at' => 'NOW()',
        'updated_at' => 'NOW()',
        'login_attempts' => 0,
        'lockout_time' => null,
        'last_login' => null
    ];
    
    // تحديد الأعمدة المتاحة للإدخال
    foreach ($potentialColumns as $col => $default) {
        if (in_array(strtolower($col), $columns)) {
            $availableColumns[] = $col;
            if ($default !== null && $col !== 'created_at' && $col !== 'updated_at') {
                $values[] = ":{$col}";
            } else if ($col === 'created_at' || $col === 'updated_at') {
                $values[] = $default;
            } else {
                $values[] = ":{$col}";
            }
        }
    }
    
    // إنشاء استعلام الإدخال الديناميكي
    $columnsStr = implode(', ', $availableColumns);
    $valuesStr = implode(', ', $values);
    $sql = "INSERT INTO users ({$columnsStr}) VALUES ({$valuesStr})";
    
    echo "استعلام الإدخال: {$sql}\n";
    $stmt = $pdo->prepare($sql);
    
    // إدخال كل مستخدم
    foreach ($users as $user) {
        // تجهيز البيانات للإدخال
        $data = [];
        foreach ($availableColumns as $col) {
            if ($col === 'created_at' || $col === 'updated_at') {
                // سيتم استخدام NOW() من السيرفر
                continue;
            } else if ($col === 'password') {
                // تشفير كلمة المرور
                $data[$col] = password_hash($user['password'], PASSWORD_DEFAULT);
            } else if (isset($user[$col])) {
                // استخدام القيمة من مصفوفة المستخدم
                $data[$col] = $user[$col];
            } else if (isset($potentialColumns[$col])) {
                // استخدام القيمة الافتراضية
                $data[$col] = $potentialColumns[$col];
            }
        }
        
        // تنفيذ الإدخال
        $stmt->execute($data);
        
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
    
} catch (Exception $e) {
    // التراجع عن التغييرات في حالة حدوث خطأ
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
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