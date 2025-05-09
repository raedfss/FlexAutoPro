<?php
// 1) بدء الجلسة والتأكد من تسجيل الدخول
require_once __DIR__ . '/includes/auth.php';

// 2) تضمين الاتصال بقاعدة البيانات
require_once __DIR__ . '/includes/db.php';

// 3) تضمين الدوال المساعدة
require_once __DIR__ . '/includes/functions.php';

// 4) تضمين الهيدر العام (يحتوي <head> وفتح <body>)
require_once __DIR__ . '/includes/header.php';

// تحديد دور المستخدم - فقط للعرض
$user_role = $_SESSION['user_role'] ?? 'user';
$is_admin_or_staff = ($user_role === 'admin' || $user_role === 'staff');

// عرض البيانات الإضافية فقط - بدون أي عمليات كتابة على قاعدة البيانات
$tickets = [];
if ($is_admin_or_staff) {
    try {
        // استعلام بسيط للتذاكر
        $query = "SELECT t.*, u.username FROM tickets t JOIN users u ON t.user_email = u.email ORDER BY t.created_at DESC LIMIT 10";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $tickets = $stmt->fetchAll();
    } catch (PDOException $e) {
        // تجاهل الخطأ - فقط لا تعرض التذاكر
        $tickets = [];
    }
}
?>

<main class="container">
    <h2>مرحبًا، <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES) ?> 👋</h2>
    <p>أهلاً بك في لوحة التحكم الخاصة بك على منصة <strong>FlexAuto</strong>.</p>

    <?php if ($is_admin_or_staff && !empty($tickets)): ?>
    <!-- عرض مختصر للتذاكر (فقط للعرض - بدون أي عمليات) -->
    <div class="tickets-preview">
        <h3>آخر التذاكر المسجلة</h3>
        <div class="tickets-list">
            <?php foreach(array_slice($tickets, 0, 5) as $ticket): ?>
                <div class="ticket-item">
                    <div class="ticket-number"><?= htmlspecialchars($ticket['ticket_number']) ?></div>
                    <div class="ticket-subject"><?= htmlspecialchars($ticket['subject']) ?></div>
                    <div class="ticket-status status-<?= $ticket['status'] ?>">
                        <?= htmlspecialchars(getStatusName($ticket['status'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="view-all">
            <a href="tickets.php" class="view-all-link">عرض جميع التذاكر</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="dashboard-links">
        <ul>
            <li><a href="request_code.php">🔐 طلب كود برمجي</a></li>
            <li><a href="airbag_reset.php">💥 مسح بيانات الحوادث</a></li>
            <li><a href="ecu_tuning.php">⚙️ تعديل برمجة ECU</a></li>
            <li><a href="notifications.php">🔔 عرض الإشعارات</a></li>
            <li><a href="messages.php">📩 الرسائل</a></li>
            <li><a href="profile.php">👤 إدارة الملف الشخصي</a></li>
            <?php if ($is_admin_or_staff): ?>
            <li><a href="tickets.php">📋 إدارة التذاكر</a></li>
            <?php endif; ?>
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
    
    /* ستايل قسم التذاكر */
    .tickets-preview {
        background: rgba(0, 0, 0, 0.1);
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .tickets-preview h3 {
        margin-top: 0;
        color: #1e90ff;
    }
    .tickets-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .ticket-item {
        display: flex;
        background: rgba(0, 0, 0, 0.1);
        padding: 8px 12px;
        border-radius: 4px;
        justify-content: space-between;
        align-items: center;
    }
    .ticket-number {
        font-weight: bold;
        min-width: 80px;
    }
    .ticket-subject {
        flex: 1;
        padding: 0 10px;
    }
    .ticket-status {
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 12px;
    }
    .status-open {
        background: rgba(255, 0, 0, 0.2);
        color: #ff5050;
    }
    .status-in_progress {
        background: rgba(255, 165, 0, 0.2);
        color: #ffaa33;
    }
    .status-completed {
        background: rgba(0, 128, 0, 0.2);
        color: #33cc33;
    }
    .status-cancelled, .status-rejected {
        background: rgba(128, 128, 128, 0.2);
        color: #999999;
    }
    .view-all {
        text-align: center;
        margin-top: 10px;
    }
    .view-all-link {
        display: inline-block;
        padding: 5px 15px;
        background-color: #1e90ff;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        font-size: 14px;
    }
    .view-all-link:hover {
        background-color: #0077e6;
    }
</style>

<?php
// Función auxiliar para obtener nombre del estado
function getStatusName($status) {
    $statuses = [
        'open' => 'جديدة',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتملة',
        'cancelled' => 'ملغاة',
        'rejected' => 'مرفوضة'
    ];
    return $statuses[$status] ?? $status;
}

// 5) تضمين الفوتر العام (يغلق </body></html>)
require_once __DIR__ . '/includes/footer.php';
?>