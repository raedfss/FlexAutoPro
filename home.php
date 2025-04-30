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

$page_css = "
  .dashboard-box {
    background: rgba(0, 0, 0, 0.6);
    padding: 35px;
    border-radius: 16px;
    max-width: 700px;
    margin: 0 auto;
    box-shadow: 0 0 30px rgba(0, 255, 255, 0.08);
    text-align: center;
  }

  .dashboard-box h3 {
    margin-bottom: 30px;
    color: #fff;
  }

  .button-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    justify-content: center;
  }

  .button-grid a {
    padding: 14px 25px;
    font-size: 16px;
    font-weight: bold;
    text-decoration: none;
    color: #fff;
    border-radius: 8px;
    min-width: 180px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    transition: all 0.3s ease;
  }

  .admin-btn    { background: linear-gradient(135deg, #007bff, #3399ff); }
  .admin-btn:hover { background: linear-gradient(135deg, #006ce0, #2b89f9); }

  .danger-btn   { background: linear-gradient(135deg, #ff5722, #ff784e); }
  .danger-btn:hover { background: linear-gradient(135deg, #e14415, #ff6233); }

  .info-btn     { background: linear-gradient(135deg, #00bcd4, #33d2e0); }
  .info-btn:hover { background: linear-gradient(135deg, #00a5bc, #30c2d2); }

  .logout-btn   {
    display: inline-block;
    margin-top: 25px;
    color: #ff6b6b;
    text-decoration: none;
    font-weight: bold;
    padding: 10px 25px;
    border: 1px solid rgba(255, 107, 107, 0.4);
    border-radius: 6px;
  }

  .logout-btn:hover {
    background-color: rgba(255, 107, 107, 0.1);
    border-color: rgba(255, 107, 107, 0.6);
  }

  .version-btn {
    margin-top: 20px;
    display: inline-block;
    background: linear-gradient(135deg, #00c8ff, #007bff);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.3s;
    font-size: 14px;
  }

  .version-btn:hover {
    background: linear-gradient(135deg, #007bff, #00c8ff);
  }

  .version-badge {
    background-color: rgba(0,0,0,0.3);
    padding: 2px 6px;
    margin-right: 8px;
    border-radius: 4px;
    font-size: 12px;
  }
";

include __DIR__ . '/includes/layout.php';
?>

<div class="dashboard-box">
  <h3>لقد سجلت الدخول بصلاحية: <strong><?= $user_type === 'admin' ? 'مدير النظام' : 'مستخدم' ?></strong></h3>

  <div class="button-grid">
    <?php if ($user_type === 'admin'): ?>
      <a href="dashboard.php" class="admin-btn">📊 لوحة التحكم</a>
      <a href="manage_users.php" class="admin-btn">👥 إدارة المستخدمين</a>
      <a href="admin_tickets.php" class="danger-btn">🎫 إدارة التذاكر</a>
      <a href="logs.php" class="info-btn">📁 سجلات النظام</a>
    <?php else: ?>
      <a href="key-code.php" class="admin-btn">🔑 كود المفتاح</a>
      <a href="airbag-reset.php" class="admin-btn">💥 مسح بيانات الحوادث</a>
      <a href="ecu-tuning.php" class="admin-btn">🚗 تعديل برمجة السيارة</a>
      <a href="online-programming-ticket.php" class="admin-btn">🧾 حجز تذكرة برمجة</a>
      <a href="includes/my_tickets.php" class="info-btn">📋 تذاكري السابقة</a>
    <?php endif; ?>
  </div>

  <a href="version.php" class="version-btn">
    <span class="version-badge">v1.01</span> 🔄 آخر التحديثات
  </a>

  <div>
    <a href="logout.php" class="logout-btn">🔓 تسجيل الخروج</a>
  </div>
</div>
