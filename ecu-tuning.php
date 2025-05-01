<?php
// بدء الجلسة قبل أي إخراج
session_start();
require_once __DIR__ . '/includes/db.php';

// وظيفة لتنظيف المدخلات
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

// إنشاء أو استعادة توكن CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// تعريف user_type بشكل آمن لمنع التحذير
$user_type = isset($_SESSION['user_type']) ? sanitize_input($_SESSION['user_type']) : '';

// إعدادات الصفحة
$page_title = "تعديل برمجيات ECU";
$hide_title = true;
$success_message = '';
$error_messages = [];

// متغيرات للاحتفاظ بالمدخلات في حالة الخطأ
$car_type = '';
$chassis = '';
$tuning_type = '';
$notes = '';

// معالجة الطلب
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من توكن CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_messages[] = "خطأ في التحقق من الأمان. يرجى المحاولة مرة أخرى.";
    } else {
        // تنظيف المدخلات
        $car_type = sanitize_input($_POST['car_type'] ?? '');
        $chassis = sanitize_input($_POST['chassis'] ?? '');
        $tuning_type = sanitize_input($_POST['tuning_type'] ?? '');
        $notes = sanitize_input($_POST['notes'] ?? '');
        $file_uploaded = false;
        $file_path = '';

        // تحقق من الحقول
        if (empty($car_type)) {
            $error_messages[] = "يرجى إدخال نوع السيارة";
        }
        
        if (empty($chassis)) {
            $error_messages[] = "يرجى إدخال رقم الشاصي";
        } elseif (strlen($chassis) !== 17) {
            $error_messages[] = "رقم الشاصي يجب أن يتكون من 17 خانة بالضبط";
        } elseif (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $chassis)) {
            $error_messages[] = "رقم الشاصي يحتوي على أحرف غير صالحة";
        }
        
        if (empty($tuning_type)) {
            $error_messages[] = "يرجى اختيار نوع التعديل";
        } elseif (!in_array($tuning_type, ['Stage 1', 'Stage 2', 'Stage 3', 'Eco'])) {
            $error_messages[] = "نوع التعديل غير صالح";
        }

        // معالجة الملف إذا تم رفعه
        if (isset($_FILES['ecu_file']) && $_FILES['ecu_file']['error'] === UPLOAD_ERR_OK) {
            // التحقق من نوع الملف
            $allowed_extensions = ['bin', 'hex', 'zip', 'rar', 'pdf'];
            $file_extension = strtolower(pathinfo($_FILES['ecu_file']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_extension, $allowed_extensions)) {
                $error_messages[] = "نوع الملف غير مسموح به. الأنواع المسموحة: " . implode(', ', $allowed_extensions);
            } else {
                // التحقق من حجم الملف (10MB كحد أقصى)
                if ($_FILES['ecu_file']['size'] > 10 * 1024 * 1024) {
                    $error_messages[] = "حجم الملف كبير جدًا. الحد الأقصى هو 10 ميجابايت";
                } else {
                    $target_dir = __DIR__ . "/uploads/ecu_files/";
                    
                    // إنشاء المجلد إذا لم يكن موجودًا
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }
                    
                    // إنشاء اسم ملف آمن وفريد
                    $unique_name = 'ecu_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
                    $target_file = $target_dir . $unique_name;
                    
                    if (move_uploaded_file($_FILES['ecu_file']['tmp_name'], $target_file)) {
                        $file_uploaded = true;
                        $file_path = 'uploads/ecu_files/' . $unique_name;
                    } else {
                        $error_messages[] = "حدث خطأ أثناء رفع الملف";
                    }
                }
            }
        }

        // إذا لا توجد أخطاء، أدخل التذكرة في قاعدة البيانات
        if (empty($error_messages)) {
            try {
                $username = sanitize_input($_SESSION['username'] ?? 'مستخدم');
                $email = filter_var($_SESSION['email'] ?? '', FILTER_SANITIZE_EMAIL);
                $phone = sanitize_input($_SESSION['phone'] ?? '');
                $service_type = "تعديل ECU: " . $tuning_type;

                // تحقق من وجود حقل status في جدول tickets
                $check_column = $pdo->query("
                    SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_name = 'tickets' AND column_name = 'status'
                ");
                
                $has_status_column = $check_column->rowCount() > 0;

                // بناء استعلام الإدراج
                if ($has_status_column) {
                    $stmt = $pdo->prepare("
                        INSERT INTO tickets 
                        (username, email, phone, car_type, chassis, service_type, notes, file_path, status, created_at, ip_address) 
                        VALUES 
                        (:username, :email, :phone, :car_type, :chassis, :service_type, :notes, :file_path, 'pending', NOW(), :ip_address)
                    ");
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO tickets 
                        (username, email, phone, car_type, chassis, service_type, notes, file_path, created_at, ip_address) 
                        VALUES 
                        (:username, :email, :phone, :car_type, :chassis, :service_type, :notes, :file_path, NOW(), :ip_address)
                    ");
                }

                // تنفيذ الاستعلام
                $stmt->execute([
                    'username' => $username,
                    'email' => $email,
                    'phone' => $phone,
                    'car_type' => $car_type,
                    'chassis' => $chassis,
                    'service_type' => $service_type,
                    'notes' => $notes,
                    'file_path' => $file_path,
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);

                // الحصول على معرف التذكرة
                $ticket_id = $pdo->lastInsertId();

                // تجديد توكن CSRF بعد النجاح
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $csrf_token = $_SESSION['csrf_token'];

                // رسالة النجاح
                $success_message = "تم إرسال طلب تعديل ECU بنجاح. رقم طلبك هو: FLEX-" . str_pad($ticket_id, 5, '0', STR_PAD_LEFT);
                
                // إفراغ البيانات بعد النجاح
                $car_type = $chassis = $tuning_type = $notes = '';

                // إرسال بريد تنبيه للإدارة (اختياري)
                $admin_email = "admin@flexauto.com";
                $subject = "طلب تعديل ECU جديد - FlexAuto";
                $message = "تم استلام طلب تعديل ECU جديد:\n\n";
                $message .= "رقم الطلب: FLEX-" . str_pad($ticket_id, 5, '0', STR_PAD_LEFT) . "\n";
                $message .= "العميل: " . $username . "\n";
                $message .= "البريد الإلكتروني: " . $email . "\n";
                $message .= "الهاتف: " . $phone . "\n";
                $message .= "نوع السيارة: " . $car_type . "\n";
                $message .= "رقم الشاصي: " . $chassis . "\n";
                $message .= "نوع التعديل: " . $tuning_type . "\n";
                $message .= "تم رفع ملف: " . ($file_uploaded ? "نعم" : "لا") . "\n";
                
                // إضافة رأس إضافية
                $headers = "From: noreply@flexauto.com" . "\r\n";
                $headers .= "Reply-To: " . $email . "\r\n";
                
                // محاولة إرسال البريد
                @mail($admin_email, $subject, $message, $headers);

            } catch (PDOException $e) {
                // تسجيل الخطأ في ملف سجل بدلاً من عرضه للمستخدم
                error_log("ECU Tuning Error: " . $e->getMessage());
                $error_messages[] = "حدث خطأ أثناء معالجة طلبك. يرجى المحاولة مرة أخرى لاحقًا.";
            }
        }
    }
}

// تحديد ستايل الصفحة - استخدام متغيرات الألوان الموجودة
$page_css = '
<style>
    /* تنسيقات خاصة بصفحة تعديل ECU - متوافقة مع نظام فلكس أوتو */
    .ecu-tuning-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
        direction: rtl;
    }
    
    .ecu-header {
        text-align: center;
        margin-bottom: 30px;
        background: linear-gradient(135deg, #070e1b 0%, #0f172a 100%);
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    }
    
    .ecu-header h1 {
        margin-bottom: 10px;
        font-size: 32px;
        font-weight: 700;
        color: #00d9ff;
    }
    
    .ecu-header p {
        font-size: 18px;
        color: #f8fafc;
    }
    
    .ecu-info-box {
        background-color: #1e293b;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }
    
    .ecu-info-box h3 {
        color: #00d9ff;
        margin-bottom: 20px;
        font-size: 22px;
        position: relative;
        padding-bottom: 12px;
    }
    
    .ecu-info-box h3:after {
        content: "";
        position: absolute;
        bottom: 0;
        right: 0;
        width: 50px;
        height: 3px;
        background: #00d9ff;
    }
    
    .ecu-info-box ul {
        list-style-type: none;
        padding-right: 0;
    }
    
    .ecu-info-box ul li {
        margin-bottom: 12px;
        position: relative;
        padding-right: 28px;
        font-size: 16px;
        line-height: 1.6;
        color: #f8fafc;
    }
    
    .ecu-info-box ul li:before {
        content: "✓";
        color: #00ff88;
        position: absolute;
        right: 0;
        font-weight: bold;
        font-size: 18px;
    }
    
    .ecu-form {
        background-color: #1e293b;
        border-radius: 10px;
        padding: 30px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }
    
    .ecu-form .form-group {
        margin-bottom: 25px;
    }
    
    .ecu-form label {
        font-weight: 600;
        display: block;
        margin-bottom: 10px;
        color: #f8fafc;
        font-size: 16px;
    }
    
    .ecu-form select,
    .ecu-form input[type="text"],
    .ecu-form textarea {
        width: 100%;
        padding: 14px;
        border: 1px solid #2d3748;
        border-radius: 5px;
        font-size: 16px;
        transition: all 0.3s;
        background-color: #0f172a;
        color: #f8fafc;
    }
    
    .ecu-form select:focus,
    .ecu-form input[type="text"]:focus,
    .ecu-form textarea:focus {
        border-color: #00d9ff;
        outline: none;
        box-shadow: 0 0 8px rgba(0, 217, 255, 0.3);
    }
    
    .ecu-form .file-input {
        padding: 12px;
        border: 2px dashed #2d3748;
        border-radius: 5px;
        width: 100%;
        background-color: #0f172a;
        cursor: pointer;
        transition: all 0.3s;
        color: #f8fafc;
    }
    
    .ecu-form .file-input:hover {
        border-color: #00d9ff;
        background-color: rgba(0, 217, 255, 0.1);
    }
    
    .ecu-form small {
        display: block;
        margin-top: 5px;
        color: #a0aec0;
        font-size: 14px;
    }
    
    .ecu-form button {
        background: linear-gradient(135deg, #00d9ff 0%, #0088cc 100%);
        color: #f8fafc;
        border: none;
        padding: 14px 25px;
        font-size: 18px;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.3s;
        width: 100%;
        font-weight: 600;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }
    
    .ecu-form button:hover {
        background: linear-gradient(135deg, #0088cc 0%, #006699 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
    }
    
    .ecu-form button:active {
        transform: translateY(0);
    }
    
    .tuning-types {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 5px;
    }
    
    .tuning-type-option {
        flex: 1 1 calc(50% - 15px);
        display: flex;
        align-items: center;
        background-color: #0f172a;
        border: 2px solid #2d3748;
        border-radius: 8px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.3s;
        min-width: 150px;
    }
    
    .tuning-type-option:hover {
        background-color: #1a2234;
        border-color: #4a5568;
        transform: translateY(-2px);
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
    }
    
    .tuning-type-option.selected {
        background-color: rgba(0, 217, 255, 0.1);
        border-color: #00d9ff;
        box-shadow: 0 2px 10px rgba(0, 217, 255, 0.2);
    }
    
    .tuning-type-option input {
        margin-left: 12px;
        width: 18px;
        height: 18px;
        accent-color: #00d9ff;
    }
    
    .tuning-type-option span {
        font-weight: 500;
        font-size: 16px;
        color: #f8fafc;
    }
    
    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
    }
    
    .alert-success {
        background-color: rgba(0, 255, 136, 0.1);
        border: 1px solid rgba(0, 255, 136, 0.3);
        color: #00ff88;
    }
    
    .alert-error {
        background-color: rgba(255, 107, 107, 0.1);
        border: 1px solid rgba(255, 107, 107, 0.3);
        color: #ff6b6b;
    }
    
    .alert i {
        margin-left: 10px;
        font-size: 20px;
    }
    
    .alert ul {
        margin: 10px 25px 0 0;
        padding-right: 0;
    }
    
    .alert ul li {
        margin-bottom: 5px;
    }
    
    .vin-validation {
        margin-top: 5px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .vin-valid {
        color: #00ff88;
    }
    
    .vin-invalid {
        color: #ff6b6b;
    }
    
    @media (max-width: 768px) {
        .ecu-tuning-container {
            padding: 15px;
        }
        
        .ecu-header {
            padding: 20px;
        }
        
        .ecu-header h1 {
            font-size: 26px;
        }
        
        .ecu-header p {
            font-size: 16px;
        }
        
        .ecu-form {
            padding: 20px;
        }
        
        .tuning-type-option {
            flex: 1 1 100%;
        }
    }
</style>';

// محتوى الصفحة
ob_start();
?>

<div class="ecu-tuning-container">
    <div class="ecu-header">
        <h1>خدمة تعديل برمجيات ECU</h1>
        <p>حسّن أداء سيارتك مع خبراء البرمجة المتخصصين لدينا</p>
    </div>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div><?= htmlspecialchars($success_message) ?></div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_messages)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <strong>خطأ:</strong>
                <ul>
                    <?php foreach ($error_messages as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="ecu-info-box">
        <h3>مميزات خدمة تعديل ECU</h3>
        <ul>
            <li>زيادة قوة المحرك وتحسين عزم الدوران</li>
            <li>تحسين استجابة دواسة الوقود واستهلاك الوقود</li>
            <li>إزالة محددات السرعة وتحسين أداء التروس</li>
            <li>تعديلات مخصصة حسب نوع السيارة واحتياجاتك</li>
            <li>ضمان على جميع التعديلات مع دعم فني مستمر</li>
        </ul>
    </div>
    
    <div class="ecu-form">
        <form method="POST" action="" enctype="multipart/form-data" id="ecu-form">
            <!-- إضافة توكن CSRF للحماية -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <div class="form-group">
                <label for="car_type">نوع السيارة</label>
                <input type="text" id="car_type" name="car_type" placeholder="مثال: تويوتا كامري 2022" value="<?= htmlspecialchars($car_type) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="chassis">رقم الشاصي (VIN)</label>
                <input type="text" id="chassis" name="chassis" placeholder="يرجى إدخال رقم الشاصي المكون من 17 خانة" minlength="17" maxlength="17" value="<?= htmlspecialchars($chassis) ?>" required>
                <div id="vin_validation" class="vin-validation"></div>
            </div>
            
            <div class="form-group">
                <label>نوع التعديل</label>
                <div class="tuning-types">
                    <label class="tuning-type-option <?= $tuning_type === 'Stage 1' ? 'selected' : '' ?>">
                        <input type="radio" name="tuning_type" value="Stage 1" <?= $tuning_type === 'Stage 1' ? 'checked' : '' ?> required>
                        <span>Stage 1 - تعديل أساسي</span>
                    </label>
                    <label class="tuning-type-option <?= $tuning_type === 'Stage 2' ? 'selected' : '' ?>">
                        <input type="radio" name="tuning_type" value="Stage 2" <?= $tuning_type === 'Stage 2' ? 'checked' : '' ?>>
                        <span>Stage 2 - تعديل متوسط</span>
                    </label>
                    <label class="tuning-type-option <?= $tuning_type === 'Stage 3' ? 'selected' : '' ?>">
                        <input type="radio" name="tuning_type" value="Stage 3" <?= $tuning_type === 'Stage 3' ? 'checked' : '' ?>>
                        <span>Stage 3 - تعديل متقدم</span>
                    </label>
                    <label class="tuning-type-option <?= $tuning_type === 'Eco' ? 'selected' : '' ?>">
                        <input type="radio" name="tuning_type" value="Eco" <?= $tuning_type === 'Eco' ? 'checked' : '' ?>>
                        <span>Eco - توفير الوقود</span>
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label for="ecu_file">ملف ECU (اختياري)</label>
                <input type="file" id="ecu_file" name="ecu_file" class="file-input" accept=".bin,.hex,.zip,.rar,.pdf">
                <small>يمكنك رفع نسخة من ملف ECU الحالي إذا كان متوفراً لديك. الأنواع المدعومة: bin, hex, zip, rar, pdf (الحد الأقصى: 10MB)</small>
            </div>
            
            <div class="form-group">
                <label for="notes">ملاحظات إضافية</label>
                <textarea id="notes" name="notes" rows="5" placeholder="أي معلومات إضافية ترغب في إضافتها حول طلبك"><?= htmlspecialchars($notes) ?></textarea>
            </div>
            
            <button type="submit" id="submit-btn">إرسال طلب التعديل</button>
        </form>
    </div>
</div>

<script>
    // تحسين تجربة المستخدم عند اختيار نوع التعديل
    document.addEventListener('DOMContentLoaded', function() {
        // التعامل مع خيارات التعديل
        const tuningOptions = document.querySelectorAll('.tuning-type-option');
        
        tuningOptions.forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            
            // إضافة الكلاس عند التحميل إذا كان مختاراً
            if (radio.checked) {
                option.classList.add('selected');
            }
            
            // إضافة الكلاس عند النقر
            option.addEventListener('click', function() {
                tuningOptions.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                radio.checked = true;
            });
        });
        
        // التحقق من صحة رقم الشاصي (VIN)
        const chassisInput = document.getElementById('chassis');
        const vinValidation = document.getElementById('vin_validation');
        
        if (chassisInput) {
            chassisInput.addEventListener('input', function() {
                // تحويل الأحرف إلى أحرف كبيرة وإزالة المسافات
                this.value = this.value.toUpperCase().replace(/\s/g, '');
                
                // استبدال الأحرف غير المسموح بها في VIN (I, O, Q)
                this.value = this.value.replace(/[IOQ]/g, '');
                
                const vin = this.value.trim();
                
                if (vin.length === 0) {
                    vinValidation.textContent = '';
                    vinValidation.className = 'vin-validation';
                } else if (vin.length === 17) {
                    // التحقق من صحة تنسيق VIN
                    const vinRegex = /^[A-HJ-NPR-Z0-9]{17}$/;
                    if (vinRegex.test(vin)) {
                        vinValidation.textContent = '✓ رقم الشاصي صحيح (17 خانة)';
                        vinValidation.className = 'vin-validation vin-valid';
                    } else {
                        vinValidation.textContent = '✗ رقم الشاصي يحتوي على أحرف غير صالحة';
                        vinValidation.className = 'vin-validation vin-invalid';
                    }
                } else {
                    vinValidation.textContent = '✗ رقم الشاصي يجب أن يتكون من 17 خانة بالضبط (الآن: ' + vin.length + ' خانة)';
                    vinValidation.className = 'vin-validation vin-invalid';
                }
            });
            
            // تشغيل التحقق عند تحميل الصفحة إذا كانت هناك قيمة
            if (chassisInput.value.length > 0) {
                chassisInput.dispatchEvent(new Event('input'));
            }
        }
        
        // منع إرسال النموذج مرتين
        const form = document.getElementById('ecu-form');
        const submitBtn = document.getElementById('submit-btn');
        
        if (form && submitBtn) {
            form.addEventListener('submit', function() {
                // التحقق من صحة رقم الشاصي قبل الإرسال
                const chassisValue = chassisInput.value.trim();
                if (chassisValue.length !== 17) {
                    vinValidation.textContent = '✗ رقم الشاصي يجب أن يتكون من 17 خانة بالضبط';
                    vinValidation.className = 'vin-validation vin-invalid';
                    chassisInput.focus();
                    return false;
                }
                
                // تعطيل زر الإرسال بعد النقر
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...';
            });
        }
        
        // إخفاء رسائل التنبيه تلقائياً بعد 8 ثوانٍ
        const alerts = document.querySelectorAll('.alert');
        if (alerts.length) {
            setTimeout(() => {
                alerts.forEach(alert => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                });
            }, 8000);
        }
        
        // التحقق من حجم الملف قبل الإرسال
        const fileInput = document.getElementById('ecu_file');
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const fileSize = this.files[0].size; // بالبايت
                    const maxSize = 10 * 1024 * 1024; // 10 ميجابايت
                    
                    if (fileSize > maxSize) {
                        alert('حجم الملف كبير جدًا. الحد الأقصى هو 10 ميجابايت.');
                        this.value = ''; // مسح الملف المحدد
                    }
                    
                    // التحقق من امتداد الملف
                    const fileName = this.files[0].name;
                    const fileExt = fileName.split('.').pop().toLowerCase();
                    const allowedExts = ['bin', 'hex', 'zip', 'rar', 'pdf'];
                    
                    if (!allowedExts.includes(fileExt)) {
                        alert('نوع الملف غير مسموح به. الأنواع المسموحة: ' + allowedExts.join(', '));
                        this.value = ''; // مسح الملف المحدد
                    }
                }
            });
        }
    });
</script>

<?php
// تخزين المحتوى وعرضه في قالب layout.php
$page_content = ob_get_clean();
include __DIR__ . '/includes/layout.php';

?>