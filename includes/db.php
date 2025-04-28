<?php
// FlexAutoPro - includes/db.php
// الاتصال بقاعدة بيانات PostgreSQL عبر Neon.tech

$db_type  = 'pgsql';
$db_host  = 'ep-silent-recipe-a4whzvsp-pooler.us-east-1.aws.neon.tech';
$db_port  = '5432';
$db_name  = 'neondb';
$db_user  = 'neondb_owner';
$db_pass  = 'npg_eWfsJy0PN5EQ';
$dsn      = "pgsql:host={$db_host};port={$db_port};dbname={$db_name};sslmode=require";

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
