<?php
session_start();

// 1) المصادقة – تأكد أن المستخدم مسجل وله النوع 'user'
require_once __DIR__ . '/includes/auth.php';
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'user') {
    header('Location: login.php');
    exit;
}

// 2) الاتصال بقاعدة البيانات (PDO)
require_once __DIR__ . '/includes/db.php';

// 3) الدوال المساعدة (showMessage)
require_once __DIR__ . '/includes/functions.php';

// 4) تضمين الهيدر العام
require_once __DIR__ . '/includes/header.php';

// تهيئة رسائل الخطأ والنجاح
$error   = '';
$success = '';

// 5) معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ecu_model     = trim($_POST['ecu_model']     ?? '');
    $modifications = trim($_POST['modifications'] ?? '');
    $file          = $_FILES['ecu_file']         ?? null;

    // التحقق من اكتمال الحقول
    if ($ecu_model === '' || $modifications === '' || !$file) {
        $error = "❌ جميع الحقول مطلوبة.";
    } else {
        // فحص الامتداد والحجم
        $allowed_exts = ['bin','hex','ori','mod'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_exts, true)) {
            $error = "❌ الملف غير مدعوم. الصيغ المسموحة: bin, hex, ori, mod.";
        }
        elseif ($file['size'] > 3 * 1024 * 1024) {
            $error = "❌ حجم الملف كبير؛ الحد الأقصى 3 ميجابايت.";
        }
        else {
            // إعداد مسار الرفع
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // توليد اسم فريد للملف
            $filename = uniqid('ecu_', true) . '.' . $ext;
            $destination = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // حفظ الطلب في قاعدة البيانات
                $stmt = $pdo->prepare("
                    INSERT INTO ecu_tuning_requests
                      (user_id, ecu_model, modifications, uploaded_file, created_at)
                    VALUES
                      (:uid, :model, :mods, :file, NOW())
                ");
                $stmt->execute([
                    ':uid'    => $_SESSION['user_id'],
                    ':model'  => $ecu_model,
                    ':mods'   => $modifications,
                    ':file'   => $filename
                ]);

                $success = "✅ تم إرسال طلب تعديل برمجة وحدة ECU بنجاح.";
            } else {
                $error = "❌ فشل في رفع الملف، الرجاء المحاولة مرة أخرى.";
            }
        }
    }
}
?>

<div class="container">
    <h2>⚙️ طلب تعديل برمجة وحدة ECU</h2>

    <?php
    // عرض رسائل
    if ($error)   showMessage('danger', $error);
    if ($success) showMessage('success', $success);
    ?>

    <form method="POST" enctype="multipart/form-data" class="form-style">
        <div class="form-group">
            <label for="ecu_model">موديل وحدة ECU:</label>
            <input type="text" id="ecu_model" name="ecu_model" required
                   value="<?= htmlspecialchars($_POST['ecu_model'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
            <label for="modifications">التعديلات المطلوبة:</label>
            <textarea id="modifications" name="modifications" rows="4" required><?= htmlspecialchars($_POST['modifications'] ?? '', ENT_QUOTES) ?></textarea>
        </div>

        <div class="form-group">
            <label for="ecu_file">ملف ECU (.bin, .hex, .ori, .mod):</label>
            <input type="file" id="ecu_file" name="ecu_file"
                   accept=".bin,.hex,.ori,.mod" required>
        </div>

        <button type="submit" class="btn-submit">إرسال الطلب</button>
    </form>
</div>

<?php
// 6) تضمين الفوتر العام
require_once __DIR__ . '/includes/footer.php';
?>
