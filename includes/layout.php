<?php
// منع الوصول المباشر للملف
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('🚫 لا يمكنك الوصول إلى هذا الملف مباشرة.');
}

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// إعداد المتغيرات الافتراضية
$is_admin = $_SESSION['user_role'] ?? null;
$site_title = 'FlexAuto';
$page_title = $page_title ?? $site_title;
$current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$current_page = basename($current_path);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($page_title) ?></title>

  <!-- الخطوط والأيقونات -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet" />

  <!-- ملف التنسيق العام -->
  <link rel="stylesheet" href="/assets/css/layout.css" />

  <?php if (isset($page_css)): ?>
    <style><?= $page_css ?></style>
  <?php endif; ?>
  
  <!-- إضافة أنماط CSS لإصلاح مشكلة الفوتر -->
  <style>
    html, body {
      height: 100%;
      margin: 0;
      padding: 0;
      display: flex;
      flex-direction: column;
    }
    
    body {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }
    
    main {
      flex: 1 0 auto;
      padding-bottom: 20px;
    }
    
    footer {
      flex-shrink: 0;
      width: 100%;
      margin-top: auto;
    }
    
    /* تأكيد أن المحتوى لا يظهر أسفل الفوتر */
    .content-wrapper {
      display: flex;
      flex-direction: column;
      flex: 1 0 auto;
    }
  </style>
</head>
<body>

<?php if (!isset($disable_background) || !$disable_background): ?>
  <div class="svg-background">
    <embed type="image/svg+xml" src="/assets/img/background.svg" class="svg-object" />
  </div>
<?php endif; ?>

<header>
  <div class="logo"><i class="fas fa-tools"></i> FlexAuto</div>
  <div class="nav">
    <a href="/index.php" <?= $current_page === 'index.php' ? 'style="color: #00d9ff;"' : '' ?>>الرئيسية</a>
    <?php if ($is_admin === 'admin'): ?>
      <a href="/admin_dashboard.php" <?= $current_page === 'admin_dashboard.php' ? 'style="color: #00d9ff;"' : '' ?>>لوحة التحكم</a>
      <a href="/admin_tickets.php" <?= $current_page === 'admin_tickets.php' ? 'style="color: #00d9ff;"' : '' ?>>التذاكر</a>
      <a href="/admin_users.php" <?= $current_page === 'admin_users.php' ? 'style="color: #00d9ff;"' : '' ?>>المستخدمين</a>
    <?php else: ?>
      <a href="/services.php" <?= $current_page === 'services.php' ? 'style="color: #00d9ff;"' : '' ?>>خدماتنا</a>
      <a href="/tickets.php" <?= $current_page === 'tickets.php' ? 'style="color: #00d9ff;"' : '' ?>>تذاكري</a>
      <a href="/profile.php" <?= $current_page === 'profile.php' ? 'style="color: #00d9ff;"' : '' ?>>الملف الشخصي</a>
    <?php endif; ?>
    <a href="mailto:raedfss@hotmail.com">اتصل بنا</a>
    <?php if (isset($_SESSION['email'])): ?>
      <a href="/logout.php" style="color: #ff6b6b;"><i class="fas fa-sign-out-alt"></i> خروج</a>
    <?php else: ?>
      <a href="/login.php" <?= $current_page === 'login.php' ? 'style="color: #00d9ff;"' : '' ?>><i class="fas fa-sign-in-alt"></i> دخول</a>
    <?php endif; ?>
  </div>
</header>

<div class="content-wrapper">
  <main>
    <?php if (($show_diagnostic ?? false) && $is_admin === 'admin'): ?>
      <div class="diagnostic-container">
        <div class="diagnostic-col">
          <h3>DIAGNOSTIC RUNNING</h3>
          <p>MODEL TYPE: PRECISION GT</p>
          <p>ECU VERSION: 4.21.0</p>
          <p>STATUS: OPTIMIZING...</p>
        </div>
        <div class="diagnostic-col">
          <h3>DIAGNOSTIC CODE: P4E27</h3>
          <p>MODULE: ENGINE CONTROL</p>
          <p>RESOLUTION: IN PROGRESS 42%</p>
        </div>
      </div>
    <?php endif; ?>

    <?php if (isset($page_title) && !($hide_title ?? false)): ?>
      <h2><?= htmlspecialchars($display_title ?? $page_title) ?></h2>
    <?php endif; ?>

    <?php if (isset($_GET['status'])):
      $status = htmlspecialchars($_GET['status']);
      $alert_class = '';
      $alert_message = '';
      $icon = 'info-circle';

      switch ($status) {
        case 'success': $alert_class = 'success'; $alert_message = 'تمت العملية بنجاح'; $icon = 'check-circle'; break;
        case 'error': $alert_class = 'error'; $alert_message = 'حدث خطأ أثناء العملية'; $icon = 'exclamation-circle'; break;
        case 'updated': $alert_class = 'success'; $alert_message = 'تم تحديث البيانات بنجاح'; $icon = 'check-circle'; break;
        case 'deleted': $alert_class = 'warning'; $alert_message = 'تم حذف العنصر بنجاح'; $icon = 'exclamation-triangle'; break;
      }

      if ($alert_message): ?>
        <div class="alert alert-<?= $alert_class ?>">
          <i class="fas fa-<?= $icon ?>"></i> <?= $alert_message ?>
        </div>
    <?php endif; endif; ?>

    <?= $notification ?? '' ?>
    <?= $content_start ?? '' ?>
    
    <!-- هنا يأتي محتوى الصفحة عن طريق PHP Include -->
    <?= $page_content ?? '' ?>
    
  </main>

  <footer>
    <div class="footer-highlight">ذكاءٌ في الخدمة، سرعةٌ في الاستجابة، جودةٌ بلا حدود.</div>
    <div>Smart service, fast response, unlimited quality.</div>
    <div>📧 raedfss@hotmail.com | ☎️ +962796519007</div>
    <div>&copy; <?= date('Y') ?> FlexAuto. جميع الحقوق محفوظة.</div>
  </footer>
</div>

<?php if (isset($page_js)): ?>
<script>
  <?= $page_js ?>
</script>
<?php endif; ?>

</body>
</html>