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
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FlexAuto | إدارة التذاكر</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      margin: 0;
      font-family: 'Cairo', sans-serif;
      background: #0f172a;
      color: #f8fafc;
    }
    header {
      background: #070e1b;
      padding: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    header .logo {
      font-size: 22px;
      color: #00d9ff;
      font-weight: bold;
    }
    header .admin-controls a {
      color: #fff;
      margin-right: 15px;
      text-decoration: none;
    }
    .container {
      max-width: 1000px;
      margin: 40px auto;
      padding: 20px;
      background: rgba(255,255,255,0.03);
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0,0,0,0.3);
    }
    .container h2 {
      margin-bottom: 20px;
      text-align: center;
    }
    .ticket-stats div {
      margin: 10px 0;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 25px;
    }
    th, td {
      padding: 12px;
      text-align: center;
      border-bottom: 1px solid #3b3b3b;
    }
    th {
      background-color: #1e293b;
    }
    .status-reviewed {
      color: #00ff88;
      font-weight: bold;
    }
    .status-pending {
      color: #ffc107;
      font-weight: bold;
    }
    .action-btn {
      background: #1e90ff;
      color: white;
      padding: 6px 12px;
      text-decoration: none;
      border-radius: 5px;
      margin: 2px;
      display: inline-block;
    }
    .btn-danger {
      background: #dc3545;
    }
    .btn-disabled {
      background: #6c757d;
      cursor: not-allowed;
    }
    footer {
      text-align: center;
      padding: 20px;
      background: #070e1b;
      color: #ccc;
      margin-top: 40px;
    }
  </style>
</head>
<body>

<header>
  <div class="logo"><i class="fas fa-ticket-alt"></i> FlexAuto | التذاكر</div>
  <div class="admin-controls">
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
  </div>
</header>

<div class="container">
  <h2>إدارة التذاكر</h2>
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
        <td>FLEX-<?= $row['id'] ?></td>
        <td><?= htmlspecialchars($row['username']) ?></td>
        <td><?= htmlspecialchars($row['phone']) ?></td>
        <td><?= htmlspecialchars($row['car_type']) ?></td>
        <td><?= htmlspecialchars($row['chassis']) ?></td>
        <td><?= htmlspecialchars($row['service_type']) ?></td>
        <td>
          <?php if (isset($row['is_seen']) && $row['is_seen']): ?>
            <span class="status-reviewed"><i class="fas fa-check-circle"></i> تمت المراجعة</span>
          <?php else: ?>
            <span class="status-pending"><i class="fas fa-clock"></i> قيد الانتظار</span>
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

<footer>
  &copy; <?= date('Y') ?> FlexAuto | جميع الحقوق محفوظة.
</footer>

</body>
</html>
