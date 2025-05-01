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
    // استرجاع بيانات الإصدار
    $stmt = $pdo->prepare("SELECT * FROM versions WHERE id = ?");
    $stmt->execute([$id]);
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
    echo json_encode(['error' => 'حدث خطأ أثناء استرجاع البيانات']);
    exit;
}
?>