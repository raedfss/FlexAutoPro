<?php
// FlexAutoPro - includes/db.php
// اتصال بقاعدة بيانات Neon PostgreSQL

// بيانات الاتصال الأساسية
$db_type  = 'pgsql';
$db_host  = 'ep-silent-recipe-a4whzvsp-pooler.us-east-1.aws.neon.tech';
$db_port  = '5432';
$db_name  = 'neondb';
$db_user  = 'neondb_owner';
$db_pass  = 'npg_eWfsJy0PN5EQ';

// إعدادات الاتصال الآمن (SSL)
$ssl_mode = 'require'; // يمكن تغييره إلى "prefer" أو حذفه للاختبار المحلي

// بناء سلسلة الاتصال (DSN)
// أضف خيار SSL إذا كان مطلوباً (قياسي في بيئة الإنتاج)
$dsn = "{$db_type}:host={$db_host};port={$db_port};dbname={$db_name}";

// إضافة وضع SSL إذا كان متاحاً
if (!empty($ssl_mode)) {
    $dsn .= ";sslmode={$ssl_mode}";
}

// خيارات الاتصال المحسنة
$pdoOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,    // إظهار الأخطاء كاستثناءات
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,          // إرجاع النتائج كمصفوفات مسماة
    PDO::ATTR_EMULATE_PREPARES   => false,                     // استخدام التحضيرات الفعلية بدلاً من المحاكاة
    PDO::ATTR_PERSISTENT         => true,                      // استخدام اتصالات مستمرة لتحسين الأداء
];

try {
    // إنشاء كائن PDO
    $pdo = new PDO($dsn, $db_user, $db_pass, $pdoOptions);
    
    // تعيين المنطقة الزمنية لقاعدة البيانات (اختياري)
    $pdo->exec("SET timezone = 'UTC'");
    
} catch (PDOException $e) {
    // تسجيل الخطأ بدلاً من عرضه مباشرة للمستخدم (للأمان)
    error_log('Database Connection Error: ' . $e->getMessage());
    
    // عرض رسالة خطأ عامة في وضع الإنتاج
    if (getenv('ENVIRONMENT') === 'production') {
        die("حدث خطأ في الاتصال بقاعدة البيانات. يرجى المحاولة مرة أخرى لاحقاً.");
    } else {
        // عرض تفاصيل الخطأ في وضع التطوير
        die("Database Connection Failed: " . $e->getMessage());
    }
}

/**
 * وظيفة مساعدة لتنفيذ استعلام مع معالجة الأخطاء
 *
 * @param PDO $pdo كائن اتصال قاعدة البيانات
 * @param string $query الاستعلام المراد تنفيذه
 * @param array $params المعاملات للاستعلام (اختياري)
 * @return PDOStatement|false
 */
function db_query($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log('Query Error: ' . $e->getMessage());
        return false;
    }
}
?>