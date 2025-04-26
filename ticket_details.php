<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("معرف التذكرة غير صالح.");
}

$ticket_id = intval($_GET['id']);
$username = $_SESSION['username'];

// جلب بيانات التذكرة
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ? AND username = ?");
$stmt->execute([$ticket_id, $username]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    die("لم يتم العثور على التذكرة.");
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تفاصيل التذكرة | FlexAuto</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            max-width: 800px;
            margin: auto;
        }

        .container {
            background: rgba(0, 0, 0, 0.6);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.1);
            border: 1px solid rgba(66, 135, 245, 0.2);
        }

        h1 {
            text-align: center;
            color: #00ffff;
            margin-bottom: 30px;
        }

        .info-row {
            margin-bottom: 15px;
        }

        .info-label {
            color: #a0d0ff;
            font-size: 14px;
        }

        .info-value {
            font-size: 16px;
            font-weight: bold;
        }

        .divider {
            border-bottom: 1px solid rgba(66, 135, 245, 0.2);
            margin: 20px 0;
        }

        .buttons {
            margin-top: 25px;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: bold;
            transition: 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e90ff, #4287f5);
            color: white;
        }

        .btn-secondary {
            background: rgba(30, 35, 50, 0.8);
            color: #00ffff;
            border: 1px solid rgba(0, 255, 255, 0.3);
        }

        .btn-warning {
            background: #ff9800;
            color: white;
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
            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="svg-background">
    <embed type="image/svg+xml" src="admin/admin_background.svg" class="svg-object">
</div>

<header>FlexAuto - تفاصيل التذكرة</header>

<main>
    <div class="container">
        <h1>تفاصيل التذكرة: FLEX-<?= htmlspecialchars($ticket['id']) ?></h1>

        <div class="info-row">
            <div class="info-label">نوع الخدمة:</div>
            <div class="info-value"><?= htmlspecialchars($ticket['service_type']) ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">نوع السيارة:</div>
            <div class="info-value"><?= htmlspecialchars($ticket['car_type']) ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">رقم الشاسيه:</div>
            <div class="info-value"><?= htmlspecialchars($ticket['chassis']) ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">تاريخ الإرسال:</div>
            <div class="info-value"><?= date('Y/m/d - h:i A', strtotime($ticket['created_at'])) ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">حالة التذكرة:</div>
            <div class="info-value">
                <?= $ticket['is_seen'] ? '<span style="color:#00c853">تمت المراجعة</span>' : '<span style="color:#ffc107">قيد المراجعة</span>' ?>
            </div>
        </div>

        <?php if (!empty($ticket['comments'])): ?>
            <div class="divider"></div>
            <div class="info-row">
                <div class="info-label">ملاحظات:</div>
                <div class="info-value"><?= nl2br(htmlspecialchars($ticket['comments'])) ?></div>
            </div>
        <?php endif; ?>

        <div class="buttons">
            <a href="my_tickets.php" class="btn btn-primary"><i class="fas fa-arrow-right"></i> رجوع</a>
            <button onclick="window.print();" class="btn btn-secondary"><i class="fas fa-print"></i> طباعة</button>
        </div>
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
