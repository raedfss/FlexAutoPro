<?php
// FlexAutoPro - Admin Dashboard

// بدء الجلسة
session_start();

// استدعاء الملفات المطلوبة
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// التحقق من صلاحيات المشرف
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
?>

<main class="container">
    <h1 class="text-center mb-4" style="color: #00ffff;">لوحة تحكم المشرف</h1>

    <p class="text-center">مرحبًا، <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> 👋</p>
    <p class="text-center mb-5">هذه هي لوحة تحكم الإدارة. يمكنك من هنا إدارة النظام بالكامل.</p>

    <div class="admin-links" style="max-width: 500px; margin: auto;">
        <ul style="list-style: none; padding: 0;">
            <li style="margin: 10px 0;">
                <a href="users.php" class="btn btn-primary w-100">
                    👥 إدارة المستخدمين
                </a>
            </li>
            <li style="margin: 10px 0;">
                <a href="requests.php" class="btn btn-primary w-100">
                    📄 متابعة الطلبات
                </a>
            </li>
            <li style="margin: 10px 0;">
                <a href="logs.php" class="btn btn-primary w-100">
                    🕵️ سجل العمليات
                </a>
            </li>
            <li style="margin: 10px 0;">
                <a href="settings.php" class="btn btn-primary w-100">
                    ⚙️ إعدادات النظام
                </a>
            </li>
        </ul>
    </div>
</main>

<?php
require_once 'includes/footer.php';
?>
