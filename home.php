<?php
session_start();

// منع الدخول المباشر بدون تسجيل دخول

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
$user_type = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'user';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexAuto | الصفحة الرئيسية</title>
    <style>
        /* نفس التنسيقات السابقة */
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            color: white;
            overflow-x: hidden;
            overflow-y: auto;
            background-color: #1a1f2e;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .svg-background {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: -1;
            overflow: hidden;
            opacity: 0.6;
        }
        .svg-object {
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        header {
            background-color: rgba(0, 0, 0, 0.85);
            padding: 20px;
            text-align: center;
            font-size: 30px;
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
            padding: 20px;
        }
        .container {
            background: rgba(0, 0, 0, 0.65);
            padding: 30px;
            width: 80%;
            max-width: 800px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(66, 135, 245, 0.2);
            transition: all 0.3s ease;
        }
        .container:hover {
            box-shadow: 0 0 40px rgba(0, 255, 255, 0.2);
        }
        h1 {
            margin-bottom: 10px;
            color: #fff;
            text-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
        }
        .role {
            font-size: 18px;
            margin-bottom: 30px;
            color: #a0d0ff;
        }
        .links {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin: 30px 0;
        }
        .links a {
            display: inline-block;
            padding: 15px 25px;
            background-color: rgba(30, 144, 255, 0.8);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            min-width: 180px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }
        .links a:hover {
            background-color: rgba(99, 179, 237, 0.9);
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.4);
        }
        .admin-highlight {
            background: linear-gradient(135deg, #ff7e00, #ff5000) !important;
            border: 1px solid rgba(255, 126, 0, 0.4);
            position: relative;
            overflow: hidden;
        }
        .admin-highlight::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            animation: shine 2s infinite;
        }
        @keyframes shine {
            100% {
                left: 100%;
            }
        }
        .admin-highlight:hover {
            background: linear-gradient(135deg, #ff5000, #ff7e00) !important;
        }
        .logout {
            margin-top: 40px;
        }
        .logout a {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: bold;
            padding: 10px 20px;
            border: 1px solid rgba(255, 107, 107, 0.4);
            border-radius: 5px;
            transition: all 0.3s;
        }
        .logout a:hover {
            background-color: rgba(255, 107, 107, 0.1);
            border-color: rgba(255, 107, 107, 0.6);
        }

        /* زر الإصدارات */
        .version-btn {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(135deg, #00c8ff, #007bff);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            margin-top: 20px;
            border: 1px solid rgba(0, 200, 255, 0.3);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        .version-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            animation: shine 3s infinite;
        }
        .version-btn:hover {
            background: linear-gradient(135deg, #007bff, #00c8ff);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.4);
        }
        .version-badge {
            display: inline-block;
            background-color: rgba(0, 0, 0, 0.3);
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 5px;
            font-size: 12px;
        }

        footer {
            background-color: rgba(0, 0, 0, 0.9);
            color: #eee;
            text-align: center;
            padding: 20px;
            font-size: 14px;
            width: 100%;
        }
        .footer-highlight {
            font-size: 20px;
            font-weight: bold;
            color: #00ffff;
            margin-bottom: 10px;
        }
        @media (max-width: 768px) {
            .container { width: 95%; padding: 20px; }
            .links { flex-direction: column; }
            .links a { width: 100%; min-width: unset; }
            header { font-size: 24px; padding: 15px; }
        }
    </style>
</head>
<body>

<div class="svg-background">
    <embed type="image/svg+xml" src="admin/admin_background.svg" class="svg-object">
</div>

<header>
    FlexAuto - نظام ورشة السيارات الذكي
</header>

<main>
    <div class="container">
        <h1>مرحبًا، <?= htmlspecialchars($username) ?>!</h1>
        <div class="role">لقد سجلت الدخول بصلاحية: <strong><?= $user_type == 'admin' ? 'مدير النظام' : 'مستخدم' ?></strong></div>

        <div class="links">
            <?php if ($user_type == 'admin'): ?>
                <a href="dashboard.php">📊 لوحة التحكم</a>
                <a href="manage_users.php">👥 إدارة المستخدمين</a>
                <a href="admin_tickets.php" class="admin-highlight">🎫 إدارة التذاكر</a>
                <a href="logs.php">📁 سجلات النظام</a>
            <?php else: ?>
                <a href="key-code.php">🔑 كود المفتاح</a>
                <a href="airbag-reset.php">💥 مسح بيانات الحوادث</a>
                <a href="ecu-tuning.php">🚗 تعديل برمجة السيارة</a>
                <a href="online-programming-ticket.php">🧾 حجز تذكرة برمجة أونلاين</a>
                <a href="includes/my_tickets.php">📋 تذاكري السابقة</a>
            <?php endif; ?>
        </div>

        <!-- زر الإصدارات الجديد -->
        <!-- يُوجّه المستخدم إلى صفحة version.php (يمكنك تسميتها changelog.php أو about.php) -->
        <a href="version.php" class="version-btn">
            🔄 آخر التحديثات والتعديلات 
            <span class="version-badge">v1.01</span>
        </a>

        <div class="logout">
            <a href="logout.php">🔓 تسجيل الخروج</a>
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
