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

$page_title = 'الصفحة الرئيسية';
$display_title = 'مرحبًا، ' . htmlspecialchars($username);

// تخصيص ستايل إضافي
$page_css = "
  .container {
    background: rgba(0, 0, 0, 0.65);
    padding: 30px;
    width: 80%;
    max-width: 800px;
    border-radius: 15px;
    text-align: center;
    margin: 0 auto;
    box-shadow: 0 0 30px rgba(0, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(66, 135, 245, 0.2);
  }

  .container:hover {
    box-shadow: 0 0 40px rgba(0, 255, 255, 0.2);
  }

  .role {
    font-size: 18px;
    margin-bottom: 30px;
    color: #a0d0ff;
  }

  .links {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 20px;
    margin: 30px 0;
  }

  .links a {
    display: inline-block;
    padding: 15px 25px;
    background-color: rgba(30, 144, 255, 0.8);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-size: 16px;
    transition: all 0.3s;
    min-width: 180px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
  }

  .links a:hover {
    background-color: rgba(99, 179, 237, 0.9);
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.4);
  }

  .admin-highlight {
    background: linear-gradient(135deg, #ff7e00, #ff5000) !important;
  }

  .logout a {
    color: #ff6b6b;
    text-decoration: none;
    font-weight: bold;
    padding: 10px 20px;
    border: 1px solid rgba(255, 107, 107, 0.4);
    border-radius: 5px;
    transition: all 0.3s;
  }

  .logout a:hover {
    background-color: rgba(255, 107, 107, 0.1);
    border-color: rgba(255, 107, 107, 0.6);
  }

  .version-btn {
    display: inline-block;
    padding: 10px 20px;
    background: linear-gradient(135deg, #00c8ff, #007bff);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s;
    margin-top: 20px;
    border: 1px solid rgba(0, 200, 255, 0.3);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
  }

  .version-btn:hover {
    background: linear-gradient(135deg, #007bff, #00c8ff);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.4);
  }

  .version-badge {
    background-color: rgba(0, 0, 0, 0.3);
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 5px;
    font-size: 12px;
  }
";

// إدراج القالب الأساسي
include __DIR__ . '/includes/layout.php';
?>

<!-- المحتوى الرئيسي -->
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
