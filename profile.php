<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

// استيراد الملفات الضرورية
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

$username = $_SESSION['username'];
$user_type = $_SESSION['user_role'] ?? 'user';
$email = $_SESSION['email'] ?? '';
$user_id = $_SESSION['user_id'];

// إعداد عنوان الصفحة
$page_title = 'الملف الشخصي';
$display_title = 'إدارة الملف الشخصي';

$success = '';
$error = '';

// جلب بيانات المستخدم الحالية
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// التحقق من وجود معلومات الملف الشخصي الإضافية
$stmt = $pdo->prepare("
    SELECT * FROM user_profiles 
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

// جلب الوثائق المرفقة (إن وجدت)
$stmt = $pdo->prepare("
    SELECT * FROM user_documents 
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تحويل مصفوفة الوثائق إلى مصفوفة مفاتيح للسهولة
$user_docs = [];
foreach ($documents as $doc) {
    $user_docs[$doc['document_type']] = $doc;
}

// عند تقديم النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من توكن CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "❌ فشل التحقق من الأمان. يرجى تحديث الصفحة والمحاولة مرة أخرى.";
    } else {
        // تحديد نوع النموذج المقدم
        $form_type = $_POST['form_type'] ?? '';
        
        // معالجة نموذج البيانات الأساسية
        if ($form_type === 'basic_info') {
            $new_username = sanitizeInput($_POST['username']);
            $new_password = $_POST['password'];
            $confirm = $_POST['confirm'];
            
            if (empty($new_username)) {
                $error = "❌ يجب إدخال الاسم.";
            } elseif (!empty($new_password) && $new_password !== $confirm) {
                $error = "❌ كلمتا المرور غير متطابقتين.";
            } else {
                try {
                    // تحديث الاسم
                    $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                    $stmt->execute([$new_username, $user_id]);
                    $_SESSION['username'] = $new_username;

                    // تحديث كلمة المرور إن وُجدت
                    if (!empty($new_password)) {
                        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed, $user_id]);
                    }

                    $success = "✅ تم تحديث بيانات الحساب الأساسية بنجاح.";
                    
                    // تسجيل النشاط
                    logActivity('profile_update', 'تم تحديث بيانات الملف الشخصي الأساسية', $user_id);
                } catch (PDOException $e) {
                    $error = "❌ حدث خطأ أثناء تحديث البيانات. الرجاء المحاولة مرة أخرى.";
                    logError('Error updating profile: ' . $e->getMessage());
                }
            }
        }
        
        // معالجة نموذج معلومات العميل
        elseif ($form_type === 'client_info') {
            $shop_name = sanitizeInput($_POST['shop_name']);
            $phone = sanitizeInput($_POST['phone']);
            $address = sanitizeInput($_POST['address']);
            $city = sanitizeInput($_POST['city']);
            $country = sanitizeInput($_POST['country']);
            
            try {
                // التحقق من وجود سجل سابق
                if ($profile) {
                    // تحديث السجل الموجود
                    $stmt = $pdo->prepare("
                        UPDATE user_profiles SET 
                        shop_name = ?, phone = ?, address = ?, city = ?, country = ?, updated_at = NOW()
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$shop_name, $phone, $address, $city, $country, $user_id]);
                } else {
                    // إنشاء سجل جديد
                    $stmt = $pdo->prepare("
                        INSERT INTO user_profiles 
                        (user_id, shop_name, phone, address, city, country, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([$user_id, $shop_name, $phone, $address, $city, $country]);
                }
                
                $success = "✅ تم تحديث معلومات العميل بنجاح.";
                
                // تحديث البيانات المحلية
                $profile = [
                    'shop_name' => $shop_name,
                    'phone' => $phone,
                    'address' => $address,
                    'city' => $city,
                    'country' => $country
                ];
                
                // تسجيل النشاط
                logActivity('profile_update', 'تم تحديث معلومات العميل', $user_id);
            } catch (PDOException $e) {
                $error = "❌ حدث خطأ أثناء تحديث معلومات العميل. الرجاء المحاولة مرة أخرى.";
                logError('Error updating client info: ' . $e->getMessage());
            }
        }
        
        // معالجة نموذج معلومات المبرمج
        elseif ($form_type === 'programmer_info') {
            $programmer_name = sanitizeInput($_POST['programmer_name']);
            $programmer_serial = sanitizeInput($_POST['programmer_serial']);
            $programmer_type = sanitizeInput($_POST['programmer_type']);
            $dongle_number = sanitizeInput($_POST['dongle_number']);
            
            try {
                // التحقق من وجود سجل سابق
                if ($profile) {
                    // تحديث السجل الموجود
                    $stmt = $pdo->prepare("
                        UPDATE user_profiles SET 
                        programmer_name = ?, programmer_serial = ?, programmer_type = ?, dongle_number = ?, updated_at = NOW()
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$programmer_name, $programmer_serial, $programmer_type, $dongle_number, $user_id]);
                } else {
                    // إنشاء سجل جديد
                    $stmt = $pdo->prepare("
                        INSERT INTO user_profiles 
                        (user_id, programmer_name, programmer_serial, programmer_type, dongle_number, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([$user_id, $programmer_name, $programmer_serial, $programmer_type, $dongle_number]);
                }
                
                $success = "✅ تم تحديث معلومات المبرمج بنجاح.";
                
                // تحديث البيانات المحلية
                if (!$profile) $profile = [];
                $profile['programmer_name'] = $programmer_name;
                $profile['programmer_serial'] = $programmer_serial;
                $profile['programmer_type'] = $programmer_type;
                $profile['dongle_number'] = $dongle_number;
                
                // تسجيل النشاط
                logActivity('profile_update', 'تم تحديث معلومات المبرمج', $user_id);
            } catch (PDOException $e) {
                $error = "❌ حدث خطأ أثناء تحديث معلومات المبرمج. الرجاء المحاولة مرة أخرى.";
                logError('Error updating programmer info: ' . $e->getMessage());
            }
        }
        
        // معالجة الموافقة على التعهد القانوني
        elseif ($form_type === 'legal_agreement') {
            if (isset($_POST['agree_terms']) && $_POST['agree_terms'] == '1') {
                try {
                    // تحديث حالة الموافقة في السجل
                    if ($profile) {
                        $stmt = $pdo->prepare("
                            UPDATE user_profiles SET 
                            legal_agreement = 1, legal_agreement_date = NOW(), updated_at = NOW()
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$user_id]);
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO user_profiles 
                            (user_id, legal_agreement, legal_agreement_date, created_at, updated_at)
                            VALUES (?, 1, NOW(), NOW(), NOW())
                        ");
                        $stmt->execute([$user_id]);
                    }
                    
                    $success = "✅ تم تسجيل موافقتك على التعهد القانوني بنجاح.";
                    
                    // تحديث البيانات المحلية
                    if (!$profile) $profile = [];
                    $profile['legal_agreement'] = 1;
                    $profile['legal_agreement_date'] = date('Y-m-d H:i:s');
                    
                    // تسجيل النشاط
                    logActivity('legal_agreement', 'تم الموافقة على التعهد القانوني', $user_id);
                } catch (PDOException $e) {
                    $error = "❌ حدث خطأ أثناء تسجيل الموافقة. الرجاء المحاولة مرة أخرى.";
                    logError('Error updating legal agreement: ' . $e->getMessage());
                }
            } else {
                $error = "❌ يجب الموافقة على الشروط والأحكام للمتابعة.";
            }
        }
        
        // معالجة رفع المستندات
        elseif ($form_type === 'document_upload') {
            $document_type = sanitizeInput($_POST['document_type']);
            $file = $_FILES['document_file'] ?? null;
            
            // التحقق من صحة نوع المستند
            $allowed_types = ['shop_photo', 'id_card', 'business_license'];
            if (!in_array($document_type, $allowed_types)) {
                $error = "❌ نوع المستند غير صالح.";
            } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                $error = "❌ حدث خطأ أثناء رفع الملف. الرجاء المحاولة مرة أخرى.";
            } else {
                // فحص نوع وحجم الملف
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
                $file_info = pathinfo($file['name']);
                $ext = strtolower($file_info['extension'] ?? '');
                
                if (!in_array($ext, $allowed_extensions)) {
                    $error = "❌ امتداد الملف غير مدعوم. يجب أن يكون بصيغة jpg أو jpeg أو png أو pdf.";
                } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB
                    $error = "❌ حجم الملف كبير. الحد الأقصى المسموح هو 5 ميجابايت.";
                } else {
                    // توليد اسم فريد للملف
                    $filename = 'doc_' . $user_id . '_' . $document_type . '_' . uniqid() . '.' . $ext;
                    $upload_dir = __DIR__ . '/uploads/documents/';
                    $destination = $upload_dir . $filename;
                    
                    // التأكد من وجود المجلد
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        try {
                            // التحقق من وجود المستند بالفعل
                            $existing_doc = isset($user_docs[$document_type]);
                            
                            if ($existing_doc) {
                                // حذف الملف القديم
                                $old_file = $upload_dir . $user_docs[$document_type]['file_name'];
                                if (file_exists($old_file)) {
                                    unlink($old_file);
                                }
                                
                                // تحديث سجل المستند
                                $stmt = $pdo->prepare("
                                    UPDATE user_documents SET 
                                    file_name = ?, original_name = ?, uploaded_at = NOW(), status = 'pending'
                                    WHERE user_id = ? AND document_type = ?
                                ");
                                $stmt->execute([$filename, $file['name'], $user_id, $document_type]);
                            } else {
                                // إنشاء سجل جديد
                                $stmt = $pdo->prepare("
                                    INSERT INTO user_documents 
                                    (user_id, document_type, file_name, original_name, uploaded_at, status)
                                    VALUES (?, ?, ?, ?, NOW(), 'pending')
                                ");
                                $stmt->execute([$user_id, $document_type, $filename, $file['name']]);
                            }
                            
                            $success = "✅ تم رفع المستند بنجاح وسيتم مراجعته قريبًا.";
                            
                            // تحديث مصفوفة الوثائق
                            $user_docs[$document_type] = [
                                'document_type' => $document_type,
                                'file_name' => $filename,
                                'original_name' => $file['name'],
                                'uploaded_at' => date('Y-m-d H:i:s'),
                                'status' => 'pending'
                            ];
                            
                            // تسجيل النشاط
                            logActivity('document_upload', "تم رفع مستند: $document_type", $user_id);
                        } catch (PDOException $e) {
                            $error = "❌ حدث خطأ أثناء حفظ بيانات المستند. الرجاء المحاولة مرة أخرى.";
                            logError('Error saving document: ' . $e->getMessage());
                            
                            // حذف الملف المرفوع في حالة الخطأ
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

// تحليل حالة الملف الشخصي
$profile_complete = false;
$docs_complete = false;
$legal_agreed = false;

// التحقق من اكتمال المعلومات الأساسية
if ($profile && !empty($profile['shop_name']) && !empty($profile['phone']) && !empty($profile['address'])) {
    $profile_complete = true;
}

// التحقق من اكتمال المستندات
$required_docs = ['shop_photo', 'id_card', 'business_license'];
$docs_count = 0;
foreach ($required_docs as $doc_type) {
    if (isset($user_docs[$doc_type]) && $user_docs[$doc_type]['status'] === 'approved') {
        $docs_count++;
    }
}
if ($docs_count === count($required_docs)) {
    $docs_complete = true;
}

// التحقق من الموافقة على التعهد
if ($profile && !empty($profile['legal_agreement'])) {
    $legal_agreed = true;
}

// التحقق من اكتمال الملف الشخصي بالكامل
$full_profile_complete = $profile_complete && $docs_complete && $legal_agreed;

// إنشاء توكن CSRF لحماية النموذج
$csrf_token = generateCSRFToken();

// CSS مخصص للصفحة
$page_css = <<<CSS
.container {
  background: rgba(0, 0, 0, 0.7);
  padding: 35px;
  width: 95%;
  max-width: 1000px;
  border-radius: 16px;
  text-align: center;
  margin: 30px auto;
  box-shadow: 0 0 40px rgba(0, 200, 255, 0.15);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(66, 135, 245, 0.25);
}
.avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: linear-gradient(145deg, #3494e6, #ec6ead);
  margin: 0 auto 15px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  color: white;
  box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}
.user-email {
  color: #a8d8ff;
  margin: 5px auto 15px;
  font-size: 18px;
}
.profile-status {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 15px;
  margin: 20px 0;
}
.status-item {
  background: rgba(0, 40, 80, 0.4);
  border-radius: 10px;
  padding: 10px 15px;
  font-size: 14px;
  min-width: 150px;
  position: relative;
}
.status-item.incomplete {
  border: 1px solid rgba(255, 87, 87, 0.5);
}
.status-item.complete {
  border: 1px solid rgba(87, 255, 173, 0.5);
}
.status-badge {
  position: absolute;
  top: -8px;
  right: -8px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  text-align: center;
  line-height: 20px;
  font-size: 12px;
}
.badge-complete {
  background: #00cc66;
  color: white;
}
.badge-incomplete {
  background: #ff5757;
  color: white;
}
.profile-tabs {
  display: flex;
  justify-content: center;
  gap: 10px;
  margin: 30px 0 20px;
  flex-wrap: wrap;
}
.tab-btn {
  padding: 10px 15px;
  background: rgba(0, 40, 80, 0.4);
  border: 1px solid rgba(66, 135, 245, 0.4);
  border-radius: 8px;
  color: white;
  cursor: pointer;
  font-weight: bold;
  transition: 0.3s;
}
.tab-btn:hover {
  background: rgba(30, 70, 120, 0.6);
}
.tab-btn.active {
  background: linear-gradient(145deg, #1e90ff, #0070cc);
  border-color: transparent;
}
.form {
  max-width: 700px;
  margin: 0 auto;
  text-align: right;
  padding: 20px;
  background: rgba(0, 40, 80, 0.2);
  border-radius: 12px;
  border: 1px solid rgba(66, 135, 245, 0.2);
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
.form-group input, .form-group select, .form-group textarea {
  width: 100%;
  padding: 12px;
  border-radius: 8px;
  border: 1px solid rgba(66, 135, 245, 0.4);
  background: rgba(0, 40, 80, 0.4);
  color: white;
  box-sizing: border-box;
}
.form-group textarea {
  min-height: 120px;
  resize: vertical;
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
.btn-secondary {
  background: rgba(150, 150, 150, 0.2);
  color: #eee;
  border: 1px solid rgba(200, 200, 200, 0.3);
}
.btn-secondary:hover {
  background: rgba(150, 150, 150, 0.3);
}
.document-list {
  margin: 20px 0;
  text-align: right;
}
.document-item {
  background: rgba(0, 40, 80, 0.3);
  border-radius: 8px;
  padding: 15px;
  margin-bottom: 15px;
  border: 1px solid rgba(66, 135, 245, 0.2);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.document-info {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.document-name {
  font-weight: bold;
  color: #a8d8ff;
}
.document-status {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 15px;
  font-size: 12px;
  margin-right: 10px;
}
.status-pending {
  background: rgba(255, 166, 0, 0.2);
  color: #ffa600;
  border: 1px solid rgba(255, 166, 0, 0.3);
}
.status-approved {
  background: rgba(0, 204, 102, 0.2);
  color: #00cc66;
  border: 1px solid rgba(0, 204, 102, 0.3);
}
.status-rejected {
  background: rgba(255, 76, 76, 0.2);
  color: #ff4c4c;
  border: 1px solid rgba(255, 76, 76, 0.3);
}
.tab-content {
  display: none;
}
.tab-content.active {
  display: block;
}
.alert {
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
  position: relative;
  text-align: right;
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
.alert-warning {
  background: rgba(255, 193, 7, 0.2);
  border: 1px solid rgba(255, 193, 7, 0.5);
  color: #ffcb57;
}
.alert-info {
  background: rgba(23, 162, 184, 0.2);
  border: 1px solid rgba(23, 162, 184, 0.5);
  color: #5dccff;
}
.legal-text {
  text-align: justify;
  background: rgba(0, 0, 0, 0.3);
  padding: 20px;
  border-radius: 8px;
  max-height: 250px;
  overflow-y: auto;
  margin-bottom: 20px;
  line-height: 1.6;
  font-size: 14px;
  border: 1px solid rgba(66, 135, 245, 0.2);
}
.checkbox-group {
  display: flex;
  align-items: center;
  gap: 10px;
  justify-content: flex-start;
  margin: 20px 0;
}
.checkbox-group input[type="checkbox"] {
  width: 20px;
  height: 20px;
}
.checkbox-label {
  font-weight: bold;
  color: #a8d8ff;
}
.profile-completion {
  background: rgba(0, 0, 0, 0.3);
  border-radius: 10px;
  padding: 15px;
  margin-bottom: 25px;
  border: 1px solid rgba(66, 135, 245, 0.3);
}
.completion-bar {
  background: rgba(150, 150, 150, 0.2);
  height: 10px;
  border-radius: 5px;
  margin: 10px 0;
  overflow: hidden;
}
.completion-progress {
  height: 100%;
  background: linear-gradient(90deg, #1e90ff, #00cc66);
  border-radius: 5px;
  transition: width 0.5s;
}
.completion-text {
  text-align: center;
  font-size: 14px;
  color: #a8d8ff;
}
CSS;

// تعريف محتوى الصفحة
ob_start();

// حساب نسبة اكتمال الملف الشخصي
$completion_steps = 0;
$total_steps = 3; // معلومات أساسية + معلومات العميل + التعهد القانوني
if (isset($user['username']) && !empty($user['username'])) $completion_steps++;
if ($profile_complete) $completion_steps++;
if ($legal_agreed) $completion_steps++;

$completion_percentage = round(($completion_steps / $total_steps) * 100);

// حساب عدد المستندات المكتملة
$doc_steps = count(array_filter($user_docs, function($doc) {
    return $doc['status'] === 'approved';
}));
$doc_percentage = count($required_docs) > 0 ? round(($doc_steps / count($required_docs)) * 100) : 0;

// تحديد حالة اكتمال الخدمات المختلفة
$basic_services_available = isset($user['username']) && !empty($user['username']);
$advanced_services_available = $profile_complete;
$security_services_available = $full_profile_complete;
?>
<div class="container">
    <div class="avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
    <h2><?= $display_title ?></h2>
    <div class="user-email"><?= htmlspecialchars($email) ?></div>
    
    <?php if (!$full_profile_complete): ?>
    <div class="alert alert-warning">
        <strong>⚠️ تنبيه هام:</strong> بعض خدمات FlexAuto (خاصة الأمنية والتقنية المتقدمة) لن تكون متاحة إلا بعد استكمال الملف الشخصي بالكامل وتقديم الوثائق الرسمية والموافقة على التعهد.
    </div>
    <?php endif; ?>
    
    <div class="profile-completion">
        <strong>نسبة اكتمال الملف الشخصي: <?= $completion_percentage ?>%</strong>
        <div class="completion-bar">
            <div class="completion-progress" style="width: <?= $completion_percentage ?>%;"></div>
        </div>
        <div class="completion-text">
            <?php if ($completion_percentage < 100): ?>
                أكمل ملفك الشخصي للوصول إلى جميع الخدمات
            <?php else: ?>
                ملفك الشخصي مكتمل! يمكنك الوصول إلى جميع الخدمات الأساسية
            <?php endif; ?>
        </div>
        
        <strong>نسبة اكتمال الوثائق: <?= $doc_percentage ?>%</strong>
        <div class="completion-bar">
            <div class="completion-progress" style="width: <?= $doc_percentage ?>%;"></div>
        </div>
        <div class="completion-text">
            <?php if ($doc_percentage < 100): ?>
                قم برفع الوثائق المطلوبة للوصول إلى الخدمات الأمنية
            <?php else: ?>
                جميع الوثائق مكتملة ومعتمدة!
            <?php endif; ?>
        </div>
    </div>
    
    <div class="profile-status">
        <div class="status-item <?= $basic_services_available ? 'complete' : 'incomplete' ?>">
            الخدمات الأساسية
            <?php if ($basic_services_available): ?>
                <span class="status-badge badge-complete">✓</span>
            <?php else: ?>
                <span class="status-badge badge-incomplete">✗</span>
            <?php endif; ?>
        </div>
        <div class="status-item <?= $advanced_services_available ? 'complete' : 'incomplete' ?>">
            خدمات البرمجة المتقدمة
            <?php if ($advanced_services_available): ?>
                <span class="status-badge badge-complete">✓</span>
            <?php else: ?>
                <span class="status-badge badge-incomplete">✗</span>
            <?php endif; ?>
        </div>
        <div class="status-item <?= $security_services_available ? 'complete' : 'incomplete' ?>">
            خدمات الأمان والحماية
            <?php if ($security_services_available): ?>
                <span class="status-badge badge-complete">✓</span>
            <?php else: ?>
                <span class="status-badge badge-incomplete">✗</span>
            <?php endif; ?>
        </div>
    </div>

    <?php
    if (!empty($error)) {
        showMessage("danger", $error);
    }
    if (!empty($success)) {
        showMessage("success", $success);
    }
    ?>

    <div class="profile-tabs">
        <button class="tab-btn active" data-tab="basic-info">البيانات الأساسية</button>
        <button class="tab-btn" data-tab="client-info">معلومات العميل</button>
        <button class="tab-btn" data-tab="documents">الوثائق الرسمية</button>
        <button class="tab-btn" data-tab="legal">التعهد القانوني</button>
        <button class="tab-btn" data-tab="programmer">بيانات المبرمجة</button>
    </div>

    <!-- قسم البيانات الأساسية -->
    <div class="tab-content active" id="basic-info">
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="form">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="form_type" value="basic_info">
            
            <div class="form-group">
                <label for="username">الاسم الكامل:</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>

            <div class="form-group">
                <label>البريد الإلكتروني (غير قابل للتعديل):</label>
                <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
            </div>

            <div class="form-group">
                <label for="password">كلمة مرور جديدة (اختياري):</label>
                <input type="password" id="password" name="password">
                <small class="form-text">اتركها فارغة إذا لم تكن ترغب في تغيير كلمة المرور</small>
            </div>

            <div class="form-group">
                <label for="confirm">تأكيد كلمة المرور:</label>
                <input type="password" id="confirm" name="confirm">
            </div>

            <button type="submit" class="btn btn-primary">تحديث البيانات الأساسية</button>
        </form>
    </div>

    <!-- قسم معلومات العميل -->
    <div class="tab-content" id="client-info">
        <div class="alert alert-info">
            <strong>ملاحظة:</strong> تعبئة معلومات العميل اختيارية، لكن تعتبر ضرورية للوصول إلى خدمات البرمجة المتقدمة.
        </div>
        
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="form">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="form_type" value="client_info">
            
            <div class="form-group">
                <label for="shop_name">اسم الورشة أو الشركة:</label>
                <input type="text" id="shop_name" name="shop_name" value="<?= htmlspecialchars($profile['shop_name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="phone">رقم الهاتف:</label>
                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="address">العنوان الكامل:</label>
                <textarea id="address" name="address"><?= htmlspecialchars($profile['address'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="city">المدينة:</label>
                <input type="text" id="city" name="city" value="<?= htmlspecialchars($profile['city'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="country">الدولة:</label>
                <select id="country" name="country">
                    <option value="">-- اختر الدولة --</option>
                    <option value="Jordan" <?= ($profile['country'] ?? '') === 'Jordan' ? 'selected' : '' ?>>الأردن</option>
                    <option value="Saudi Arabia" <?= ($profile['country'] ?? '') === 'Saudi Arabia' ? 'selected' : '' ?>>السعودية</option>
                    <option value="UAE" <?= ($profile['country'] ?? '') === 'UAE' ? 'selected' : '' ?>>الإمارات</option>
                    <option value="Egypt" <?= ($profile['country'] ?? '') === 'Egypt' ? 'selected' : '' ?>>مصر</option>
                    <option value="Iraq" <?= ($profile['country'] ?? '') === 'Iraq' ? 'selected' : '' ?>>العراق</option>
                    <option value="Kuwait" <?= ($profile['country'] ?? '') === 'Kuwait' ? 'selected' : '' ?>>الكويت</option>
                    <option value="Qatar" <?= ($profile['country'] ?? '') === 'Qatar' ? 'selected' : '' ?>>قطر</option>
                    <option value="Bahrain" <?= ($profile['country'] ?? '') === 'Bahrain' ? 'selected' : '' ?>>البحرين</option>
                    <option value="Oman" <?= ($profile['country'] ?? '') === 'Oman' ? 'selected' : '' ?>>عمان</option>
                    <option value="Lebanon" <?= ($profile['country'] ?? '') === 'Lebanon' ? 'selected' : '' ?>>لبنان</option>
                    <option value="Syria" <?= ($profile['country'] ?? '') === 'Syria' ? 'selected' : '' ?>>سوريا</option>
                    <option value="Palestine" <?= ($profile['country'] ?? '') === 'Palestine' ? 'selected' : '' ?>>فلسطين</option>
                    <option value="Other" <?= ($profile['country'] ?? '') === 'Other' ? 'selected' : '' ?>>دولة أخرى</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">تحديث معلومات العميل</button>
        </form>
    </div>

    <!-- قسم الوثائق الرسمية -->
    <div class="tab-content" id="documents">
        <div class="alert alert-info">
            <strong>ملاحظة:</strong> لضمان أمان الخدمات وحماية المستخدمين، نطلب تقديم المستندات التالية للتحقق من هوية الورشة أو الشركة. هذه المستندات ضرورية للوصول إلى الخدمات الأمنية والحساسة.
        </div>
        
        <div class="document-list">
            <h3>المستندات المطلوبة:</h3>
            
            <?php foreach(['shop_photo', 'id_card', 'business_license'] as $doc_type): ?>
                <?php 
                    $doc_exists = isset($user_docs[$doc_type]);
                    $doc_status = $doc_exists ? $user_docs[$doc_type]['status'] : 'غير مرفوع';
                    $doc_status_class = $doc_exists ? 'status-' . $user_docs[$doc_type]['status'] : '';
                    
                    $doc_names = [
                        'shop_photo' => 'صورة لمحل الورشة',
                        'id_card' => 'صورة الهوية الشخصية / جواز السفر',
                        'business_license' => 'صورة رخصة المهن أو السجل التجاري'
                    ];
                    
                    $doc_name = $doc_names[$doc_type];
                ?>
                <div class="document-item">
                    <div class="document-info">
                        <div class="document-name"><?= $doc_name ?></div>
                        <div>
                            <span class="document-status <?= $doc_status_class ?>">
                                <?php 
                                    if (!$doc_exists) {
                                        echo "غير مرفوع";
                                    } else {
                                        switch($user_docs[$doc_type]['status']) {
                                            case 'pending': echo "قيد المراجعة"; break;
                                            case 'approved': echo "تمت الموافقة"; break;
                                            case 'rejected': echo "مرفوض"; break;
                                            default: echo $user_docs[$doc_type]['status']; break;
                                        }
                                    }
                                ?>
                            </span>
                            <?php if ($doc_exists): ?>
                                <small>تم الرفع: <?= date('Y-m-d', strtotime($user_docs[$doc_type]['uploaded_at'])) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div>
                        <button type="button" class="btn btn-secondary upload-doc-btn" data-doctype="<?= $doc_type ?>">
                            <?= $doc_exists ? 'تحديث' : 'رفع' ?> المستند
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- نموذج رفع المستندات (مخفي يظهر عند الضغط على زر الرفع) -->
            <div id="upload-form-container" style="display: none;">
                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" enctype="multipart/form-data" class="form">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="form_type" value="document_upload">
                    <input type="hidden" id="document_type" name="document_type" value="">
                    
                    <div class="form-group">
                        <label for="document_file">اختر الملف:</label>
                        <input type="file" id="document_file" name="document_file" required accept=".jpg,.jpeg,.png,.pdf">
                        <small class="form-text">الصيغ المدعومة: JPG، JPEG، PNG، PDF. الحجم الأقصى: 5 ميجابايت</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">رفع المستند</button>
                    <button type="button" class="btn btn-secondary" id="cancel-upload">إلغاء</button>
                </form>
            </div>
        </div>
    </div>

    <!-- قسم التعهد القانوني -->
    <div class="tab-content" id="legal">
        <div class="alert alert-info">
            <strong>تنبيه:</strong> الموافقة على هذا التعهد ضرورية للوصول إلى خدمات الأمان وفك الشيفرات.
        </div>
        
        <div class="legal-text">
            <h3>التعهد القانوني للاستخدام:</h3>
            <p>أتعهد أنا الموقع أدناه بأن جميع الخدمات التي أطلبها من خلال منصة FlexAuto سيتم استخدامها لأغراض قانونية ومشروعة، وتحت مسؤوليتي الكاملة، وفق قوانين الدولة التي أعمل ضمن نطاقها.</p>
            <p>أُقرّ بأنني الممثل القانوني للورشة أو الشركة، وأني أتحمل كامل المسؤولية في حال استخدام أي أكواد أو أدوات برمجية من المنصة بشكل غير قانوني.</p>
            <p>كما أوافق على أن تحتفظ المنصة بنسخ من وثائقي الرسمية للتحقق من مشروعية الطلبات.</p>
            <p>أُقر بأن استخدام أدوات البرمجة والخدمات التقنية المتاحة عبر منصة FlexAuto سيكون حصرياً لأغراض صيانة وإصلاح المركبات التي أملك تصريحاً قانونياً للعمل عليها، وأن أي استخدام لهذه الأدوات للتحايل على أنظمة حماية المركبات، أو لأي غرض غير قانوني، يعتبر انتهاكاً للاتفاقية ويعرضني للمساءلة القانونية.</p>
            <p>أتعهد بعدم استخدام خدمات فك حماية الـ Immobilizer أو تعديل بيانات الـ ECU إلا على المركبات المملوكة لعملائي بشكل قانوني، وبعد التأكد من ملكيتهم الشرعية للمركبة والحصول على موافقتهم الخطية.</p>
            <p>أوافق على أن شركة FlexAuto ليست مسؤولة عن أي سوء استخدام للخدمات المقدمة من قبلي أو من قبل أي شخص يعمل لديّ، وأتحمل المسؤولية الكاملة عن جميع العمليات التي تتم باستخدام حسابي.</p>
        </div>
        
        <?php if (!$legal_agreed): ?>
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="form">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="form_type" value="legal_agreement">
            
            <div class="checkbox-group">
                <input type="checkbox" id="agree_terms" name="agree_terms" value="1">
                <label for="agree_terms" class="checkbox-label">أوافق على جميع الشروط والأحكام المذكورة أعلاه</label>
            </div>
            
            <button type="submit" class="btn btn-primary">تأكيد الموافقة</button>
        </form>
        <?php else: ?>
        <div class="alert alert-success">
            <strong>✓ تمت الموافقة على التعهد القانوني</strong>
            <p>لقد وافقت على التعهد القانوني بتاريخ: <?= date('Y-m-d', strtotime($profile['legal_agreement_date'])) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- قسم بيانات المبرمجة -->
    <div class="tab-content" id="programmer">
        <div class="alert alert-info">
            <strong>ملاحظة:</strong> هذه البيانات اختيارية وتساعدنا في تقديم خدمة أفضل خاصة بأجهزة البرمجة التي تستخدمها.
        </div>
        
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="form">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="form_type" value="programmer_info">
            
            <div class="form-group">
                <label for="programmer_name">اسم المبرمجة المستخدمة:</label>
                <select id="programmer_name" name="programmer_name">
                    <option value="">-- اختر --</option>
                    <option value="KTAG" <?= ($profile['programmer_name'] ?? '') === 'KTAG' ? 'selected' : '' ?>>KTAG</option>
                    <option value="KESS" <?= ($profile['programmer_name'] ?? '') === 'KESS' ? 'selected' : '' ?>>KESS</option>
                    <option value="Flex" <?= ($profile['programmer_name'] ?? '') === 'Flex' ? 'selected' : '' ?>>Flex</option>
                    <option value="FGTech" <?= ($profile['programmer_name'] ?? '') === 'FGTech' ? 'selected' : '' ?>>FGTech</option>
                    <option value="CMD" <?= ($profile['programmer_name'] ?? '') === 'CMD' ? 'selected' : '' ?>>CMD</option>
                    <option value="Xprog" <?= ($profile['programmer_name'] ?? '') === 'Xprog' ? 'selected' : '' ?>>Xprog</option>
                    <option value="Launch" <?= ($profile['programmer_name'] ?? '') === 'Launch' ? 'selected' : '' ?>>Launch</option>
                    <option value="Autel" <?= ($profile['programmer_name'] ?? '') === 'Autel' ? 'selected' : '' ?>>Autel</option>
                    <option value="Other" <?= ($profile['programmer_name'] ?? '') === 'Other' ? 'selected' : '' ?>>أخرى</option>
                </select>
            </div>

            <div class="form-group">
                <label for="programmer_serial">رقم الجهاز أو الرقم التسلسلي:</label>
                <input type="text" id="programmer_serial" name="programmer_serial" value="<?= htmlspecialchars($profile['programmer_serial'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="programmer_type">نوع الجهاز:</label>
                <select id="programmer_type" name="programmer_type">
                    <option value="">-- اختر --</option>
                    <option value="Master" <?= ($profile['programmer_type'] ?? '') === 'Master' ? 'selected' : '' ?>>Master</option>
                    <option value="Slave" <?= ($profile['programmer_type'] ?? '') === 'Slave' ? 'selected' : '' ?>>Slave</option>
                </select>
            </div>

            <div class="form-group">
                <label for="dongle_number">رقم الدونجل (إن وُجد):</label>
                <input type="text" id="dongle_number" name="dongle_number" value="<?= htmlspecialchars($profile['dongle_number'] ?? '') ?>">
            </div>

            <button type="submit" class="btn btn-primary">تحديث بيانات المبرمجة</button>
        </form>
    </div>
</div>

<script>
// كود جافاسكريبت للتبديل بين علامات التبويب
document.addEventListener('DOMContentLoaded', function() {
    // التبديل بين علامات التبويب
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // إزالة الفئة النشطة من جميع الأزرار والمحتويات
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // إضافة الفئة النشطة للزر المحدد
            this.classList.add('active');
            
            // إظهار المحتوى المرتبط
            const tabId = this.getAttribute('data-tab');
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // التعامل مع أزرار رفع المستندات
    const uploadButtons = document.querySelectorAll('.upload-doc-btn');
    const uploadFormContainer = document.getElementById('upload-form-container');
    const documentTypeInput = document.getElementById('document_type');
    const cancelUploadBtn = document.getElementById('cancel-upload');
    
    uploadButtons.forEach(button => {
        button.addEventListener('click', function() {
            const docType = this.getAttribute('data-doctype');
            documentTypeInput.value = docType;
            uploadFormContainer.style.display = 'block';
            
            // التمرير إلى نموذج الرفع
            uploadFormContainer.scrollIntoView({ behavior: 'smooth' });
        });
    });
    
    if (cancelUploadBtn) {
        cancelUploadBtn.addEventListener('click', function() {
            uploadFormContainer.style.display = 'none';
        });
    }
});
</script>
<?php
$page_content = ob_get_clean();

// إدراج القالب
include __DIR__ . '/includes/layout.php';
?>