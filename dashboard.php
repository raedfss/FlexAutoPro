<?php
// بدء الجلسة والتأكد من تسجيل الدخول
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

$user_role = $_SESSION['user_role'] ?? 'user';
$username = htmlspecialchars($_SESSION['username'], ENT_QUOTES);
$email = $_SESSION['email'] ?? '';

// عدد الإشعارات غير المقروءة
$notifications_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_email = ? AND is_read = 0");
    $stmt->execute([$email]);
    $notifications_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    // تجاهل الخطأ
}
?>

<main class="container">
    <div class="card welcome-card">
        <div class="avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
        <h2>مرحبًا، <?= $username ?> 👋</h2>
        <div class="role">
            <?php if ($user_role === 'admin'): ?>
                لقد سجلت الدخول بصلاحية: <strong>مدير النظام 👑</strong>
            <?php elseif ($user_role === 'staff'): ?>
                لقد سجلت الدخول بصلاحية: <strong>موظف 👨‍💼</strong>
            <?php else: ?>
                لقد سجلت الدخول بصلاحية: <strong>مستخدم 👤</strong>
            <?php endif; ?>
        </div>
        <p>أهلاً بك في لوحة التحكم الخاصة بك على منصة <strong>FlexAutoPro</strong>.</p>
    </div>

    <div class="dashboard-links <?= ($user_role === 'admin' || $user_role === 'staff') ? 'admin-links' : '' ?>">
        <?php if ($user_role === 'admin' || $user_role === 'staff'): ?>
            <!-- روابط للمدير والموظفين -->
            <a href="dashboard.php" class="admin-highlight">📋 إدارة وتنفيذ الطلبات</a>
            <a href="manage_users.php">👥 إدارة المستخدمين</a>
            <a href="notifications.php">
                🔔 الإشعارات
                <?php if ($notifications_count > 0): ?>
                    <span class="notification-badge"><?= $notifications_count ?></span>
                <?php endif; ?>
            </a>
            <a href="messages.php">📩 الرسائل</a>
            <a href="profile.php">⚙️ الملف الشخصي</a>
            
            <?php if ($user_role === 'admin'): ?>
                <!-- روابط إضافية للمدير فقط -->
                <a href="admin_employees.php">👨‍💼 إدارة الموظفين</a>
                <a href="system_logs.php">📜 سجلات النظام</a>
            <?php endif; ?>
        <?php else: ?>
            <!-- روابط للمستخدمين العاديين -->
            <a href="request_code.php">🔑 طلب كود برمجي</a>
            <a href="airbag_reset.php">💥 مسح بيانات الحوادث</a>
            <a href="ecu_tuning.php">🚗 تعديل برمجة ECU</a>
            <a href="online-programming-ticket.php">🧾 حجز برمجة أونلاين</a>
            <a href="includes/my_tickets.php">
                📋 تذاكري
                <?php if ($notifications_count > 0): ?>
                    <span class="notification-badge"><?= $notifications_count ?></span>
                <?php endif; ?>
            </a>
            <a href="notifications.php">🔔 عرض الإشعارات</a>
            <a href="messages.php">📩 الرسائل</a>
            <a href="profile.php">👤 إدارة الملف الشخصي</a>
        <?php endif; ?>
    </div>
    
    <div class="version-section">
        <a href="version.php" class="version-btn">
            🔄 آخر التحديثات والتعديلات
        </a>
    </div>
    
    <div class="logout">
        <a href="logout.php">🔓 تسجيل الخروج</a>
    </div>
</main>

<style>
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

.welcome-card {
  margin-bottom: 25px;
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

.dashboard-links {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 20px;
  margin-bottom: 25px;
}

.dashboard-links a {
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

.dashboard-links a:hover {
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
  transition: 0.3s ease;
}

.version-btn:hover {
  transform: translateY(-3px);
  box-shadow: 0 5px 15px rgba(0, 150, 255, 0.4);
}

.logout {
  margin-top: 30px;
}

.logout a {
  color: #ff6b6b;
  font-weight: bold;
  text-decoration: none;
  transition: 0.3s ease;
}

.logout a:hover {
  color: #ff3b3b;
  text-shadow: 0 0 5px rgba(255, 100, 100, 0.5);
}

/* تجاوب التصميم مع الشاشات المختلفة */
@media (max-width: 768px) {
  .container {
    width: 95%;
    padding: 25px 15px;
  }
  
  .dashboard-links {
    flex-direction: column;
    align-items: center;
  }
  
  .dashboard-links a {
    width: 100%;
    max-width: 300px;
  }
}
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>