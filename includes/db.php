<?php
// FlexAutoPro - includes/db.php
// إعداد الاتصال بقاعدة البيانات عبر PDO
// يدعم MySQL محلي (XAMPP) أو PostgreSQL على Railway باستخدام Private Networking

if (getenv('PGHOST')) {
    // 📌 Production: PostgreSQL on Railway (Private Network)
    $db_type  = 'pgsql';
    $db_host  = getenv('PGHOST') ?: 'postgres.railway.internal';
    $db_port  = getenv('PGPORT') ?: '5432';
    $db_name  = getenv('PGDATABASE') ?: 'railway';
    $db_user  = getenv('PGUSER') ?: 'postgres';
    $db_pass  = getenv('PGPASSWORD') ?: 'zrbJfRfwUFvcTUokiBdGWdXQWSNAvkNI';
    $dsn      = "pgsql:host={$db_host};port={$db_port};dbname={$db_name};sslmode=require";
} else {
    // 📌 Development: MySQL on Localhost (XAMPP)
    $db_type    = 'mysql';
    $db_host    = '127.0.0.1';
    $db_name    = 'flexauto';      // اسم قاعدة البيانات المحلية
    $db_user    = 'root';
    $db_pass    = '';
    $db_charset = 'utf8mb4';
    $dsn        = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";
}

try {
    $pdoOptions = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $pdoOptions);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
