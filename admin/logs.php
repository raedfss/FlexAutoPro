<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// حماية المشرف فقط
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// جلب السجلات
$stmt = $pdo->query("
    SELECT l.*, u.username 
    FROM logs l 
    LEFT JOIN users u ON l.user_id = u.id 
    ORDER BY l.created_at DESC
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>سجل العمليات</h2>

<table border="1" cellpadding="8" cellspacing="0" width="100%">
    <tr>
        <th>#</th>
        <th>المستخدم</th>
        <th>العملية</th>
        <th>IP</th>
        <th>التاريخ</th>
    </tr>
    <?php foreach ($logs as $log): ?>
    <tr>
        <td><?= $log['id']; ?></td>
        <td><?= htmlspecialchars($log['username'] ?? 'غير معروف'); ?></td>
        <td><?= htmlspecialchars($log['action']); ?></td>
        <td><?= $log['ip_address']; ?></td>
        <td><?= formatDate($log['created_at']); ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<?php require_once '../includes/footer.php'; ?>
