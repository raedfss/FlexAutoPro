<?php
session_start();

// 1) التأكد من تسجيل الدخول وصلاحية المستخدم
require_once __DIR__ . '/includes/auth.php';
if (!isset($_SESSION['email']) || $_SESSION['user_type'] !== 'user') {
    header("Location: login.php");
    exit;
}

// 2) الاتصال بقاعدة البيانات (PDO)
require_once __DIR__ . '/includes/db.php';

// 3) الدوال المساعدة (showMessage)
require_once __DIR__ . '/includes/functions.php';

// 4) الهيدر العام
require_once __DIR__ . '/includes/header.php';

// تهيئة رسائل الخطأ والنجاح
$error   = '';
$success = '';

// 5) معالجة الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $car_type   = trim($_POST['car_type']   ?? '');
    $ecu_number = trim($_POST['ecu_number'] ?? '');
    $file       = $_FILES['ecu_file']       ?? null;

    // التحقق من اكتمال الحقول
    if ($car_type === '' || $ecu_number === '' || !$file) {
        $error = "❌ جميع الحقول مطلوبة.";
    } else {
        // فحص الامتداد والحجم
        $allowed = ['bin','hex','zip'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed, true)) {
            $error = "❌ الملف غير مدعوم. صيغ مسموحة: bin, hex, zip.";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = "❌ حجم الملف كبير؛ الحد الأقصى 5 ميجابايت.";
        } else {
            // تحضير اسم فريد ومسار الرفع
            $uploadDir  = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $filename   = uniqid('airbag_', true) . '.' . $ext;
            $dest       = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                // إدخال سجل جديد
                $stmt = $pdo->prepare("
                    INSERT INTO airbag_resets 
                    (user_id, vehicle_type, ecu_number, uploaded_file, created_at)
                    VALUES (:uid, :veh, :ecu, :file, NOW())
                ");
                $stmt->execute([
                    ':uid'  => $_SESSION['user_id'],
                    ':veh'  => $car_type,
                    ':ecu'  => $ecu_number,
                    ':file' => $filename
                ]);
                $success = "✅ تم إرسال طلب مسح بيانات الحادث بنجاح.";
            } else {
                $error = "❌ فشل رفع الملف، حاول مجددًا.";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مسح بيانات الحوادث | FlexAuto</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* تضمين ملف الـ CSS */
        <?php include __DIR__ . '/style_home.css'; ?>
    </style>
</head>
<body>

<main class="form-container">
    <h1>مرحبًا <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES) ?>!</h1>
    <h2>💥 ارفع بيانات وحدة التحكم لمسح بيانات الحادث</h2>

    <?php
    if ($error)   showMessage('danger', $error);
    if ($success) showMessage('success', $success);
    ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
        <div class="form-group">
            <label for="car_type">نوع السيارة:</label>
            <input type="text" id="car_type" name="car_type" required
                   value="<?= htmlspecialchars($_POST['car_type'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
            <label for="ecu_number">رقم وحدة التحكم (ECU):</label>
            <input type="text" id="ecu_number" name="ecu_number" required
                   value="<?= htmlspecialchars($_POST['ecu_number'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
            <label for="ecu_file">تحميل ملف البيانات (bin/hex/zip):</label>
            <input type="file" id="ecu_file" name="ecu_file" accept=".bin,.hex,.zip" required>
        </div>

        <button type="submit" class="btn-submit">إرسال الطلب</button>
    </form>

    <p class="logout"><a href="logout.php">🔓 تسجيل الخروج</a></p>
</main>

<?php
// 6) تضمين الفوتر
require_once __DIR__ . '/includes/footer.php';
?>

</body>
</html>
