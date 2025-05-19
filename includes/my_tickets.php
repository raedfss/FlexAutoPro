<?php
// FlexAutoPro - my_tickets.php

// بدء الجلسة والتحقق من تسجيل الدخول
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: ../login.php");
    exit;
}

// استدعاء الاتصال بقاعدة البيانات
require_once __DIR__ . '/../includes/db.php';

// جلب التذاكر الخاصة بالمستخدم
$username = $_SESSION['username'];

try {
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE username = :username ORDER BY created_at DESC");
    $stmt->execute(['username' => $username]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تذاكري - FlexAuto</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background-color: #1a1f2e;
            color: white;
            margin: 0;
            padding: 0;
        }
        .svg-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.5;
        }
        .svg-object {
            width: 100%;
            height: 100%;
        }
        header {
            background-color: rgba(0, 0, 0, 0.85);
            padding: 18px;
            text-align: center;
            font-size: 24px;
            color: #00ffff;
            font-weight: bold;
            border-bottom: 1px solid rgba(0, 255, 255, 0.3);
        }
        main {
            padding: 30px 20px;
            max-width: 1000px;
            margin: auto;
        }
        .container {
            background: rgba(0, 0, 0, 0.6);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.1);
            border: 1px solid rgba(66, 135, 245, 0.2);
            margin-bottom: 30px;
        }
        h1 {
            text-align: center;
            color: #00ffff;
            margin-bottom: 20px;
        }
        .ticket-box {
            background-color: rgba(30, 35, 50, 0.6);
            border: 1px solid rgba(66, 135, 245, 0.2);
            border-right: 5px solid #00ffff;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 12px;
        }
        .ticket-box p {
            margin: 8px 0;
        }
        .ticket-id {
            font-weight: bold;
            color: #00ffff;
        }
        .ticket-status {
            font-weight: bold;
        }
        .status-reviewed {
            color: #00c853;
        }
        .status-pending {
            color: #ffc107;
        }
        .buttons {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: bold;
            display: inline-block;
            transition: 0.3s ease;
        }
        .btn-primary {
            background: linear-gradient(135deg, #1e90ff, #4287f5);
            color: white;
        }
        .btn-primary:hover {
            background: #1e90ff;
        }
        .btn-secondary {
            background-color: rgba(0, 0, 0, 0.3);
            border: 1px solid #00ffff;
            color: #00ffff;
        }
        .btn-secondary:hover {
            background-color: rgba(0, 0, 0, 0.6);
        }
        .btn-warning {
            background-color: #ff9800;
            color: white;
        }
        .btn-warning:hover {
            background-color: #ff7700;
        }
        footer {
            background-color: rgba(0, 0, 0, 0.9);
            color: #eee;
            text-align: center;
            padding: 20px;
            margin-top: 50px;
        }
        .footer-highlight {
            font-size: 18px;
            font-weight: bold;
            color: #00ffff;
            margin-bottom: 10px;
        }
        @media (max-width: 768px) {
            .buttons {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="svg-background">
    <embed type="image/svg+xml" src="../admin/admin_background.svg" class="svg-object">
</div>

<header>FlexAuto - تذاكري</header>

<main>
    <div class="container">
        <h1>تذاكري السابقة</h1>

        <?php if (!empty($tickets)): ?>
            <?php foreach ($tickets as $ticket): ?>
                <div class="ticket-box">
                    <p><strong>رقم التذكرة:</strong> <span class="ticket-id">FLEX-<?= htmlspecialchars($ticket['id']) ?></span></p>
                    <p><strong>نوع الخدمة:</strong> <?= htmlspecialchars($ticket['service_type']) ?></p>
                    <p><strong>نوع السيارة:</strong> <?= htmlspecialchars($ticket['car_type']) ?></p>
                    <p><strong>رقم الشاسيه:</strong> <?= htmlspecialchars($ticket['chassis']) ?></p>
                    <p><strong>تاريخ الإرسال:</strong> <?= date('Y/m/d - h:i A', strtotime($ticket['created_at'])) ?></p>
                    <p><strong>الحالة:</strong>
                        <span class="ticket-status <?= ($ticket['is_seen'] == 1) ? 'status-reviewed' : 'status-pending' ?>">
                            <?= ($ticket['is_seen'] == 1) ? 'تمت المراجعة' : 'قيد المراجعة' ?>
                        </span>
                    </p>
                    <div class="buttons">
                        <a href="../ticket_details.php?id=<?= $ticket['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-eye"></i> عرض التفاصيل
                        </a>
                        <button onclick="window.print()" class="btn btn-secondary">
                            <i class="fas fa-print"></i> طباعة
                        </button>
                        <?php if ($ticket['is_seen'] == 0): ?>
                            <a href="../edit_ticket.php?id=<?= $ticket['id'] ?>" class="btn btn-warning">
                                <i class="fas fa-edit"></i> تعديل التذكرة
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-center" style="color: #a0d0ff;">لا توجد تذاكر محفوظة.</p>
        <?php endif; ?>
    </div>
</main>

<footer>
    <div class="footer-highlight">ذكاءٌ في الخدمة، سرعةٌ في الاستجابة، جودةٌ بلا حدود.</div>
    <div>Smart service, fast response, unlimited quality.</div>
    <div style="margin-top: 8px;">📧 raedfss@hotmail.com | ☎️ +962796519007</div>
    <div style="margin-top: 5px;">&copy; <?= date('Y') ?> FlexAuto. جميع الحقوق محفوظة.</div>
</footer>

</body>
</html>
