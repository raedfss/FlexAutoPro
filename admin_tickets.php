<?php
session_start();

// تضمين ملف الاتصال بقاعدة البيانات
require_once __DIR__ . '/db_connect.php';

// التأكد من أن المستخدم مسجَّل دخول وهو من نوع admin
if (!isset($_SESSION['email']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// ==========================
//  تحديث حالة التذكرة
// ==========================
if (isset($_GET['mark_seen']) && is_numeric($_GET['mark_seen'])) {
    $id = (int) $_GET['mark_seen'];
    $stmt = $conn->prepare("UPDATE tickets SET is_seen = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: admin_tickets.php");
    exit;
}

// ==========================
//  إلغاء التذكرة
// ==========================
if (isset($_GET['cancel_ticket']) && is_numeric($_GET['cancel_ticket'])) {
    $id = (int) $_GET['cancel_ticket'];
    // مثال لتحديث حالة التذكرة إلى cancelled
    $stmt = $conn->prepare("UPDATE tickets SET status = 'cancelled' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: admin_tickets.php");
    exit;
}

// ==========================
//  جلب كل التذاكر
// ==========================
$result = $conn->query("SELECT * FROM tickets ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة تذاكر FlexAuto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ——— حافظنا على أسلوبك السابق ——— */
        :root { /* … الألوان والـ CSS variables كما لديك … */ }
        body { margin:0; font-family:'Cairo',sans-serif; background:#0f172a; color:#f8fafc; }
        .top-bar { height:4px; background:linear-gradient(90deg,#0099ff,#00d9ff); }
        header { background:#070e1b; padding:20px; display:flex; justify-content:space-between; align-items:center; }
        /* … بقية تنسيق الصفحة كما في الكود الأساسي … */
        /* لتقصير المكان هنا، افترض أنك نقلت كل CSS من فوق كما هو */
    </style>
</head>
<body>

<div class="top-bar"></div>
<header>
    <div class="logo"><i class="fas fa-ticket-alt"></i> FlexAuto | تذاكر</div>
    <div class="admin-controls">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
    </div>
</header>

<div class="container">
    <h2>إدارة التذاكر</h2>

    <div class="ticket-stats">
        <?php
        $total = $result->num_rows;
        $seen = 0;
        $pending = 0;
        $result->data_seek(0);
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['is_seen'])) $seen++;
            else $pending++;
        }
        $result->data_seek(0);
        ?>
        <div>إجمالي التذاكر: <strong><?= $total ?></strong></div>
        <div>تمت المراجعة: <strong><?= $seen ?></strong></div>
        <div>بانتظار المراجعة: <strong><?= $pending ?></strong></div>
    </div>

    <table class="tickets-table">
        <thead>
            <tr>
                <th>رقم</th><th>العميل</th><th>هاتف</th><th>سيارة</th>
                <th class="hide-mobile">شاصي</th><th>الخدمة</th><th>الحالة</th><th>إجراء</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td>FLEX-<?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><?= htmlspecialchars($row['phone']) ?></td>
                <td><?= htmlspecialchars($row['car_type']) ?></td>
                <td class="hide-mobile"><?= htmlspecialchars($row['chassis']) ?></td>
                <td><?= htmlspecialchars($row['service_type']) ?></td>
                <td>
                    <?php if ($row['is_seen']): ?>
                        <span class="status-reviewed"><i class="fas fa-check-circle"></i> تمت المراجعة</span>
                    <?php else: ?>
                        <span class="status-pending"><i class="fas fa-clock"></i> قيد الانتظار</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$row['is_seen']): ?>
                        <a href="?mark_seen=<?= $row['id'] ?>" class="action-btn"><i class="fas fa-check"></i> تمت المراجعة</a>
                    <?php else: ?>
                        <button class="action-btn btn-disabled"><i class="fas fa-check-circle"></i> تم</button>
                    <?php endif; ?>

                    <a href="?cancel_ticket=<?= $row['id'] ?>"
                       class="action-btn btn-danger"
                       onclick="return confirm('هل أنت متأكد من إلغاء هذه التذكرة؟');">
                       <i class="fas fa-ban"></i> إلغاء
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<footer>جميع الحقوق محفوظة &copy; <?= date('Y') ?> FlexAuto</footer>

</body>
</html>
