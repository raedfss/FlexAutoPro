<?php
session_start();
include 'db_connect.php';

// وظيفة لتنظيف المدخلات
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// وظيفة للتحقق من امتدادات الملفات المسموح بها
function allowed_file($filename, $allowed_exts = ['zip', 'rar', 'bin', 'hex', 'jpg', 'jpeg', 'png', 'pdf']) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowed_exts);
}

// وظيفة لإنشاء اسم ملف آمن
function generate_safe_filename($filename) {
    // إزالة الأحرف غير الآمنة وتوليد اسم فريد
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', basename($filename));
    return time() . '_' . substr(md5(uniqid(rand(), true)), 0, 8) . '_' . $filename;
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['email']) || $_SESSION['user_type'] !== 'user') {
    header("Location: login.php");
    exit;
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: create_ticket.php");
    exit;
}

// التحقق من CSRF token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
    $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = "خطأ في التحقق من الأمان. يرجى المحاولة مرة أخرى.";
    header("Location: create_ticket.php");
    exit;
}

// إنشاء توكن CSRF جديد
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// مصفوفة لحفظ الأخطاء
$errors = [];

// تنظيف واستقبال القيم
$username = sanitize_input($_SESSION['username']);
$primary_email = filter_var($_SESSION['email'], FILTER_SANITIZE_EMAIL);
$alt_email = !empty($_POST['alternative_email']) ? 
             filter_var($_POST['alternative_email'], FILTER_SANITIZE_EMAIL) : '';
$phone = sanitize_input($_POST['phone'] ?? '');
$car_type = sanitize_input($_POST['car_type'] ?? '');
$chassis = sanitize_input($_POST['chassis'] ?? '');
$year = sanitize_input($_POST['year'] ?? '');
$service_type = sanitize_input($_POST['service_type'] ?? '');
$details = sanitize_input($_POST['details'] ?? '');

// التحقق من البيانات
if (empty($phone)) {
    $errors[] = "رقم الهاتف مطلوب";
}

if (empty($car_type)) {
    $errors[] = "نوع السيارة مطلوب";
}

if (empty($chassis)) {
    $errors[] = "رقم الشاصي مطلوب";
} elseif (strlen($chassis) !== 17) {
    $errors[] = "رقم الشاصي يجب أن يتكون من 17 خانة بالضبط";
}

if (empty($service_type)) {
    $errors[] = "نوع الخدمة مطلوب";
}

if (empty($details)) {
    $errors[] = "تفاصيل الطلب مطلوبة";
}

// إذا كانت هناك أخطاء، إرجاع المستخدم مع رسائل الخطأ
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = [
        'alternative_email' => $alt_email,
        'phone' => $phone,
        'car_type' => $car_type,
        'chassis' => $chassis,
        'year' => $year,
        'service_type' => $service_type,
        'details' => $details
    ];
    header("Location: create_ticket.php");
    exit;
}

// تهيئة المتغيرات
$dump_filename = '';
$image_paths = [];

// تعيين الأذونات المناسبة للمجلدات
$upload_permissions = 0755; // أكثر أمانًا من 0777

// رفع ملف السوفوير
if (!empty($_FILES['software_dump']['name'])) {
    $upload_dir = 'uploads/dumps/';
    
    // إنشاء المجلد إذا لم يكن موجودًا
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, $upload_permissions, true);
    }
    
    // التحقق من امتداد الملف
    if (allowed_file($_FILES['software_dump']['name'], ['zip', 'rar', 'bin', 'hex', 'pdf'])) {
        $safe_filename = generate_safe_filename($_FILES['software_dump']['name']);
        $dump_path = $upload_dir . $safe_filename;
        
        // التحقق من حجم الملف (10MB كحد أقصى)
        if ($_FILES['software_dump']['size'] <= 10 * 1024 * 1024) {
            if (move_uploaded_file($_FILES['software_dump']['tmp_name'], $dump_path)) {
                $dump_filename = $dump_path;
            } else {
                $errors[] = "فشل في رفع ملف السوفوير";
            }
        } else {
            $errors[] = "حجم ملف السوفوير كبير جدًا. الحد الأقصى هو 10 ميجابايت";
        }
    } else {
        $errors[] = "نوع ملف السوفوير غير مدعوم. الأنواع المدعومة: zip, rar, bin, hex, pdf";
    }
}

// رفع الصور
if (!empty($_FILES['images']['name'][0])) {
    $img_dir = 'uploads/images/';
    
    // إنشاء المجلد إذا لم يكن موجودًا
    if (!is_dir($img_dir)) {
        mkdir($img_dir, $upload_permissions, true);
    }
    
    foreach ($_FILES['images']['name'] as $key => $img_name) {
        // تجاهل الملفات الفارغة
        if (empty($img_name)) continue;
        
        // التحقق من امتداد الصورة
        if (allowed_file($img_name, ['jpg', 'jpeg', 'png'])) {
            // التحقق من حجم الصورة (5MB كحد أقصى)
            if ($_FILES['images']['size'][$key] <= 5 * 1024 * 1024) {
                $safe_img_name = generate_safe_filename($img_name);
                $img_path = $img_dir . $safe_img_name;
                
                if (move_uploaded_file($_FILES['images']['tmp_name'][$key], $img_path)) {
                    $image_paths[] = $img_path;
                } else {
                    $errors[] = "فشل في رفع الصورة: $img_name";
                }
            } else {
                $errors[] = "حجم الصورة $img_name كبير جدًا. الحد الأقصى هو 5 ميجابايت";
            }
        } else {
            $errors[] = "نوع الصورة $img_name غير مدعوم. الأنواع المدعومة: jpg, jpeg, png";
        }
    }
}

$image_files = implode(',', $image_paths);

// التحقق من الأخطاء مرة أخرى بعد رفع الملفات
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = [
        'alternative_email' => $alt_email,
        'phone' => $phone,
        'car_type' => $car_type,
        'chassis' => $chassis,
        'year' => $year,
        'service_type' => $service_type,
        'details' => $details
    ];
    header("Location: create_ticket.php");
    exit;
}

try {
    // استخدام Prepared Statements لحماية من حقن SQL
    $sql = "INSERT INTO tickets (username, primary_email, alt_email, phone, car_type, chassis, year, service_type, details, dump_file, image_files, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    // التحقق من استخدام MySQLi أو PDO
    if ($conn instanceof mysqli) {
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssssssssss", 
            $username, $primary_email, $alt_email, $phone, $car_type, 
            $chassis, $year, $service_type, $details, $dump_filename, $image_files);
        
        $success = mysqli_stmt_execute($stmt);
        if ($success) {
            $ticket_id = mysqli_insert_id($conn);
        } else {
            throw new Exception(mysqli_error($conn));
        }
        
        mysqli_stmt_close($stmt);
    } else {
        // إذا كان PDO
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $username, $primary_email, $alt_email, $phone, $car_type,
            $chassis, $year, $service_type, $details, $dump_filename, $image_files
        ]);
        
        $ticket_id = $conn->lastInsertId();
    }
    
    // بعد إنشاء التذكرة بنجاح
    if (isset($ticket_id) && $ticket_id) {
        // إنشاء رقم تذكرة بتنسيق خاص
        $formatted_ticket_id = 'FLEX-' . str_pad($ticket_id, 5, '0', STR_PAD_LEFT);
        
        // إرسال بريد تنبيه بطريقة أكثر أمانًا
        $to = "raedfss@hotmail.com";
        $subject = "🆕 تذكرة جديدة من " . $username . " - FlexAuto";
        
        // بناء محتوى البريد بطريقة آمنة
        $body = "تم استلام تذكرة جديدة.\n\n"
              . "رقم التذكرة: " . $formatted_ticket_id . "\n"
              . "الاسم: " . $username . "\n"
              . "الهاتف: " . $phone . "\n"
              . "البريد: " . $primary_email . "\n";
        
        if (!empty($alt_email)) {
            $body .= "البريد البديل: " . $alt_email . "\n";
        }
        
        $body .= "السيارة: " . $car_type;
        
        if (!empty($year)) {
            $body .= " - " . $year;
        }
        
        $body .= "\nالشاسيه: " . $chassis . "\n"
               . "الخدمة: " . $service_type . "\n\n"
               . "الوصف:\n" . $details . "\n\n"
               . "تم الإنشاء في: " . date("Y-m-d H:i") . "\n";
        
        if (!empty($dump_filename)) {
            $body .= "تم إرفاق ملف سوفوير: نعم\n";
        }
        
        if (!empty($image_files)) {
            $body .= "تم إرفاق صور: " . count($image_paths) . "\n";
        }
        
        // إعداد الهيدرز للبريد الإلكتروني
        $headers = "From: noreply@flexauto.com" . "\r\n" .
                   "Reply-To: " . $primary_email . "\r\n" .
                   "X-Mailer: PHP/" . phpversion();
        
        // محاولة إرسال البريد
        mail($to, $subject, $body, $headers);
        
        // تخزين رقم التذكرة في الجلسة
        $_SESSION['ticket_id'] = $ticket_id;
        $_SESSION['formatted_ticket_id'] = $formatted_ticket_id;
        
        // إعادة توجيه إلى صفحة الشكر
        header("Location: thank_you.php?id=" . $ticket_id);
        exit;
    } else {
        throw new Exception("فشل في إنشاء رقم التذكرة");
    }
} catch (Exception $e) {
    // تسجيل الخطأ في ملف سجل
    error_log("Error creating ticket: " . $e->getMessage());
    
    $_SESSION['error_message'] = "حدث خطأ أثناء إنشاء التذكرة. يرجى المحاولة مرة أخرى لاحقًا.";
    header("Location: create_ticket.php");
    exit;
}
?>