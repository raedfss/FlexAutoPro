<?php
// FlexAutoPro - includes/db.php
// إعداد الاتصال بقاعدة البيانات باستخدام PDO - يدعم Localhost و PostgreSQL على Railway

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (str_contains($host, 'localhost')) {
    // 📌 بيئة التطوير: Localhost باستخدام MySQL
    $db_type = 'mysql';
    $db_host = 'localhost';
    $db_name = 'flexauto';    // تأكد أنه نفس اسم قاعدة البيانات لديك في XAMPP
    $db_user = 'root';
    $db_pass = '';
    $db_charset = 'utf8mb4';
} else {
    // 📌 بيئة الإنتاج: Railway باستخدام PostgreSQL
    $db_type = 'pgsql';
    $db_host = 'monorail.proxy.rlwy.net';
    $db_port = '5432';
    $db_name = 'railway';
    $db_user = 'postgres';
    $db_pass = 'qPDuGhAJpcnSsGanToKibGYbhGSAvyat';
}

try {
    if ($db_type === 'mysql') {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
        $pdo = new PDO($dsn, $db_user, $db_pass);
    } elseif ($db_type === 'pgsql') {
        $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name";
        $pdo = new PDO($dsn, $db_user, $db_pass);
    } else {
        throw new Exception("Unsupported database type: $db_type");
    }

    // إعدادات أمان وأخطاء
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
