<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// حماية المشرف فقط
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// جلب طلبات الكود البرمجي
$stmt1 = $pdo->query("
    SELECT r.*, u.username 
    FROM code_requests r 
    JOIN users u ON r.user_id = u.id 
    ORDER BY r.id DESC
");
$code_requests = $stmt1->fetchAll(PDO::FETCH_ASSOC);

// جلب طلبات مسح بيانات الحادث
$stmt2 = $pdo->query("
    SELECT a.*, u.username 
    FROM airbag_resets a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.id DESC
");
$airbag_requests = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// جلب طلبات تعديل ECU
$stmt3 = $pdo->query("
    SELECT e.*, u.username 
    FROM ecu_tuning_requests e 
    JOIN users u ON e.user_id = u.id 
    ORDER BY e.id DESC
");
$ecu_requests = $stmt3->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>طلبات الكود البرمجي</h2>
<table border="1" cellpadding="8" cellspacing="0" width="100%">
    <tr>
        <th>#</th>
        <th>المستخدم</th>
        <th>رقم الشاسيه</th>
        <th>النوع</th>
        <th>الموديل</th>
    </tr>
    <?php foreach ($code_requests as $r): ?>
    <tr>
        <td><?= $r['id']; ?></td>
        <td><?= htmlspecialchars($r['username']); ?></td>
        <td><?= htmlspecialchars($r['chassis_number']); ?></td>
        <td><?= htmlspecialchars($r['vehicle_make']); ?></td>
        <td><?= htmlspecialchars($r['vehicle_model']); ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<h2>طلبات مسح بيانات الحادث</h2>
<table border="1" cellpadding="8" cellspacing="0" width="100%">
    <tr>
        <th>#</th>
        <th>المستخدم</th>
        <th>نوع السيارة</th>
        <th>رقم ECU</th>
        <th>الملف</th>
    </tr>
    <?php foreach ($airbag_requests as $r): ?>
    <tr>
        <td><?= $r['id']; ?></td>
        <td><?= htmlspecialchars($r['username']); ?></td>
        <td><?= htmlspecialchars($r['vehicle_type']); ?></td>
        <td><?= htmlspecialchars($r['ecu_number']); ?></td>
        <td><a href="../uploads/<?= $r['uploaded_file']; ?>" target="_blank">تحميل</a></td>
    </tr>
    <?php endforeach; ?>
</table>

<h2>طلبات تعديل ECU</h2>
<table border="1" cellpadding="8" cellspacing="0" width="100%">
    <tr>
        <th>#</th>
        <th>المستخدم</th>
        <th>موديل ECU</th>
        <th>التعديلات المطلوبة</th>
        <th>الملف</th>
    </tr>
    <?php foreach ($ecu_requests as $r): ?>
    <tr>
        <td><?= $r['id']; ?></td>
        <td><?= htmlspecialchars($r['username']); ?></td>
        <td><?= htmlspecialchars($r['ecu_model']); ?></td>
        <td><?= nl2br(htmlspecialchars($r['modifications'])); ?></td>
        <td><a href="../uploads/<?= $r['uploaded_file']; ?>" target="_blank">تحميل</a></td>
    </tr>
    <?php endforeach; ?>
</table>

<?php require_once '../includes/footer.php'; ?>
