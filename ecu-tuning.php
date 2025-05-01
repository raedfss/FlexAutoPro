<?php
// بدء الجلسة قبل أي إخراج
session_start();
require_once __DIR__ . '/includes/db.php';

// تعريف user_type بشكل آمن لمنع التحذير
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

// إعدادات الصفحة
$page_title = "تعديل برمجيات ECU";
$hide_title = true;
$success_message = '';
$error_messages = [];

// معالجة الطلب
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $car_type = trim($_POST['car_type'] ?? '');
    $chassis = trim($_POST['chassis'] ?? '');
    $tuning_type = trim($_POST['tuning_type'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $file_uploaded = false;
    $file_path = '';

    // تحقق من الحقول
    if (empty($car_type)) $error_messages[] = "يرجى إدخال نوع السيارة";
    if (empty($chassis)) {
        $error_messages[] = "يرجى إدخال رقم الشاصي";
    } elseif (strlen($chassis) !== 17) {
        $error_messages[] = "رقم الشاصي يجب أن يتكون من 17 خانة بالضبط";
    }
    if (empty($tuning_type)) $error_messages[] = "يرجى اختيار نوع التعديل";

    // معالجة الملف إذا تم رفعه
    if (isset($_FILES['ecu_file']) && $_FILES['ecu_file']['error'] === UPLOAD_ERR_OK) {
        $target_dir = __DIR__ . "/uploads/ecu_files/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $file_extension = pathinfo($_FILES['ecu_file']['name'], PATHINFO_EXTENSION);
        $unique_name = uniqid('ecu_') . '_' . time() . '.' . $file_extension;
        $target_file = $target_dir . $unique_name;

        if (move_uploaded_file($_FILES['ecu_file']['tmp_name'], $target_file)) {
            $file_uploaded = true;
            $file_path = 'uploads/ecu_files/' . $unique_name;
        } else {
            $error_messages[] = "حدث خطأ أثناء رفع الملف";
        }
    }

    // إذا لا توجد أخطاء، أدخل التذكرة في قاعدة البيانات
    if (empty($error_messages)) {
        try {
            $username = $_SESSION['username'] ?? 'مستخدم';
            $email = $_SESSION['email'] ?? '';
            $phone = $_SESSION['phone'] ?? '';
            $service_type = "تعديل ECU: " . $tuning_type;

            $stmt = $pdo->prepare("
                INSERT INTO tickets 
                (username, email, phone, car_type, chassis, service_type, notes, file_path, status, created_at) 
                VALUES 
                (:username, :email, :phone, :car_type, :chassis, :service_type, :notes, :file_path, 'pending', NOW())
            ");

            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'car_type' => $car_type,
                'chassis' => $chassis,
                'service_type' => $service_type,
                'notes' => $notes,
                'file_path' => $file_path
            ]);

            $success_message = "تم إرسال طلب تعديل ECU بنجاح. سنتواصل معك قريباً.";
            // إفراغ البيانات بعد النجاح
            $car_type = $chassis = $tuning_type = $notes = '';
        } catch (PDOException $e) {
            $error_messages[] = "حدث خطأ أثناء معالجة طلبك: " . $e->getMessage();
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
            <strong>تم بنجاح!</strong> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_messages)): ?>
        <div class="alert alert-error">
            <strong>خطأ:</strong>
            <ul>
                <?php foreach ($error_messages as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
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
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="car_type">نوع السيارة</label>
                <input type="text" id="car_type" name="car_type" placeholder="مثال: تويوتا كامري 2022" value="<?php echo htmlspecialchars($car_type ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="chassis">رقم الشاصي (VIN)</label>
                <input type="text" id="chassis" name="chassis" placeholder="يرجى إدخال رقم الشاصي المكون من 17 خانة" minlength="17" maxlength="17" value="<?php echo htmlspecialchars($chassis ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label>نوع التعديل</label>
                <div class="tuning-types">
                    <label class="tuning-type-option">
                        <input type="radio" name="tuning_type" value="Stage 1" <?php echo ($tuning_type ?? '') === 'Stage 1' ? 'checked' : ''; ?> required>
                        <span>Stage 1 - تعديل أساسي</span>
                    </label>
                    <label class="tuning-type-option">
                        <input type="radio" name="tuning_type" value="Stage 2" <?php echo ($tuning_type ?? '') === 'Stage 2' ? 'checked' : ''; ?>>
                        <span>Stage 2 - تعديل متوسط</span>
                    </label>
                    <label class="tuning-type-option">
                        <input type="radio" name="tuning_type" value="Stage 3" <?php echo ($tuning_type ?? '') === 'Stage 3' ? 'checked' : ''; ?>>
                        <span>Stage 3 - تعديل متقدم</span>
                    </label>
                    <label class="tuning-type-option">
                        <input type="radio" name="tuning_type" value="Eco" <?php echo ($tuning_type ?? '') === 'Eco' ? 'checked' : ''; ?>>
                        <span>Eco - توفير الوقود</span>
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label for="ecu_file">ملف ECU (اختياري)</label>
                <input type="file" id="ecu_file" name="ecu_file" class="file-input">
                <small>يمكنك رفع نسخة من ملف ECU الحالي إذا كان متوفراً لديك</small>
            </div>
            
            <div class="form-group">
                <label for="notes">ملاحظات إضافية</label>
                <textarea id="notes" name="notes" rows="5" placeholder="أي معلومات إضافية ترغب في إضافتها حول طلبك"><?php echo htmlspecialchars($notes ?? ''); ?></textarea>
            </div>
            
            <button type="submit">إرسال طلب التعديل</button>
        </form>
    </div>
</div>

<script>
    // تحسين تجربة المستخدم عند اختيار نوع التعديل
    document.addEventListener('DOMContentLoaded', function() {
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
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/includes/layout.php';
?>