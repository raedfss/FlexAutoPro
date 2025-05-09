<?php
// بدء الجلسة والتأكد من تسجيل الدخول
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

$user_role = $_SESSION['user_role'] ?? 'user';
$username = htmlspecialchars($_SESSION['username'], ENT_QUOTES);
?>

<main class="container">
    <div class="welcome-message">
        <h2>مرحبًا، <?= $username ?> 👋</h2>
        <p>أهلاً بك في لوحة التحكم الخاصة بك على منصة <strong>FlexAuto</strong>.</p>
    </div>

    <div class="dashboard-links">
        <ul>
            <?php if ($user_role === 'admin' || $user_role === 'staff'): ?>
                <li><a href="dashboard.php">📋 إدارة وتنفيذ الطلبات</a></li>
                <li><a href="manage_users.php">👥 إدارة المستخدمين</a></li>
                <li><a href="notifications.php">🔔 الإشعارات</a></li>
                <li><a href="messages.php">📩 الرسائل</a></li>
                <li><a href="profile.php">⚙️ الملف الشخصي</a></li>
                <?php if ($user_role === 'admin'): ?>
                    <li><a href="admin_employees.php">👨‍💼 إدارة الموظفين</a></li>
                    <li><a href="system_logs.php">📜 سجلات النظام</a></li>
                <?php endif; ?>
            <?php else: ?>
                <li><a href="request_code.php">🔐 طلب كود برمجي</a></li>
                <li><a href="airbag_reset.php">💥 مسح بيانات الحوادث</a></li>
                <li><a href="ecu_tuning.php">⚙️ تعديل برمجة ECU</a></li>
                <li><a href="notifications.php">🔔 عرض الإشعارات</a></li>
                <li><a href="messages.php">📩 الرسائل</a></li>
                <li><a href="profile.php">👤 إدارة الملف الشخصي</a></li>
            <?php endif; ?>
        </ul>
    </div>
</main>

<style>
    .welcome-message {
        text-align: center;
        margin-bottom: 30px;
        color: #ffffff;
    }

    .dashboard-links ul {
        list-style: none;
        padding: 0;
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 15px;
    }

    .dashboard-links ul li a {
        padding: 12px 20px;
        background-color: #004080;
        color: #ffffff;
        text-decoration: none;
        border-radius: 6px;
        transition: background 0.2s;
        display: inline-block;
    }

    .dashboard-links ul li a:hover {
        background-color: #0066cc;
    }

    @media (max-width: 768px) {
        .dashboard-links ul {
            flex-direction: column;
            align-items: center;
        }
    }
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
