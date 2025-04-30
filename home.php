<?php
session_start();

// منع الدخول المباشر بدون تسجيل دخول
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
$user_type = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'user';

$page_title = "الصفحة الرئيسية";
include 'includes/layout.php';
?>

<div class="container">
    <h1>مرحبًا، <?= htmlspecialchars($username) ?>!</h1>
    <div class="role">
        لقد سجلت الدخول بصلاحية:
        <strong><?= $user_type === 'admin' ? 'مدير النظام' : 'مستخدم' ?></strong>
    </div>

    <div class="links">
        <?php if ($user_type === 'admin'): ?>
            <a href="dashboard.php">📊 لوحة التحكم</a>
            <a href="manage_users.php">👥 إدارة المستخدمين</a>
            <a href="admin_tickets.php" class="admin-highlight">🎫 إدارة التذاكر</a>
            <a href="logs.php">📁 سجلات النظام</a>
        <?php else: ?>
            <a href="key-code.php">🔑 كود المفتاح</a>
            <a href="airbag-reset.php">💥 مسح بيانات الحوادث</a>
            <a href="ecu-tuning.php">🚗 تعديل برمجة السيارة</a>
            <a href="online-programming-ticket.php">🧾 حجز تذكرة برمجة أونلاين</a>
            <a href="includes/my_tickets.php">📋 تذاكري السابقة</a>
        <?php endif; ?>
    </div>

    <!-- زر الإصدارات -->
    <a href="version.php" class="version-btn">
        🔄 آخر التحديثات والتعديلات 
        <span class="version-badge">v1.01</span>
    </a>

    <div class="logout">
        <a href="logout.php">🔓 تسجيل الخروج</a>
    </div>
</div>
