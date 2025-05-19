<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تم إرسال التذكرة بنجاح | FlexAuto</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            color: white;
            background-color: #1a1f2e;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            line-height: 1.6;
        }

        /* خلفية SVG متحركة */
        .svg-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
            opacity: 0.5;
        }

        .svg-object {
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        header {
            background-color: rgba(0, 0, 0, 0.85);
            padding: 18px 20px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #00ffff;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(0, 255, 255, 0.3);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.4);
        }

        main {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 20px;
        }

        .container {
            background: rgba(0, 0, 0, 0.6);
            width: 100%;
            max-width: 700px;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(66, 135, 245, 0.2);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #00ffff, #4287f5, #00ffff);
            animation: border-glow 3s infinite;
        }

        @keyframes border-glow {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }

        .success-icon {
            display: inline-block;
            width: 80px;
            height: 80px;
            background-color: rgba(0, 200, 83, 0.1);
            border-radius: 50%;
            position: relative;
            margin-bottom: 20px;
            animation: scale-in 0.5s ease-out;
        }

        .success-icon::before {
            content: "✓";
            font-size: 50px;
            font-weight: bold;
            color: #00c853;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-shadow: 0 0 10px rgba(0, 200, 83, 0.5);
        }

        @keyframes scale-in {
            0% { 
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.2);
            }
            100% { 
                transform: scale(1);
                opacity: 1;
            }
        }

        h1 {
            font-size: 28px;
            margin-bottom: 15px;
            color: #fff;
            animation: fade-in 0.8s ease-out;
        }

        .message {
            color: #a0d0ff;
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 20px;
            animation: fade-in 1s ease-out;
        }

        .details {
            background-color: rgba(30, 35, 50, 0.5);
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
            text-align: right;
            border-right: 3px solid #4287f5;
            animation: slide-up 1s ease-out;
        }

        .details p {
            margin-bottom: 10px;
            color: #e0e0e0;
        }

        .details strong {
            color: #00ffff;
        }

        .ticket-id {
            font-family: monospace;
            background-color: rgba(0, 0, 0, 0.3);
            border-radius: 5px;
            padding: 5px 10px;
            color: #00ffff;
            font-weight: bold;
            display: inline-block;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
            animation: fade-in 1.2s ease-out;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 180px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e90ff, #4287f5);
            color: white;
            box-shadow: 0 4px 15px rgba(30, 144, 255, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(30, 144, 255, 0.4);
        }

        .btn-secondary {
            background: rgba(30, 35, 50, 0.8);
            color: #00ffff;
            border: 1px solid rgba(0, 255, 255, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(30, 35, 50, 1);
            transform: translateY(-3px);
        }

        footer {
            background-color: rgba(0, 0, 0, 0.9);
            color: #eee;
            text-align: center;
            padding: 20px;
            width: 100%;
        }

        .footer-highlight {
            font-size: 18px;
            font-weight: bold;
            color: #00ffff;
            margin-bottom: 10px;
        }

        @keyframes fade-in {
            from { 
                opacity: 0;
                transform: translateY(10px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slide-up {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            main {
                padding: 20px 15px;
            }
            
            .container {
                padding: 30px 20px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                min-width: 100%;
            }
            
            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

<div class="svg-background">
    <embed type="image/svg+xml" src="admin/admin_background.svg" class="svg-object">
</div>

<header>FlexAuto - تأكيد إرسال التذكرة</header>

<main>
    <div class="container">
        <div class="success-icon"></div>
        <h1>تم إرسال التذكرة بنجاح!</h1>
        <div class="message">شكراً لك، سيتم مراجعة الطلب قريبًا والرد عليك في أقرب وقت ممكن.</div>
        
        <div class="details">
            <p><strong>رقم التذكرة:</strong> <span class="ticket-id"><?= "FLEX-" . date("Ymd") . "-" . rand(1000, 9999) ?></span></p>
            <p><strong>تاريخ الإرسال:</strong> <?= date("Y/m/d - h:i a") ?></p>
            <p><strong>حالة التذكرة:</strong> قيد المراجعة</p>
            <p><strong>الوقت المتوقع للرد:</strong> خلال 24 ساعة عمل</p>
        </div>
        
        <p class="message">
            سيتم التواصل معك عبر البريد الإلكتروني أو الهاتف في حال الحاجة لأي معلومات إضافية.
            <br>يمكنك متابعة حالة التذكرة من خلال لوحة التحكم الخاصة بك.
        </p>
        
        <div class="action-buttons">
            <!-- تم تعديل الرابط ليشير إلى المسار الصحيح -->
            <a href="includes/my_tickets.php" class="btn btn-primary">
                <span>📋 عرض تذاكري</span>
            </a>
            <a href="dashboard.php" class="btn btn-secondary">
                <span>🏠 الصفحة الرئيسية</span>
            </a>
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