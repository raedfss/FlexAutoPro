<?php
// التحقق من وجود جلسة نشطة قبل بدء جلسة جديدة
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// تعيين عنوان الصفحة إذا لم يكن موجوداً
if (!isset($pageTitle)) {
    $pageTitle = "FlexAuto - خدمات برمجة السيارات";
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="FlexAuto - خدمات برمجة السيارات وحلول تقنية متقدمة">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <!-- Bootstrap RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.rtl.min.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts - Cairo -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        :root {
            --primary: #004080;
            --primary-light: #0066cc;
            --primary-dark: #003366;
            --secondary: #00d9ff;
            --light: #f8f9fa;
            --dark: #212529;
        }
        body {
            font-family: 'Cairo', 'Tahoma', sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        /* الهيدر */
        .site-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .header-container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        .logo-icon {
            font-size: 32px;
            margin-left: 10px;
            color: var(--secondary);
        }
        .logo-text {
            font-size: 24px;
            font-weight: 700;
            color: white;
        }
        .site-title {
            margin: 0;
            font-size: 32px;
            text-align: center;
            font-weight: 700;
            color: white;
        }
        .site-subtitle {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 5px;
            text-align: center;
        }
        .main-nav {
            width: 100%;
            margin-top: 10px;
        }
        .nav-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
        }
        .nav-item {
            margin: 5px 10px;
        }
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 4px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            font-weight: 600;
        }
        .nav-icon {
            font-size: 16px;
        }
        .user-name {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        .main-content {
            flex: 1;
            width: 90%;
            max-width: 1200px;
            margin: 30px auto;
            padding-bottom: 30px;
        }
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        @media (min-width: 768px) {
            .header-container {
                flex-direction: row;
                justify-content: space-between;
            }
            .logo-container {
                margin-bottom: 0;
            }
            .main-nav {
                width: auto;
                margin-top: 0;
            }
            .nav-list {
                justify-content: flex-end;
            }
        }
        @media (max-width: 767px) {
            .site-title {
                font-size: 24px;
            }
            .site-subtitle {
                font-size: 14px;
            }
            .nav-item {
                margin: 3px 5px;
            }
            .nav-link {
                padding: 6px 10px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="header-container">
        <div class="logo-container">
            <i class="fas fa-car-alt logo-icon"></i>
            <div>
                <div class="logo-text">FlexAuto</div>
                <div class="site-subtitle">خدمات برمجة السيارات</div>
            </div>
        </div>

        <nav class="main-nav">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="../index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home nav-icon"></i> الرئيسية
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../services.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cogs nav-icon"></i> خدماتنا
                    </a>
                </li>
                <?php if (isset($_SESSION['username'])): ?>
                    <li class="nav-item">
                        <a href="my_tickets.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'my_tickets.php' ? 'active' : ''; ?>">
                            <i class="fas fa-ticket-alt nav-icon"></i> تذاكري
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user nav-icon"></i> <span class="user-name"><?php echo $_SESSION['username']; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt nav-icon"></i> تسجيل الخروج
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a href="../login.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : ''; ?>">
                            <i class="fas fa-sign-in-alt nav-icon"></i> تسجيل الدخول
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../register.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'register.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user-plus nav-icon"></i> التسجيل
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<div class="main-content">
<!-- محتوى الصفحة يأتي هنا -->
