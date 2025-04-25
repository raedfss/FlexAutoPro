<?php
session_start();

// 1) التحقق من المصادقة/تسجيل الدخول
require_once __DIR__ . '/includes/auth.php';

// 2) الاتصال بقاعدة البيانات (PDO)
require_once __DIR__ . '/includes/db.php';

// 3) دوال مساعدة (showMessage, formatDate, ...)
require_once __DIR__ . '/includes/functions.php';

// 4) الهيدر العام
require_once __DIR__ . '/includes/header.php';

// تهيئة رسائل التنفيذ
$success = '';
$error   = '';

// 5) معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // جلب القيم مع trim لتنظيفها
    $vehicle_type = trim($_POST['vehicle_type'] ?? '');
    $ecu_number   = trim($_POST['ecu_number']   ?? '');
    $file         = $_FILES['eeprom_file']      ?? null;

    // التحقق من اكتمال الحقول
    if ($vehicle_type === '' || $ecu_number === '' || !$file) {
        $error = "❌ جميع الحقول مطلوبة.";
    } else {
        // فحص الامتداد والحجم
        $allowed_exts = ['bin', 'hex'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_exts, true)) {
            $error = "❌ الملف غير مدعوم. يجب أن يكون بصيغة .bin أو .hex فقط.";
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $error = "❌ حجم الملف كبير. الحد الأقصى المسموح هو 2 ميجابايت.";
        } else {
            // توليد اسم فريد
            $filename    = uniqid('eeprom_', true) . '.' . $ext;
            $upload_dir  = __DIR__ . '/uploads/';
            $destination = $upload_dir . $filename;

            // التأكد من وجود المجلد وحقوق الكتابة
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // إدخال السجل في قاعدة البيانات
                $stmt = $pdo->prepare("
                    INSERT INTO airbag_resets (user_id, ecu_number, vehicle_type, uploaded_file, created_at)
                    VALUES (:uid, :ecu, :veh, :file, NOW())
                ");
                $stmt->execute([
                    ':uid'  => $_SESSION['user_id'],
                    ':ecu'  => $ecu_number,
                    ':veh'  => $vehicle_type,
                    ':file' => $filename
                ]);

                $success = "✅ تم إرسال طلب مسح بيانات Airbag بنجاح.";
            } else {
                $error = "❌ فشل في رفع الملف. الرجاء المحاولة مرة أخرى.";
            }
        }
    }
}
?>

<div class="container">
    <h2>طلب مسح بيانات الحادث (Airbag Reset)</h2>

    <?php
    // عرض رسائل الخطأ أو النجاح
    if ($error)   showMessage('danger', $error);
    if ($success) showMessage('success', $success);
    ?>

    <form method="POST" enctype="multipart/form-data" action="airbag_reset.php" class="form">
        <div class="form-group">
            <label for="vehicle_type">نوع السيارة:</label>
            <input type="text" id="vehicle_type" name="vehicle_type" required
                   value="<?= htmlspecialchars($_POST['vehicle_type'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
            <label for="ecu_number">رقم وحدة ECU:</label>
            <input type="text" id="ecu_number" name="ecu_number" required
                   value="<?= htmlspecialchars($_POST['ecu_number'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
            <label for="eeprom_file">ملف EEPROM (.bin أو .hex):</label>
            <input type="file" id="eeprom_file" name="eeprom_file" accept=".bin,.hex" required>
        </div>

        <button type="submit" class="btn btn-primary">إرسال الطلب</button>
    </form>
</div>

<?php
// 6) الفوتر العام
require_once __DIR__ . '/includes/footer.php';
?>
