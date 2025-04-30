<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// التحقق من صلاحية الأدمن
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// تحديث حالة التذكرة
if (isset($_GET['mark_seen']) && is_numeric($_GET['mark_seen'])) {
    $id = (int) $_GET['mark_seen'];
    $stmt = $pdo->prepare("UPDATE tickets SET is_seen = 1 WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header("Location: admin_tickets.php");
    exit;
}

// إلغاء التذكرة
if (isset($_GET['cancel_ticket']) && is_numeric($_GET['cancel_ticket'])) {
    $id = (int) $_GET['cancel_ticket'];
    $stmt = $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header("Location: admin_tickets.php");
    exit;
}

// جلب التذاكر
$stmt = $pdo->query("SELECT * FROM tickets ORDER BY created_at DESC");
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($tickets);
$seen = count(array_filter($tickets, fn($t) => isset($t['is_seen']) && $t['is_seen']));
$pending = $total - $seen;

// تحديد عنوان الصفحة ليظهر في layout.php
$page_title = "إدارة التذاكر";
include 'includes/layout.php';
?>

<div class="container">
  <h2>إدارة التذاكر</h2>
  <div class="ticket-stats">
    <div>إجمالي التذاكر: <strong><?= $total ?></strong></div>
    <div>تمت المراجعة: <strong><?= $seen ?></strong></div>
    <div>بانتظار المراجعة: <strong><?= $pending ?></strong></div>
  </div>

  <table style="width: 100%; border-collapse: collapse; margin-top: 25px;">
    <thead>
      <tr style="background-color: #1e293b;">
        <th>رقم</th><th>العميل</th><th>هاتف</th><th>السيارة</th>
        <th>الشاصي</th><th>الخدمة</th><th>الحالة</th><th>الإجراء</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($tickets as $row): ?>
      <tr style="border-bottom: 1px solid #3b3b3b;">
        <td>FLEX-<?= $row['id'] ?></td>
        <td><?= htmlspecialchars($row['username']) ?></td>
        <td><?= htmlspecialchars($row['phone']) ?></td>
        <td><?= htmlspecialchars($row['car_type']) ?></td>
        <td><?= htmlspecialchars($row['chassis']) ?></td>
        <td><?= htmlspecialchars($row['service_type']) ?></td>
        <td>
          <?php if (isset($row['is_seen']) && $row['is_seen']): ?>
            <span style="color:#00ff88;font-weight:bold;"><i class="fas fa-check-circle"></i> تمت المراجعة</span>
          <?php else: ?>
            <span style="color:#ffc107;font-weight:bold;"><i class="fas fa-clock"></i> قيد الانتظار</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if (!isset($row['is_seen']) || !$row['is_seen']): ?>
            <a href="?mark_seen=<?= $row['id'] ?>" class="action-btn"><i class="fas fa-check"></i> مراجعة</a>
          <?php else: ?>
            <button class="action-btn btn-disabled"><i class="fas fa-check-circle"></i> تم</button>
          <?php endif; ?>

          <a href="?cancel_ticket=<?= $row['id'] ?>" class="action-btn btn-danger" onclick="return confirm('هل أنت متأكد من إلغاء هذه التذكرة؟');">
            <i class="fas fa-ban"></i> إلغاء
          </a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
