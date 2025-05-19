<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// إنشاء أو استعادة توكن CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// وظيفة لتنظيف المدخلات
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// مصفوفة لحفظ الأخطاء
$errors = [];
$success_message = '';

// التحقق إذا تم إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من توكن CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "خطأ في التحقق من الأمان. يرجى المحاولة مرة أخرى.";
    } else {
        // تنظيف وتحقق من المدخلات
        $request_type = isset($_POST['request_type']) ? sanitize_input($_POST['request_type']) : '';
        $custom_service = isset($_POST['custom_service']) ? sanitize_input($_POST['custom_service']) : '';
        $car_type = isset($_POST['car_type']) ? sanitize_input($_POST['car_type']) : '';
        $vin = isset($_POST['vin']) ? sanitize_input($_POST['vin']) : '';
        $contact = isset($_POST['contact']) ? sanitize_input($_POST['contact']) : '';

        // التحقق من القيم
        if (empty($request_type)) {
            $errors[] = "يرجى تحديد نوع الطلب";
        } elseif (!in_array($request_type, ['key_code', 'custom'])) {
            $errors[] = "نوع الطلب غير صالح";
        }
        
        if ($request_type === 'custom' && empty($custom_service)) {
            $errors[] = "يرجى تحديد نوع الخدمة المطلوبة";
        }
        
        if (empty($car_type)) {
            $errors[] = "يرجى إدخال نوع السيارة";
        }
        
        if (empty($vin)) {
            $errors[] = "يرجى إدخال رقم الشاصي (VIN)";
        } elseif (strlen($vin) !== 17) {
            $errors[] = "رقم الشاصي يجب أن يتكون من 17 خانة بالضبط";
        } elseif (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
            // التحقق من صحة تنسيق VIN (بدون الأحرف I, O, Q)
            $errors[] = "رقم الشاصي يحتوي على أحرف غير صالحة";
        }
        
        if (empty($contact)) {
            $errors[] = "يرجى إدخال معلومات التواصل";
        }

        // إن لم تكن هناك أخطاء نُعالج الإدخال
        if (empty($errors)) {
            try {
                $service_type = ($request_type === 'key_code') 
                    ? 'طلب كود برمجة مفتاح'
                    : 'طلب خدمة: ' . $custom_service;

                $username = isset($_SESSION['username']) ? sanitize_input($_SESSION['username']) : 'زائر';
                $email = isset($_SESSION['email']) ? sanitize_input($_SESSION['email']) : '';

                // التحقق من وجود عمود status قبل الإدراج
                $check_column = $pdo->query("
                    SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_name = 'tickets' AND column_name = 'status'
                ");
                
                $has_status_column = $check_column->rowCount() > 0;
                
                // بناء استعلام الإدراج بناءً على وجود العمود
                if ($has_status_column) {
                    $stmt = $pdo->prepare("
                        INSERT INTO tickets 
                        (username, primary_email, phone, car_type, chassis, service_type, status, created_at) 
                        VALUES 
                        (:username, :primary_email, :contact, :car_type, :vin, :service_type, 'pending', NOW())
                    ");
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO tickets 
                        (username, primary_email, phone, car_type, chassis, service_type, created_at) 
                        VALUES 
                        (:username, :primary_email, :contact, :car_type, :vin, :service_type, NOW())
                    ");
                }

                $stmt->execute([
                    'username'       => $username,
                    'primary_email'  => $email,
                    'contact'        => $contact,
                    'car_type'       => $car_type,
                    'vin'            => $vin,
                    'service_type'   => $service_type
                ]);

                // تجديد توكن CSRF بعد الإرسال الناجح
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $csrf_token = $_SESSION['csrf_token'];
                
                // تعيين رسالة النجاح
                $success_message = "تم استلام طلبك بنجاح. سيتواصل معك فريق FlexAuto خلال وقت قصير لتزويدك بالتفاصيل.";
                
                // تفريغ قيم المدخلات بعد الإرسال الناجح
                $request_type = $custom_service = $car_type = $vin = $contact = '';

            } catch (PDOException $e) {
                // تسجيل الخطأ في ملف سجل بدلاً من عرضه للمستخدم
                error_log("Database error: " . $e->getMessage());
                $errors[] = "حدث خطأ أثناء معالجة طلبك. يرجى المحاولة مرة أخرى لاحقاً.";
            }
        }
    }
}


// إعداد معلومات الصفحة
$page_title = "خدمة قاعدة بيانات VIN";
$hide_title = true; // إخفاء العنوان الافتراضي في القالب

// تنسيقات CSS المخصصة للصفحة
$page_css = <<<CSS
.service-header {
    background: linear-gradient(135deg, #004080, #001030);
    color: white;
    padding: 60px 20px;
    text-align: center;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    margin-bottom: 50px;
    position: relative;
    overflow: hidden;
}

.service-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('/assets/img/circuit-pattern.svg');
    background-size: cover;
    opacity: 0.05;
    z-index: 0;
}

.header-content {
    position: relative;
    z-index: 1;
}

.service-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 20px;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
}

.service-header p {
    font-size: 1.1rem;
    margin-bottom: 0;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
    color: rgba(255, 255, 255, 0.9);
}

.vin-form-container {
    display: flex;
    flex-wrap: wrap;
    gap: 40px;
    margin-bottom: 50px;
}

.form-content {
    flex: 1;
    min-width: 300px;
}

.info-content {
    flex: 1;
    min-width: 300px;
    background: rgba(15, 23, 42, 0.5);
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(66, 135, 245, 0.1);
    backdrop-filter: blur(5px);
    position: relative;
    overflow: hidden;
}

.info-content::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(to bottom, #00d9ff, #0070cc);
    opacity: 0.8;
}

.info-title {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 20px;
    color: #00d9ff;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-text {
    color: #cbd5e1;
    margin-bottom: 20px;
    line-height: 1.6;
}

.supported-cars {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 25px;
}

.car-brand {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 8px;
    padding: 8px 15px;
    color: #a0aec0;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.car-brand:hover {
    transform: translateY(-3px);
    background: rgba(0, 217, 255, 0.1);
    color: #00d9ff;
}

.car-brand i {
    color: #00d9ff;
}

.form-card {
    background: rgba(15, 23, 42, 0.5);
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(66, 135, 245, 0.1);
    backdrop-filter: blur(5px);
}

.form-title {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 25px;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-title i {
    color: #00d9ff;
}

.form-group {
    margin-bottom: 25px;
}

.form-label {
    display: block;
    margin-bottom: 10px;
    color: #cbd5e1;
    font-weight: bold;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(66, 135, 245, 0.2);
    border-radius: 8px;
    color: #fff;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(0, 217, 255, 0.3);
    border-color: #00d9ff;
}

.form-select {
    width: 100%;
    padding: 12px 15px;
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(66, 135, 245, 0.2);
    border-radius: 8px;
    color: #fff;
    font-size: 1rem;
    transition: all 0.3s ease;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2300d9ff' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: left 15px center;
    padding-left: 35px;
}

.form-select:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(0, 217, 255, 0.3);
    border-color: #00d9ff;
}

.submit-btn {
    display: inline-block;
    padding: 12px 30px;
    background: linear-gradient(135deg, #00d9ff, #0070cc);
    color: white;
    border: none;
    border-radius: 30px;
    font-weight: bold;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    width: 100%;
}

.submit-btn:hover {
    background: linear-gradient(135deg, #00eaff, #0088ff);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
}

.custom-service-field {
    display: none;
    margin-top: 10px;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    color: #fff;
}

.alert-success {
    background: rgba(0, 200, 83, 0.2);
    border: 1px solid rgba(0, 200, 83, 0.3);
}

.alert-danger {
    background: rgba(255, 107, 107, 0.2);
    border: 1px solid rgba(255, 107, 107, 0.3);
}

.vin-validation {
    color: #64748b;
    font-size: 0.9rem;
    margin-top: 5px;
    transition: all 0.3s ease;
}

.vin-valid {
    color: #00ff88;
}

.vin-invalid {
    color: #ff6b6b;
}

.steps-container {
    margin-top: 50px;
    margin-bottom: 30px;
}

.steps {
    display: flex;
    justify-content: space-between;
    margin-bottom: 40px;
    position: relative;
}

.steps::before {
    content: '';
    position: absolute;
    top: 30px;
    left: 0;
    width: 100%;
    height: 2px;
    background: rgba(100, 116, 139, 0.3);
    z-index: 0;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 1;
    flex: 1;
}

.step-number {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.7);
    border: 2px solid rgba(66, 135, 245, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
    color: #00d9ff;
    margin-bottom: 15px;
}

.step-title {
    color: #cbd5e1;
    font-weight: bold;
    text-align: center;
    margin-bottom: 8px;
}

.step-desc {
    color: #64748b;
    font-size: 0.9rem;
    text-align: center;
    max-width: 200px;
}

/* للشاشات الصغيرة */
@media (max-width: 768px) {
    .service-header h1 {
        font-size: 2rem;
    }
    
    .service-header p {
        font-size: 1rem;
    }
    
    .steps {
        flex-direction: column;
        gap: 30px;
    }
    
    .steps::before {
        width: 2px;
        height: 100%;
        left: 30px;
        top: 0;
    }
    
    .step {
        flex-direction: row;
        align-items: flex-start;
        text-align: right;
    }
    
    .step-number {
        margin-left: 0;
        margin-bottom: 0;
        margin-right: 15px;
    }
    
    .step-content {
        text-align: right;
    }
    
    .step-title, .step-desc {
        text-align: right;
    }
}
CSS;

// JavaScript المخصص للصفحة مع تحسينات أمنية
$page_js = <<<JS
// التحقق من صحة رقم الشاصي (VIN) وتنظيف المدخلات
document.addEventListener('DOMContentLoaded', function() {
    const requestTypeSelect = document.getElementById('request_type');
    const customServiceField = document.getElementById('custom_service_field');
    const vinInput = document.getElementById('vin');
    const vinValidation = document.getElementById('vin_validation');
    const form = document.getElementById('service_request_form');
    
    // مراقبة تغيير نوع الطلب
    if(requestTypeSelect) {
        requestTypeSelect.addEventListener('change', function() {
            if(this.value === 'custom') {
                customServiceField.style.display = 'block';
                document.getElementById('custom_service').setAttribute('required', 'required');
            } else {
                customServiceField.style.display = 'none';
                document.getElementById('custom_service').removeAttribute('required');
            }
        });
    }
    
    // تنظيف وتحقق من مدخلات رقم الشاصي
    if(vinInput) {
        vinInput.addEventListener('input', function() {
            // تحويل الأحرف إلى أحرف كبيرة وإزالة المسافات
            this.value = this.value.toUpperCase().replace(/\s/g, '');
            
            // استبدال الأحرف غير المسموح بها في VIN (I, O, Q)
            this.value = this.value.replace(/[IOQ]/g, '');
            
            const vin = this.value.trim();
            
            if(vin.length === 0) {
                vinValidation.textContent = '';
                vinValidation.className = 'vin-validation';
            } else if(vin.length === 17) {
                // التحقق من صحة تنسيق VIN
                const vinRegex = /^[A-HJ-NPR-Z0-9]{17}$/;
                if(vinRegex.test(vin)) {
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
    }
    
    // منع إرسال النموذج مرتين
    if(form) {
        form.addEventListener('submit', function() {
            // تعطيل زر الإرسال بعد النقر
            const submitBtn = document.querySelector('.submit-btn');
            if(submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري المعالجة...';
            }
        });
    }
    
    // إخفاء رسائل النجاح تلقائياً بعد 5 ثوانٍ
    const successAlert = document.querySelector('.alert-success');
    if(successAlert) {
        setTimeout(() => {
            successAlert.style.opacity = '0';
            setTimeout(() => {
                successAlert.style.display = 'none';
            }, 500);
        }, 5000);
    }
});
JS;

// محتوى الصفحة
ob_start();
?>
<div class="service-header">
    <div class="header-content">
        <h1>خدمة قاعدة بيانات VIN</h1>
        <p>استعلام فوري عن أكواد البرمجة ومعلومات المركبة من خلال رقم الشاصي (VIN) لمختلف أنواع السيارات</p>
    </div>
</div>

<div class="container">
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> يرجى تصحيح الأخطاء التالية:
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="vin-form-container">
        <div class="form-content">
            <div class="form-card">
                <h2 class="form-title"><i class="fas fa-file-alt"></i> نموذج طلب الخدمة</h2>
                <form method="post" action="" id="service_request_form">
                    <!-- إضافة توكن CSRF للحماية -->
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    
                    <div class="form-group">
                        <label for="request_type" class="form-label">نوع الطلب:</label>
                        <select id="request_type" name="request_type" class="form-select" required>
                            <option value="" disabled <?= empty($request_type) ? 'selected' : '' ?>>-- اختر نوع الطلب --</option>
                            <option value="key_code" <?= isset($request_type) && $request_type === 'key_code' ? 'selected' : '' ?>>طلب كود برمجة مفتاح</option>
                            <option value="custom" <?= isset($request_type) && $request_type === 'custom' ? 'selected' : '' ?>>طلب خدمة أخرى</option>
                        </select>
                    </div>
                    
                    <div id="custom_service_field" class="form-group custom-service-field" <?= isset($request_type) && $request_type === 'custom' ? 'style="display:block;"' : '' ?>>
                        <label for="custom_service" class="form-label">وصف الخدمة المطلوبة:</label>
                        <input type="text" id="custom_service" name="custom_service" class="form-control" value="<?= htmlspecialchars($custom_service ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="مثال: فحص إلكتروني متقدم، تعديل برمجيات...">
                    </div>
                    
                    <div class="form-group">
                        <label for="car_type" class="form-label">نوع السيارة:</label>
                        <input type="text" id="car_type" name="car_type" class="form-control" value="<?= htmlspecialchars($car_type ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="مثال: Hyundai Elantra 2021" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="vin" class="form-label">رقم الشاصي (VIN):</label>
                        <input type="text" id="vin" name="vin" class="form-control" value="<?= htmlspecialchars($vin ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="KMHCT41DBFU685448" maxlength="17" required>
                        <div id="vin_validation" class="vin-validation"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact" class="form-label">معلومات التواصل:</label>
                        <input type="text" id="contact" name="contact" class="form-control" value="<?= htmlspecialchars($contact ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="رقم الهاتف أو البريد الإلكتروني" required>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i> إرسال الطلب
                    </button>
                </form>
            </div>
        </div>
        
        <div class="info-content">
            <h2 class="info-title"><i class="fas fa-database"></i> معلومات الخدمة</h2>
            <p class="info-text">
                تقدم FlexAuto خدمة استخراج أكواد برمجة المفاتيح والمعلومات الفنية المتقدمة من خلال قاعدة بيانات VIN المتخصصة لمختلف أنواع السيارات.
            </p>
            <p class="info-text">
                نقوم باستخراج الأكواد الأصلية ومعلومات البرمجة مباشرة من قواعد بيانات المصنّع لضمان التوافق الكامل وسلامة وحدات التحكم الإلكترونية.
            </p>
            
            <h3 style="color: #a0aec0; margin-top: 20px; font-size: 1.1rem;">العلامات التجارية المدعومة:</h3>
            <div class="supported-cars">
                <div class="car-brand"><i class="fas fa-car"></i> هيونداي</div>
                <div class="car-brand"><i class="fas fa-car"></i> كيا</div>
                <div class="car-brand"><i class="fas fa-car"></i> تويوتا</div>
                <div class="car-brand"><i class="fas fa-car"></i> نيسان</div>
                <div class="car-brand"><i class="fas fa-car"></i> فورد</div>
                <div class="car-brand"><i class="fas fa-car"></i> شيفروليه</div>
                <div class="car-brand"><i class="fas fa-car"></i> بي إم دبليو</div>
                <div class="car-brand"><i class="fas fa-car"></i> مرسيدس</div>
                <div class="car-brand"><i class="fas fa-car"></i> المزيد...</div>
            </div>
        </div>
    </div>
    
    <div class="steps-container">
        <h2 style="text-align: center; margin-bottom: 30px; color: #00d9ff;">كيف تعمل الخدمة؟</h2>
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <div class="step-title">تقديم الطلب</div>
                    <div class="step-desc">أدخل معلومات السيارة ورقم الشاصي (VIN) ومعلومات التواصل</div>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <div class="step-title">المعالجة الفنية</div>
                    <div class="step-desc">يقوم فريق FlexAuto بمعالجة طلبك واستخراج البيانات المطلوبة</div>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <div class="step-title">استلام النتائج</div>
                    <div class="step-desc">تتلقى الكود أو النتائج عبر وسيلة التواصل المفضلة لديك</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$page_content = ob_get_clean();

// تضمين ملف القالب
require_once 'includes/layout.php';
?>