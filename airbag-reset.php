<?php
session_start();

// 1) الاتصال بقاعدة البيانات (PDO)
require_once __DIR__ . '/includes/db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
$user_type = $_SESSION['user_role'] ?? 'user';
$email = $_SESSION['email'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// استيراد الدوال المساعدة والأمان
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

// إنشاء توكن CSRF لحماية النموذج
$csrf_token = generateCSRFToken();

// إعداد عنوان الصفحة
$page_title = 'طلب مسح بيانات الحادث (Airbag Reset)';
$display_title = 'طلب مسح بيانات الحادث (Airbag Reset)';

// تهيئة رسائل التنفيذ
$success = '';
$error = '';

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من توكن CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "❌ فشل التحقق من الأمان. يرجى تحديث الصفحة والمحاولة مرة أخرى.";
    } else {
        // جلب القيم مع تنظيفها
        $vehicle_type = sanitizeInput($_POST['vehicle_type'] ?? '');
        $ecu_number = sanitizeInput($_POST['ecu_number'] ?? '');
        $file = $_FILES['eeprom_file'] ?? null;

        // التحقق من اكتمال الحقول
        if (empty($vehicle_type) || empty($ecu_number) || !$file || $file['error'] !== UPLOAD_ERR_OK) {
            $error = "❌ جميع الحقول مطلوبة.";
        } else {
            // فحص الامتداد والحجم بشكل آمن
            $allowed_exts = ['bin', 'hex'];
            $file_info = pathinfo($file['name']);
            $ext = strtolower($file_info['extension'] ?? '');

            // التحقق من الامتداد
            if (!in_array($ext, $allowed_exts, true)) {
                $error = "❌ الملف غير مدعوم. يجب أن يكون بصيغة .bin أو .hex فقط.";
            } 
            // التحقق من الحجم
            elseif ($file['size'] > 2 * 1024 * 1024) {
                $error = "❌ حجم الملف كبير. الحد الأقصى المسموح هو 2 ميجابايت.";
            } 
            // التحقق من نوع MIME الفعلي
            elseif (!validateBinaryFile($file['tmp_name'], $ext)) {
                $error = "❌ محتوى الملف غير صالح.";
            }
            else {
                // توليد اسم فريد وآمن للملف
                $filename = secureFileName(uniqid('eeprom_', true) . '.' . $ext);
                $upload_dir = __DIR__ . '/uploads/';
                $destination = $upload_dir . $filename;

                // التأكد من وجود المجلد وحقوق الكتابة
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $error = "❌ فشل في إنشاء مجلد الرفع. الرجاء الاتصال بالمسؤول.";
                    }
                }

                if (empty($error)) {
                    // إضافة تحقق من حقوق الكتابة
                    if (!is_writable($upload_dir)) {
                        $error = "❌ المجلد غير قابل للكتابة. الرجاء الاتصال بالمسؤول.";
                    } else {
                        if (move_uploaded_file($file['tmp_name'], $destination)) {
                            try {
                                // إدخال السجل في قاعدة البيانات مع تنفيذ آمن للاستعلام
                                $stmt = $pdo->prepare("
                                    INSERT INTO airbag_resets (user_id, ecu_number, vehicle_type, uploaded_file, created_at)
                                    VALUES (:uid, :ecu, :veh, :file, NOW())
                                ");
                                $stmt->execute([
                                    ':uid'  => (int)$user_id,
                                    ':ecu'  => $ecu_number,
                                    ':veh'  => $vehicle_type,
                                    ':file' => $filename
                                ]);

                                // سجل العملية في سجل الأحداث
                                logActivity('airbag_reset', 'تم إرسال طلب مسح بيانات Airbag', $user_id);
                                
                                $success = "✅ تم إرسال طلب مسح بيانات Airbag بنجاح.";
                                
                                // إعادة تعيين المتغيرات لمنع إعادة الإرسال
                                $vehicle_type = '';
                                $ecu_number = '';
                            } catch (PDOException $e) {
                                // معالجة خطأ قاعدة البيانات بشكل آمن
                                $error = "❌ حدث خطأ في قاعدة البيانات. الرجاء المحاولة مرة أخرى.";
                                // تسجيل الخطأ للمراجعة من قبل المسؤول
                                logError('Database error in airbag_reset: ' . $e->getMessage());
                                
                                // حذف الملف في حالة فشل الإدخال في قاعدة البيانات
                                if (file_exists($destination)) {
                                    unlink($destination);
                                }
                            }
                        } else {
                            $error = "❌ فشل في رفع الملف. الرجاء المحاولة مرة أخرى.";
                        }
                    }
                }
            }
        }
    }
}

// تحديث توكن CSRF بعد المعالجة
$csrf_token = generateCSRFToken();

// CSS مخصص للصفحة
$page_css = <<<CSS
.container {
  background: rgba(0, 0, 0, 0.7);
  padding: 35px;
  width: 90%;
  max-width: 880px;
  border-radius: 16px;
  text-align: center;
  margin: 30px auto;
  box-shadow: 0 0 40px rgba(0, 200, 255, 0.15);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(66, 135, 245, 0.25);
}
.form {
  max-width: 600px;
  margin: 0 auto;
  text-align: right;
}
.form-group {
  margin-bottom: 20px;
}
.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: bold;
  color: #a8d8ff;
}
.form-group input {
  width: 100%;
  padding: 12px;
  border-radius: 8px;
  border: 1px solid rgba(66, 135, 245, 0.4);
  background: rgba(0, 40, 80, 0.4);
  color: white;
  box-sizing: border-box;
}
.form-group input[type="file"] {
  padding: 8px;
  background: rgba(0, 40, 80, 0.4);
}
.form-text {
  font-size: 0.8rem;
  color: #aaa;
  margin-top: 5px;
}
.btn {
  padding: 12px 25px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: bold;
  transition: 0.3s;
  margin-top: 10px;
}
.btn-primary {
  background: linear-gradient(145deg, #1e90ff, #0070cc);
  color: white;
  box-shadow: 0 4px 10px rgba(0,0,0,0.3);
}
.btn-primary:hover {
  background: linear-gradient(145deg, #2eaaff, #0088ff);
  transform: translateY(-2px);
}
.alert {
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
  position: relative;
}
.alert-danger {
  background: rgba(220, 53, 69, 0.2);
  border: 1px solid rgba(220, 53, 69, 0.5);
  color: #ff6b6b;
}
.alert-success {
  background: rgba(40, 167, 69, 0.2);
  border: 1px solid rgba(40, 167, 69, 0.5);
  color: #75ff75;
}
.alert-info {
  background: rgba(23, 162, 184, 0.2);
  border: 1px solid rgba(23, 162, 184, 0.5);
  color: #5dccff;
}
.alert-dismissible .btn-close {
  position: absolute;
  top: 0;
  right: 0;
  padding: 15px;
  color: inherit;
  background: transparent;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
}
CSS;

// تعريف محتوى الصفحة
ob_start();
?>
<div class="container">
    <h2><?= $display_title ?></h2>

    <?php
    // عرض رسائل الخطأ أو النجاح
    if ($error)   showMessage('danger', $error);
    if ($success) showMessage('success', $success);
    ?>

    <form method="POST" enctype="multipart/form-data" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="form">
        <!-- توكن CSRF -->
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        
        <div class="form-group">
            <label for="vehicle_type">نوع السيارة:</label>
            <input type="text" id="vehicle_type" name="vehicle_type" required
                   maxlength="100"
                   value="<?= htmlspecialchars($vehicle_type ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
            <label for="ecu_number">رقم وحدة ECU:</label>
            <input type="text" id="ecu_number" name="ecu_number" required
                   maxlength="50"
                   pattern="[A-Za-z0-9-.]+"
                   title="يرجى إدخال أرقام وحروف وعلامات - و . فقط"
                   value="<?= htmlspecialchars($ecu_number ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
            <label for="eeprom_file">ملف EEPROM (.bin أو .hex):</label>
            <input type="file" id="eeprom_file" name="eeprom_file" accept=".bin,.hex" required>
            <small class="form-text text-muted">الحد الأقصى لحجم الملف: 2 ميجابايت</small>
        </div>

        <button type="submit" class="btn btn-primary">إرسال الطلب</button>
    </form>
    
    <!-- عرض تحذير أمان للمستخدم -->
    <div class="alert alert-info mt-4">
        <strong>ملاحظة:</strong> يرجى التأكد من صحة الملف المرفوع، حيث سيتم التعامل معه من قبل الفريق الفني.
    </div>
</div>
<?php
$page_content = ob_get_clean();

// إدراج القالب
include __DIR__ . '/includes/layout.php';
?>