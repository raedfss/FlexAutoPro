<?php
// FlexAutoPro - includes/security.php
// مكتبة وظائف الأمان والحماية

// تأكد من بدء الجلسة ديناميكيًا في كل الدوال التي تستخدمها
// بدلاً من التحقق مرة واحدة في أعلى الملف

/**
 * توليد توكن CSRF لحماية النماذج
 * @return string
 */
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        // التحقق من بدء الجلسة
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // إنشاء توكن جديد إذا لم يكن موجودًا
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
}

/**
 * التحقق من صحة توكن CSRF
 * @param string $token التوكن المرسل من النموذج
 * @return bool
 */
if (!function_exists('validateCSRFToken')) {
    function validateCSRFToken($token) {
        // التحقق من بدء الجلسة
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // التحقق من وجود التوكن في الجلسة وصحته
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        
        // مقارنة التوكن المرسل بالتوكن المخزن
        $valid = hash_equals($_SESSION['csrf_token'], $token);
        
        // تجديد التوكن بعد التحقق للحماية من هجمات CSRF المتكررة
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        return $valid;
    }
}

/**
 * تنظيف وتأمين المدخلات
 * @param string $input المدخل المراد تنظيفه
 * @return string
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input) {
        // إزالة المسافات الزائدة
        $input = trim($input);
        // تحويل رموز HTML إلى نصوص
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        return $input;
    }
}

/**
 * تسجيل نشاط المستخدم
 * @param string $activity_type نوع النشاط
 * @param string $description وصف النشاط
 * @param int|null $user_id معرف المستخدم (اختياري، يستخدم المستخدم الحالي إذا لم يُعطى)
 * @return bool
 */
if (!function_exists('logActivity')) {
    function logActivity($activity_type, $description, $user_id = null) {
        global $pdo;
        
        // تأكد من بدء الجلسة
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // استخدم معرف المستخدم الحالي إذا لم يتم تحديده
        if ($user_id === null && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        
        try {
            // التحقق من وجود اتصال بقاعدة البيانات
            if (!isset($pdo) || !($pdo instanceof PDO)) {
                // إذا لم يكن هناك اتصال، نخزن السجل في ملف
                $log_message = date('Y-m-d H:i:s') . " | User ID: $user_id | $activity_type | $description\n";
                $log_dir = __DIR__ . '/../logs';
                if (!is_dir($log_dir)) {
                    mkdir($log_dir, 0755, true);
                }
                file_put_contents($log_dir . '/activity.log', $log_message, FILE_APPEND);
                return true;
            }
            
            // إدخال السجل في قاعدة البيانات
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, activity_type, description, ip_address, created_at)
                VALUES (:uid, :type, :desc, :ip, NOW())
            ");
            
            // الحصول على عنوان IP بطريقة آمنة
            $ip_address = '0.0.0.0';
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $ip_address = $_SERVER['REMOTE_ADDR'];
            }
            
            return $stmt->execute([
                ':uid'  => $user_id,
                ':type' => $activity_type,
                ':desc' => $description,
                ':ip'   => $ip_address
            ]);
        } catch (Exception $e) {
            // تسجيل الخطأ في ملف
            logError('Error in logActivity: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * تسجيل خطأ في النظام
 * @param string $error_message رسالة الخطأ
 * @return bool
 */
if (!function_exists('logError')) {
    function logError($error_message) {
        // تأكد من بدء الجلسة
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // التأكد من وجود مجلد السجلات
        $log_dir = __DIR__ . '/../logs';
        if (!is_dir($log_dir)) {
            if (!mkdir($log_dir, 0755, true)) {
                // إذا فشل إنشاء المجلد، استخدم مجلد PHP المؤقت
                $log_dir = sys_get_temp_dir();
            }
        }
        
        // الحصول على عنوان IP بطريقة آمنة
        $ip_address = '0.0.0.0';
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip_address = $_SERVER['REMOTE_ADDR'];
        }
        
        // الحصول على اسم المستخدم بطريقة آمنة
        $username = 'غير مسجل';
        if (isset($_SESSION['username'])) {
            $username = $_SESSION['username'];
        }
        
        // تنسيق رسالة الخطأ
        $log_entry = date('Y-m-d H:i:s') . ' | ' . $ip_address;
        $log_entry .= ' | ' . $username;
        $log_entry .= ' | ' . $error_message . "\n";
        
        // كتابة الخطأ في ملف
        return file_put_contents($log_dir . '/error.log', $log_entry, FILE_APPEND) !== false;
    }
}

/**
 * التحقق من صحة ملف ثنائي
 * @param string $file_path مسار الملف
 * @param string $ext امتداد الملف
 * @return bool
 */
if (!function_exists('validateBinaryFile')) {
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
}

/**
 * تأمين اسم الملف
 * @param string $filename اسم الملف
 * @return string اسم الملف المؤمن
 */
if (!function_exists('secureFileName')) {
    function secureFileName($filename) {
        // استبدال الأحرف غير الآمنة
        $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
        
        // منع الملفات التي تبدأ بنقطة
        if (substr($filename, 0, 1) === '.') {
            $filename = 'file_' . $filename;
        }
        
        return $filename;
    }
}

/**
 * عرض رسالة للمستخدم
 * @param string $type نوع الرسالة (success, danger, warning, info)
 * @param string $message نص الرسالة
 * @return void
 */
if (!function_exists('showMessage')) {
    function showMessage($type, $message) {
        echo '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($message);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>';
        echo '</div>';
    }
}