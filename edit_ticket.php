<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db.php';

// وظيفة لتنظيف المدخلات
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// إنشاء أو استعادة توكن CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// التحقق من صحة معرف التذكرة
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "معرف التذكرة غير صالح.";
    header("Location: my_tickets.php");
    exit;
}

$ticket_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
$username = sanitize_input($_SESSION['username']);

// التحقق من وجود رسالة خطأ من محاولة سابقة
$error = isset($_SESSION['edit_ticket_error']) ? $_SESSION['edit_ticket_error'] : null;
unset($_SESSION['edit_ticket_error']);

try {
    // جلب بيانات التذكرة بطريقة آمنة
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ? AND username = ?");
    $stmt->execute([$ticket_id, $username]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $_SESSION['error_message'] = "لم يتم العثور على التذكرة أو ليس لديك صلاحية الوصول إليها.";
        header("Location: my_tickets.php");
        exit;
    }

    // عند إرسال النموذج
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // التحقق من توكن CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("خطأ في التحقق من الأمان. يرجى المحاولة مرة أخرى.");
        }

        // تنظيف وتحقق من المدخلات
        $service_type = sanitize_input($_POST['service_type'] ?? '');
        $car_type = sanitize_input($_POST['car_type'] ?? '');
        $chassis = sanitize_input($_POST['chassis'] ?? '');
        $additional_info = sanitize_input($_POST['additional_info'] ?? '');

        // التحقق من الحقول المطلوبة
        if (empty($service_type)) {
            throw new Exception("نوع الخدمة مطلوب.");
        }
        
        if (empty($car_type)) {
            throw new Exception("نوع السيارة مطلوب.");
        }
        
        if (empty($chassis)) {
            throw new Exception("رقم الشاصي مطلوب.");
        } elseif (strlen($chassis) !== 17) {
            throw new Exception("رقم الشاصي يجب أن يتكون من 17 خانة بالضبط.");
        }

        // تحديث البيانات
        $update_stmt = $pdo->prepare("
            UPDATE tickets 
            SET service_type = ?, car_type = ?, chassis = ?, additional_info = ?, updated_at = NOW()
            WHERE id = ? AND username = ?
        ");
        
        $update_result = $update_stmt->execute([
            $service_type, 
            $car_type, 
            $chassis, 
            $additional_info, 
            $ticket_id, 
            $username
        ]);

        if (!$update_result) {
            throw new Exception("حدث خطأ أثناء تحديث بيانات التذكرة.");
        }

        // تجديد توكن CSRF بعد الإرسال الناجح
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        // تعيين رسالة نجاح
        $_SESSION['success_message'] = "تم تحديث بيانات التذكرة بنجاح.";
        
        header("Location: my_tickets.php");
        exit;
    }
} catch (Exception $e) {
    // تسجيل الخطأ في ملف سجل
    error_log("Error updating ticket #$ticket_id: " . $e->getMessage());
    
    // تخزين رسالة الخطأ في الجلسة
    $_SESSION['edit_ticket_error'] = $e->getMessage();
    
    // إعادة تحميل الصفحة مع رسالة الخطأ
    header("Location: edit_ticket.php?id=$ticket_id");
    exit;
}

// تحديد عنوان الصفحة
$page_title = "تعديل التذكرة | FlexAuto";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- تعزيز الأمان للمتصفح -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; script-src 'self'; font-src https://cdnjs.cloudflare.com; img-src 'self' data:;">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background-color: #1a1f2e;
            color: white;
            margin: 0;
            padding: 0;
        }

        .svg-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.5;
        }

        .svg-object {
            width: 100%;
            height: 100%;
        }

        header {
            background-color: rgba(0, 0, 0, 0.85);
            padding: 18px;
            text-align: center;
            font-size: 24px;
            color: #00ffff;
            font-weight: bold;
            border-bottom: 1px solid rgba(0, 255, 255, 0.3);
        }

        main {
            padding: 30px 20px;
            max-width: 800px;
            margin: auto;
        }

        .container {
            background: rgba(0, 0, 0, 0.6);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.1);
            border: 1px solid rgba(66, 135, 245, 0.2);
        }

        h1 {
            text-align: center;
            color: #00ffff;
            margin-bottom: 30px;
        }

        form label {
            display: block;
            margin-bottom: 6px;
            color: #a0d0ff;
        }

        form input, form textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: none;
            border-radius: 10px;
            background-color: rgba(30, 35, 50, 0.8);
            color: white;
            font-size: 16px;
        }

        form textarea {
            resize: vertical;
        }

        .buttons {
            margin-top: 25px;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: bold;
            transition: 0.3s ease;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e90ff, #4287f5);
            color: white;
        }

        .btn-secondary {
            background: rgba(30, 35, 50, 0.8);
            color: #00ffff;
            border: 1px solid rgba(0, 255, 255, 0.3);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #fff;
        }

        .alert-danger {
            background: rgba(255, 77, 77, 0.2);
            border: 1px solid rgba(255, 77, 77, 0.3);
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

        footer {
            background-color: rgba(0, 0, 0, 0.9);
            color: #eee;
            text-align: center;
            padding: 20px;
            margin-top: 50px;
        }

        .footer-highlight {
            font-size: 18px;
            font-weight: bold;
            color: #00ffff;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="svg-background">
    <embed type="image/svg+xml" src="admin/admin_background.svg" class="svg-object">
</div>

<header>FlexAuto - تعديل التذكرة</header>

<main>
    <div class="container">
        <h1>تعديل بيانات التذكرة</h1>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" id="edit_ticket_form">
            <!-- إضافة توكن CSRF للحماية -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <label for="service_type">نوع الخدمة</label>
            <input type="text" id="service_type" name="service_type" value="<?= htmlspecialchars($ticket['service_type'] ?? '') ?>" required>

            <label for="car_type">نوع السيارة</label>
            <input type="text" id="car_type" name="car_type" value="<?= htmlspecialchars($ticket['car_type'] ?? '') ?>" required>

            <label for="chassis">رقم الشاسيه</label>
            <input type="text" id="chassis" name="chassis" value="<?= htmlspecialchars($ticket['chassis'] ?? '') ?>" maxlength="17" required>
            <div id="vin_validation" class="vin-validation"></div>

            <label for="additional_info">ملاحظات إضافية</label>
            <textarea id="additional_info" name="additional_info" rows="4"><?= htmlspecialchars($ticket['additional_info'] ?? '') ?></textarea>

            <div class="buttons">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ التعديلات</button>
                <a href="my_tickets.php" class="btn btn-secondary"><i class="fas fa-arrow-right"></i> رجوع</a>
            </div>
        </form>
    </div>
</main>

<footer>
    <div class="footer-highlight">ذكاءٌ في الخدمة، سرعةٌ في الاستجابة، جودةٌ بلا حدود.</div>
    <div>Smart service, fast response, unlimited quality.</div>
    <div style="margin-top: 8px;">📧 raedfss@hotmail.com | ☎️ +962796519007</div>
    <div style="margin-top: 5px;">&copy; <?= date('Y') ?> FlexAuto. جميع الحقوق محفوظة.</div>
</footer>

<script>
// التحقق من صحة رقم الشاصي (VIN)
document.addEventListener('DOMContentLoaded', function() {
    const chassisInput = document.getElementById('chassis');
    const vinValidation = document.getElementById('vin_validation');
    const form = document.getElementById('edit_ticket_form');
    
    // مراقبة إدخال رقم الشاصي للتحقق
    if(chassisInput) {
        chassisInput.addEventListener('input', function() {
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
        
        // تشغيل التحقق عند تحميل الصفحة
        chassisInput.dispatchEvent(new Event('input'));
    }
    
    // منع إرسال النموذج مرتين
    if(form) {
        form.addEventListener('submit', function() {
            // التحقق من صحة رقم الشاصي قبل الإرسال
            const vin = chassisInput.value.trim();
            if (vin.length !== 17) {
                vinValidation.textContent = '✗ رقم الشاصي يجب أن يتكون من 17 خانة بالضبط';
                vinValidation.className = 'vin-validation vin-invalid';
                chassisInput.focus();
                return false;
            }
            
            // تعطيل زر الإرسال بعد النقر
            const submitBtn = document.querySelector('.btn-primary');
            if(submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
            }
        });
    }
});
</script>

</body>
</html>