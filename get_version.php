<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// التحقق من تسجيل الدخول وصلاحيات المدير
if (!isset($_SESSION['email']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'غير مصرح']);
    exit;
}

// التحقق من وجود معرف الإصدار
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'معرف غير صالح']);
    exit;
}

$id = (int)$_GET['id'];

try {
    // استرجاع بيانات الإصدار - استعلام متوافق مع PostgreSQL
    $stmt = $pdo->prepare("SELECT 
        id, 
        version_number,
        release_date::text as release_date,
        version_type,
        status,
        summary,
        details,
        affected_files,
        git_commands,
        created_at::text as created_at,
        updated_at::text as updated_at
    FROM versions WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $version = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$version) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'الإصدار غير موجود']);
        exit;
    }
    
    // إرسال البيانات كـ JSON
    header('Content-Type: application/json');
    echo json_encode($version);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'حدث خطأ أثناء استرجاع البيانات: ' . $e->getMessage()]);
    exit;
}