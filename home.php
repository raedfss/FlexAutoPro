<?php
session_start();
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
$user_type = $_SESSION['user_role'] ?? 'user';

$page_title = 'الصفحة الرئيسية';
$display_title = 'مرحبًا، ' . htmlspecialchars($username);

include __DIR__ . '/includes/layout.php';
?>

<div class="container">
  <div class="role">لقد سجلت الدخول بصلاحية: <strong><?= $user_type === 'admin' ? 'مدير النظام' : 'مستخدم' ?></strong></div>

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

  <a href="version.php" class="version-btn">
    🔄 آخر التحديثات والتعديلات
    <span class="version-badge">v1.01</span>
  </a>

  <div class="logout" style="margin-top: 30px;">
    <a href="logout.php">🔓 تسجيل الخروج</a>
  </div>
</div>
