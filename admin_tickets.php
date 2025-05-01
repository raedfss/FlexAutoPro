<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// التحقق من صلاحية الأدمن
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// إنشاء رمز CSRF إذا لم يكن موجودًا
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// التحقق من تنفيذ mark_seen
if (isset($_GET['mark_seen'])) {
    if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf'])) {
        die("🚫 طلب غير صالح (رمز الحماية CSRF مفقود أو غير مطابق).");
    }

    $id = (int) $_GET['mark_seen'];
    $stmt = $pdo->prepare("UPDATE tickets SET is_seen = TRUE WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header("Location: admin_tickets.php");
    exit;
}

// التحقق من تنفيذ cancel_ticket
if (isset($_GET['cancel_ticket'])) {
    if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf'])) {
        die("🚫 لا يمكن تنفيذ العملية. رمز الحماية CSRF غير صحيح.");
    }

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

$page_title = "إدارة التذاكر";
$page_css = <<<CSS
.container {
  background: rgba(15, 23, 42, 0.8);
  border-radius: 10px;
  padding: 25px;
  margin-bottom: 30px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}
.ticket-stats {
  display: flex;
  justify-content: space-around;
  background: rgba(30, 41, 59, 0.7);
  padding: 15px;
  border-radius: 8px;
  margin: 20px 0;
  font-size: 16px;
}
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 25px;
  background: rgba(15, 23, 42, 0.5);
  border-radius: 8px;
  overflow: hidden;
}
thead tr {
  background-color: #1e293b;
  color: #f8fafc;
  text-align: right;
}
th, td {
  padding: 12px 15px;
  text-align: right;
}
tbody tr {
  border-bottom: 1px solid #3b3b3b;
  transition: background-color 0.3s;
}
tbody tr:hover {
  background-color: rgba(59, 130, 246, 0.1);
}
.action-btn {
  display: inline-block;
  padding: 6px 12px;
  margin: 3px;
  border-radius: 5px;
  text-decoration: none;
  color: white;
  background: #1e90ff;
  cursor: pointer;
  border: none;
  font-size: 14px;
  transition: all 0.2s;
}
.action-btn:hover {
  background: #0078e7;
  transform: translateY(-2px);
}
.btn-danger {
  background: #ff6b6b;
}
.btn-danger:hover {
  background: #e74c3c;
}
.btn-disabled {
  background: #64748b;
  cursor: not-allowed;
}
CSS;

ob_start();
?>
<div class="container">
  <div class="ticket-stats">
    <div>إجمالي التذاكر: <strong><?= $total ?></strong></div>
    <div>تمت المراجعة: <strong><?= $seen ?></strong></div>
    <div>بانتظار المراجعة: <strong><?= $pending ?></strong></div>
  </div>

  <table>
    <thead>
      <tr>
        <th>رقم</th><th>العميل</th><th>هاتف</th><th>السيارة</th>
        <th>الشاصي</th><th>الخدمة</th><th>الحالة</th><th>الإجراء</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($tickets as $row): ?>
      <tr>
        <td>FLEX-<?= htmlspecialchars($row['id']) ?></td>
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
            <a href="?mark_seen=<?= $row['id'] ?>&csrf=<?= $csrf_token ?>" class="action-btn"><i class="fas fa-check"></i> مراجعة</a>
          <?php else: ?>
            <button class="action-btn btn-disabled"><i class="fas fa-check-circle"></i> تم</button>
          <?php endif; ?>

          <a href="?cancel_ticket=<?= $row['id'] ?>&csrf=<?= $csrf_token ?>" class="action-btn btn-danger"
             onclick="return confirm('هل أنت متأكد من إلغاء هذه التذكرة؟');">
            <i class="fas fa-ban"></i> إلغاء
          </a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
$page_content = ob_get_clean();
include __DIR__ . '/includes/layout.php';
?>
