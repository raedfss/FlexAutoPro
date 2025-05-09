<?php
// 1) بدء الجلسة والتأكد من تسجيل الدخول
require_once __DIR__ . '/includes/auth.php';

// 2) تضمين الاتصال بقاعدة البيانات
require_once __DIR__ . '/includes/db.php';

// 3) تضمين الدوال المساعدة
require_once __DIR__ . '/includes/functions.php';

// 4) تضمين الهيدر العام (يحتوي <head> وفتح <body>)
require_once __DIR__ . '/includes/header.php';
?>

<main class="container">
    <h2>مرحبًا، <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES) ?> 👋</h2>
    <p>أهلاً بك في لوحة التحكم الخاصة بك على منصة <strong>FlexAuto</strong>.</p>

    <div class="dashboard-links">
        <ul>
            <li><a href="request_code.php">🔐 طلب كود برمجي</a></li>
            <li><a href="airbag_reset.php">💥 مسح بيانات الحوادث</a></li>
            <li><a href="ecu_tuning.php">⚙️ تعديل برمجة ECU</a></li>
            <li><a href="notifications.php">🔔 عرض الإشعارات</a></li>
            <li><a href="messages.php">📩 الرسائل</a></li>
            <li><a href="profile.php">👤 إدارة الملف الشخصي</a></li>
        </ul>
    </div>
</main>

<style>
    /* تخصيص روابط لوحة التحكم */
    .dashboard-links ul {
        list-style: none;
        margin: 20px 0;
        padding: 0;
    }
    .dashboard-links ul li {
        margin: 8px 0;
    }
    .dashboard-links ul li a {
        display: inline-block;
        padding: 10px 18px;
        background-color: #004080;
        color: #fff;
        text-decoration: none;
        border-radius: 6px;
        transition: background 0.2s;
    }
    .dashboard-links ul li a:hover {
        background-color: #0066cc;
    }
</style>

<?php
// 5) تضمين الفوتر العام (يغلق </body></html>)
require_once __DIR__ . '/includes/footer.php';
