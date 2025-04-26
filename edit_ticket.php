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

// عند إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_type = trim($_POST['service_type']);
    $car_type = trim($_POST['car_type']);
    $chassis = trim($_POST['chassis']);
    $additional_info = trim($_POST['additional_info']);

    if ($service_type && $car_type && $chassis) {
        $update_stmt = $pdo->prepare("
            UPDATE tickets 
            SET service_type = ?, car_type = ?, chassis = ?, additional_info = ? 
            WHERE id = ? AND username = ?
        ");
        $update_stmt->execute([$service_type, $car_type, $chassis, $additional_info, $ticket_id, $username]);
        header("Location: my_tickets.php?success=1");
        exit;
    } else {
        $error = "جميع الحقول مطلوبة باستثناء الملاحظات.";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعديل التذكرة | FlexAuto</title>
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

        form label {
            display: block;
            margin-bottom: 6px;
            color: #a0d0ff;
        }

        form input, form textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: none;
            border-radius: 10px;
            background-color: rgba(30, 35, 50, 0.8);
            color: white;
            font-size: 16px;
        }

        form textarea {
            resize: vertical;
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
            cursor: pointer;
            border: none;
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

<header>FlexAuto - تعديل التذكرة</header>

<main>
    <div class="container">
        <h1>تعديل بيانات التذكرة</h1>

        <?php if (isset($error)): ?>
            <div style="background-color: #ff4d4d; padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <label for="service_type">نوع الخدمة</label>
            <input type="text" id="service_type" name="service_type" value="<?= htmlspecialchars($ticket['service_type']) ?>" required>

            <label for="car_type">نوع السيارة</label>
            <input type="text" id="car_type" name="car_type" value="<?= htmlspecialchars($ticket['car_type']) ?>" required>

            <label for="chassis">رقم الشاسيه</label>
            <input type="text" id="chassis" name="chassis" value="<?= htmlspecialchars($ticket['chassis']) ?>" required>

            <label for="additional_info">ملاحظات إضافية</label>
            <textarea id="additional_info" name="additional_info" rows="4"><?= htmlspecialchars($ticket['additional_info']) ?></textarea>

            <div class="buttons">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ التعديلات</button>
                <a href="my_tickets.php" class="btn btn-secondary"><i class="fas fa-arrow-right"></i> رجوع</a>
            </div>
        </form>
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
