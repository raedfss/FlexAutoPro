<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
$user_type = $_SESSION['user_role'] ?? 'user';
$email = $_SESSION['email'] ?? '';

// إعداد عنوان الصفحة
$page_title = 'الصفحة الرئيسية';
$display_title = 'مرحبًا، ' . htmlspecialchars($username);

// عدد الإشعارات غير المقروءة (تأكد من وجود الجدول notifications في قاعدة البيانات)
$notifications_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_email = ? AND is_read = 0");
    $stmt->execute([$email]);
    $notifications_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Notification error: " . $e->getMessage());
}

// CSS مخصص للصفحة
$page_css = <<<CSS
.container {
  background: rgba(0, 0, 0, 0.7);
  padding: 35px;
  width: 90%;
  max-width: 880px;
  border-radius: 16px;
  text-align: center;
  margin: 30px auto;
  box-shadow: 0 0 40px rgba(0, 200, 255, 0.15);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(66, 135, 245, 0.25);
}
.avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: linear-gradient(145deg, #3494e6, #ec6ead);
  margin: 0 auto 15px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  color: white;
  box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}
.role {
  color: #a8d8ff;
  margin: 15px auto 25px;
  font-size: 18px;
}
.links {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 20px;
  margin-bottom: 25px;
}
.links a {
  padding: 15px 25px;
  background: linear-gradient(145deg, #1e90ff, #0070cc);
  color: white;
  text-decoration: none;
  border-radius: 10px;
  font-weight: bold;
  min-width: 180px;
  transition: 0.3s ease;
  box-shadow: 0 4px 10px rgba(0,0,0,0.3);
  position: relative;
}
.links a:hover {
  transform: translateY(-4px);
  background: linear-gradient(145deg, #2eaaff, #0088ff);
}
.admin-links a {
  background: linear-gradient(145deg, #6f00ff, #4700cc);
}
.admin-links a:hover {
  background: linear-gradient(145deg, #8a1aff, #5800ff);
}
.admin-highlight {
  background: linear-gradient(145deg, #ff7300, #cc4e00) !important;
}
.admin-highlight:hover {
  background: linear-gradient(145deg, #ff8c1a, #ff5e00) !important;
}
.admin-special {
  background: linear-gradient(145deg, #ff4757, #c44569) !important;
}
.admin-special:hover {
  background: linear-gradient(145deg, #ff6b7d, #ff4757) !important;
}
.notification-badge {
  position: absolute;
  top: -6px;
  right: -6px;
  background: red;
  color: white;
  border-radius: 50%;
  font-size: 12px;
  width: 22px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.version-btn {
  background: linear-gradient(145deg, #00c8ff, #007bff);
  color: white;
  padding: 10px 20px;
  border-radius: 10px;
  text-decoration: none;
  display: inline-block;
  margin-top: 10px;
}
.logout {
  margin-top: 30px;
}
.logout a {
  color: #ff6b6b;
  font-weight: bold;
  text-decoration: none;
}
.admin-section {
  margin-top: 25px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding-top: 25px;
}
CSS;

// تعريف محتوى الصفحة
ob_start();
?>
<div class="container">
  <div class="avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
  <div class="role">لقد سجلت الدخول بصلاحية: <strong><?= $user_type === 'admin' ? 'مدير النظام 👑' : 'مستخدم 👤' ?></strong></div>

  <div class="links <?= $user_type === 'admin' ? 'admin-links' : '' ?>">
    <?php if ($user_type === 'admin'): ?>
      <a href="dashboard.php">📊 لوحة التحكم</a>
      <a href="manage_users.php">👥 إدارة المستخدمين</a>
      <a href="admin_tickets.php" class="admin-highlight">🎫 إدارة التذاكر</a>
      <a href="admin_versions.php">🔖 إدارة الإصدارات</a>
      <a href="inventory_management.php">🏪 إدارة المستودع</a>
      <a href="admin_upload_excel.php" class="admin-special">📋 رفع ملف Excel للإيرباق</a>
      <a href="logs.php">📁 سجلات النظام</a>
    <?php else: ?>
      <a href="key-code.php">🔑 كود المفتاح</a>
      <a href="airbag-reset.php">💥 مسح بيانات الحوادث</a>
      <a href="ecu-tuning.php">🚗 تعديل برمجة السيارة</a>
      <a href="online-programming-ticket.php">🧾 حجز برمجة أونلاين</a>
      <a href="includes/my_tickets.php">
        📋 تذاكري
        <?php if ($notifications_count > 0): ?>
          <span class="notification-badge"><?= $notifications_count ?></span>
        <?php endif; ?>
      </a>
    <?php endif; ?>
  </div>

  <a href="version.php" class="version-btn">
    🔄 آخر التحديثات والتعديلات
  </a>

  <div class="logout">
    <a href="logout.php">🔓 تسجيل الخروج</a>
  </div>
</div>
<?php
$page_content = ob_get_clean();

// إدراج القالب
include __DIR__ . '/includes/layout.php';
?>