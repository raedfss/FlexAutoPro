<?php
/**
 * db_connect.php
 * فتح اتصال قاعدة البيانات تلقائيًا بناءً على البيئة
 */

if (getenv('RAILWAY_STATIC_URL')) {
    // ☁️ بيئة Railway (PostgreSQL)
    $host     = getenv('PGHOST');
    $username = getenv('PGUSER');
    $password = getenv('PGPASSWORD');
    $dbname   = getenv('PGDATABASE');
    $port     = getenv('PGPORT') ?: 5432;

    // نبني الـ DSN لـ mysqli (PostgreSQL عبر pdo_pgsql لا يدعمه mysqli)
    // هنا نستخدم PDO للـ PostgreSQL
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        die("فشل الاتصال بقاعدة البيانات (PostgreSQL): " . $e->getMessage());
    }
} else {
    // 🖥️ بيئة محلية (XAMPP) باستخدام MySQL
    $host     = 'localhost';
    $username = 'root';
    $password = '';
    $dbname   = 'flexauto';

    // إنشاء اتصال mysqli
    $conn = new mysqli($host, $username, $password, $dbname);

    // فحص الاتصال
    if ($conn->connect_error) {
        die("فشل الاتصال بقاعدة البيانات (MySQL): " . $conn->connect_error);
    }

    // تعيين الترميز
    $conn->set_charset("utf8");
}
?>
