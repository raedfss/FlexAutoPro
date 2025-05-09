<?php
session_start();

// 1) التحقق من المصادقة/تسجيل الدخول
require_once __DIR__ . '/includes/auth.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "يجب تسجيل الدخول للوصول إلى هذه الصفحة";
    $_SESSION['message_type'] = "error";
    header("Location: login.php");
    exit;
}


// التحقق من مستوى صلاحيات المستخدم (إذا كان مطلوبًا)
if (!hasPermission('airbag_reset')) {
    // إعادة التوجيه إلى الصفحة الرئيسية مع رسالة خطأ
    $_SESSION['message'] = "ليس لديك صلاحية للوصول لهذه الصفحة";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit;
}

// 2) الاتصال بقاعدة البيانات (PDO)
require_once __DIR__ . '/includes/db.php';

// 3) دوال مساعدة (showMessage, formatDate, ...)
require_once __DIR__ . '/includes/functions.php';

// 4) مكتبة التحقق من المدخلات والحماية
require_once __DIR__ . '/includes/security.php';

// إنشاء توكن CSRF لحماية النموذج
$csrf_token = generateCSRFToken();

// 4) الهيدر العام
require_once __DIR__ . '/includes/header.php';

// تهيئة رسائل التنفيذ
$success = '';
$error   = '';

// 5) معالجة إرسال النموذج
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
            // التحقق من نوع MIME الفعلي (يتطلب إضافة وظيفة للتحقق من الملفات الثنائية)
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
                                    ':uid'  => (int)$_SESSION['user_id'],
                                    ':ecu'  => $ecu_number,
                                    ':veh'  => $vehicle_type,
                                    ':file' => $filename
                                ]);

                                // سجل العملية في سجل الأحداث
                                logActivity('airbag_reset', 'تم إرسال طلب مسح بيانات Airbag', $_SESSION['user_id']);
                                
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
?>

<div class="container">
    <h2>طلب مسح بيانات الحادث (Airbag Reset)</h2>

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
// 6) الفوتر العام
require_once __DIR__ . '/includes/footer.php';

/**
 * دالة للتحقق من صلاحية الملفات الثنائية
 * @param string $file_path مسار الملف المؤقت
 * @param string $ext امتداد الملف
 * @return bool
 */
function validateBinaryFile($file_path, $ext) {
    // التحقق من وجود الملف
    if (!file_exists($file_path)) {
        return false;
    }
    
    // فحص أساسي للبيانات الثنائية
    if ($ext === 'bin') {
        // التحقق من أن الملف بصيغة ثنائية صالحة
        $fileContent = file_get_contents($file_path);
        if ($fileContent === false || strlen($fileContent) < 10) {
            return false;
        }
        
        // يمكن إضافة فحوصات إضافية للتأكد من صحة الملف
        return true;
    }
    
    if ($ext === 'hex') {
        // فحص بسيط لصيغة ملف HEX
        $content = file_get_contents($file_path);
        if ($content === false) {
            return false;
        }
        
        // التحقق من أن الملف يحتوي على سطور HEX صالحة
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // نمط الخط في ملف HEX (عادة يبدأ بـ :)
            if (!preg_match('/^:[0-9A-Fa-f]{8,}$/', $line)) {
                return false;
            }
        }
        
        return true;
    }
    
    return false;
}

/**
 * دالة لتأمين اسم الملف
 * @param string $filename اسم الملف
 * @return string اسم الملف المؤمن
 */
function secureFileName($filename) {
    // استبدال الأحرف غير الآمنة
    $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
    
    // منع الملفات التي تبدأ بنقطة
    if (substr($filename, 0, 1) === '.') {
        $filename = 'file_' . $filename;
    }
    
    return $filename;
}