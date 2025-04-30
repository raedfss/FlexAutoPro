<?php
// includes/layout.php
// هذا ملف التصميم الشامل لكل صفحات موقع FlexAuto

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($page_title) ? $page_title : 'FlexAuto' ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      margin: 0;
      font-family: 'Cairo', sans-serif;
      background: #0f172a;
      color: #f8fafc;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }
    header {
      background: #070e1b;
      padding: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }
    header .logo {
      font-size: 22px;
      color: #00d9ff;
      font-weight: bold;
    }
    header .nav a {
      color: #fff;
      margin-right: 15px;
      text-decoration: none;
    }
    .svg-background {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: -1;
      opacity: 0.2;
      pointer-events: none;
    }
    .svg-object {
      width: 100%;
      height: 100%;
      pointer-events: none;
    }
    main {
      flex: 1;
      padding: 20px;
      z-index: 1;
    }
    footer {
      text-align: center;
      padding: 20px;
      background: #070e1b;
      color: #ccc;
      margin-top: auto;
    }
    .footer-highlight {
      font-size: 18px;
      color: #00ffff;
      font-weight: bold;
      margin-bottom: 5px;
    }
  </style>
</head>
<body>

<!-- Background SVG -->
<div class="svg-background">
  <embed type="image/svg+xml" src="/admin/admin_background.svg" class="svg-object">
</div>

<!-- Header -->
<header>
  <div class="logo"><i class="fas fa-tools"></i> FlexAuto</div>
  <div class="nav">
    <a href="/home.php">الرئيسية</a>
    <a href="/dashboard.php">لوحة التحكم</a>
    <a href="mailto:raedfss@hotmail.com">اتصل بنا</a>
  </div>
</header>

<!-- Main Content -->
<main>
<?php
// يجب على كل صفحة استدعاء include 'includes/layout.php';
// ثم توكل محتوى الصفحة هنا
?>
<!-- محتوى الصفحة يُوضع هنا -->
</main>

<!-- Footer -->
<footer>
  <div class="footer-highlight">ذكاءٌ في الخدمة، سرعةٌ في الاستجابة، جودةٌ بلا حدود.</div>
  <div>Smart service, fast response, unlimited quality.</div>
  <div style="margin-top: 8px;">
    📧 raedfss@hotmail.com | ☎️ +962796519007
  </div>
  <div style="margin-top: 5px;">
    &copy; <?= date('Y') ?> FlexAuto. جميع الحقوق محفوظة.
  </div>
</footer>

</body>
</html>
