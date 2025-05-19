<?php
ob_start();
session_start();
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? '';
$user_type = $_SESSION['user_role'] ?? 'user';
$email = $_SESSION['email'] ?? '';

// متغيرات البحث
$query = $_GET['query'] ?? '';
$selected_brand = $_GET['brand'] ?? '';
$selected_model = $_GET['model'] ?? '';
$selected_ecu = $_GET['ecu'] ?? '';

// رسائل النجاح والخطأ
$success_message = '';
$error_message = '';

// نتائج البحث
$ecu_data = null;
$has_result = false;
$search_message = '';
$search_results = [];

// معالجة تحميل ملف الدامب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_dump'])) {
    $ecu_id = (int)$_POST['ecu_id'];
    
    // التحقق من وجود الملف
    if (!isset($_FILES['dump_file']) || $_FILES['dump_file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = 'حدث خطأ أثناء تحميل الملف. يرجى المحاولة مرة أخرى.';
    } else {
        // التحقق من نوع الملف
        $file_info = pathinfo($_FILES['dump_file']['name']);
        $file_ext = strtolower($file_info['extension']);
        $allowed_extensions = ['bin', 'hex', 'dump', 'rom', 'dat', 'img', 'eep', 'srec', 'zip'];
        
        // التحقق من حجم الملف (5MB كحد أقصى)
        $max_size = 5 * 1024 * 1024; // 5 ميجابايت
        
        if (!in_array($file_ext, $allowed_extensions)) {
            $error_message = 'نوع الملف غير مدعوم. يُسمح فقط بملفات: ' . implode(', ', $allowed_extensions);
        } elseif ($_FILES['dump_file']['size'] > $max_size) {
            $error_message = 'حجم الملف كبير جدًا. الحد الأقصى هو 5 ميجابايت.';
        } else {
            try {
                // جلب بيانات ECU
                $ecu_stmt = $pdo->prepare("SELECT brand, model, ecu_number FROM airbag_ecus WHERE id = ?");
                $ecu_stmt->execute([$ecu_id]);
                $ecu_info = $ecu_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$ecu_info) {
                    throw new Exception("لم يتم العثور على معلومات ECU");
                }
                
                // إنشاء دليل للتخزين إذا لم يكن موجودًا
                $upload_dir = 'uploads/dump_files';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // إنشاء اسم ملف فريد
                $new_filename = $username . '_' . date('Ymd_His') . '_' . $ecu_id . '.' . $file_ext;
                $upload_path = $upload_dir . '/' . $new_filename;
                
                // نقل الملف المؤقت إلى المجلد المطلوب
                if (move_uploaded_file($_FILES['dump_file']['tmp_name'], $upload_path)) {
                    $dump_type = $_POST['dump_type'] ?? 'eeprom';
                    $notes = $_POST['notes'] ?? '';
                    
                    // حفظ معلومات الملف في قاعدة البيانات
                    $dump_stmt = $pdo->prepare("
                        INSERT INTO ecu_dumps (
                            ecu_id, username, filename, original_filename, file_path, 
                            dump_type, notes, file_size, file_type, upload_date
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $dump_stmt->execute([
                        $ecu_id,
                        $username,
                        $new_filename,
                        $_FILES['dump_file']['name'],
                        $upload_path,
                        $dump_type,
                        $notes,
                        $_FILES['dump_file']['size'],
                        $file_ext
                    ]);
                    
                    // إنشاء تذكرة جديدة
                    $ticket_stmt = $pdo->prepare("
                        INSERT INTO tickets (
                            username, email, phone, car_type, chassis, service_type, 
                            description, created_at, status, is_seen
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'pending', 0)
                    ");
                    
                    $phone = $_POST['phone'] ?? '';
                    $chassis = $_POST['chassis'] ?? '';
                    
                    $service_type = 'إعادة ضبط الإيرباق';
                    $car_type = $ecu_info['brand'] . ' ' . $ecu_info['model'];
                    $description = 'طلب إعادة ضبط كمبيوتر إيرباق. رقم الكمبيوتر: ' . $ecu_info['ecu_number'] . 
                                   '. نوع الدامب: ' . $dump_type . '. ملاحظات: ' . $notes;
                    
                    $ticket_stmt->execute([
                        $username,
                        $email,
                        $phone,
                        $car_type,
                        $chassis,
                        $service_type,
                        $description
                    ]);
                    
                    $ticket_id = $pdo->lastInsertId();
                    
                    // ربط التذكرة بالملف
                    $link_stmt = $pdo->prepare("
                        UPDATE ecu_dumps SET ticket_id = ? WHERE filename = ?
                    ");
                    $link_stmt->execute([$ticket_id, $new_filename]);
                    
                    $success_message = 'تم تحميل الملف وإنشاء تذكرة بنجاح. يمكنك متابعة حالة الطلب في صفحة "تذاكري".';
                } else {
                    throw new Exception("فشل في نقل الملف المرفوع");
                }
            } catch (Exception $e) {
                $error_message = 'حدث خطأ أثناء معالجة الطلب: ' . $e->getMessage();
                error_log('Error in airbag-reset.php: ' . $e->getMessage());
            }
        }
    }
}

// معالجة البحث المباشر
if (!empty($_GET['ecu_id'])) {
    $ecu_id = (int)$_GET['ecu_id'];
    
    $stmt = $pdo->prepare("
        SELECT ae.*,
               (SELECT COUNT(*) FROM ecu_images ei WHERE ei.ecu_id = ae.id) as image_count
        FROM airbag_ecus ae
        WHERE ae.id = ?
    ");
    $stmt->execute([$ecu_id]);
    $ecu_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ecu_data) {
        $has_result = true;
        
        // جلب الصور إذا كانت متوفرة
        if ($ecu_data['image_count'] > 0) {
            $images_stmt = $pdo->prepare("
                SELECT * FROM ecu_images WHERE ecu_id = ? ORDER BY display_order ASC
            ");
            $images_stmt->execute([$ecu_id]);
            $ecu_data['images'] = $images_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

// معالجة البحث عن طريق النموذج
if (!empty($_GET['search']) && (
    !empty($selected_brand) || 
    !empty($selected_model) || 
    !empty($selected_ecu) || 
    !empty($query)
)) {
    $search_conditions = [];
    $search_params = [];
    
    if (!empty($selected_brand)) {
        $search_conditions[] = "brand = ?";
        $search_params[] = $selected_brand;
    }
    
    if (!empty($selected_model)) {
        $search_conditions[] = "model = ?";
        $search_params[] = $selected_model;
    }
    
    if (!empty($selected_ecu)) {
        $search_conditions[] = "ecu_number = ?";
        $search_params[] = $selected_ecu;
    }
    
    if (!empty($query)) {
        $search_conditions[] = "(
            brand LIKE ? OR 
            model LIKE ? OR 
            ecu_number LIKE ? OR 
            eeprom_type LIKE ?
        )";
        $search_params[] = "%$query%";
        $search_params[] = "%$query%";
        $search_params[] = "%$query%";
        $search_params[] = "%$query%";
    }
    
    if (!empty($search_conditions)) {
        $where_clause = "WHERE " . implode(" AND ", $search_conditions);
        
        $sql = "
            SELECT ae.*,
                   (SELECT COUNT(*) FROM ecu_images ei WHERE ei.ecu_id = ae.id) as image_count
            FROM airbag_ecus ae
            $where_clause
            ORDER BY brand, model
            LIMIT 20
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($search_params);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($search_results) === 1) {
            // إذا وجدنا نتيجة واحدة فقط، عرضها مباشرة
            $ecu_data = $search_results[0];
            $has_result = true;
            
            // جلب الصور إذا كانت متوفرة
            if ($ecu_data['image_count'] > 0) {
                $images_stmt = $pdo->prepare("
                    SELECT * FROM ecu_images WHERE ecu_id = ? ORDER BY display_order ASC
                ");
                $images_stmt->execute([$ecu_data['id']]);
                $ecu_data['images'] = $images_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } elseif (count($search_results) > 1) {
            // إذا وجدنا أكثر من نتيجة، عرض قائمة للاختيار
            $search_message = 'تم العثور على ' . count($search_results) . ' نتيجة، اختر واحدة:';
        } else {
            // لا توجد نتائج
            $search_message = 'لم يتم العثور على نتائج مطابقة، حاول مرة أخرى.';
        }
    }
}

// التحقق من وجود طلبات سابقة لهذا ECU
$user_dump_requests = [];
if ($has_result && !empty($ecu_data)) {
    try {
        // التحقق من وجود جدول ecu_dumps
        $table_check = $pdo->query("SHOW TABLES LIKE 'ecu_dumps'");
        if ($table_check->rowCount() > 0) {
            $dump_stmt = $pdo->prepare("
                SELECT ed.*, t.status as ticket_status, t.is_seen
                FROM ecu_dumps ed
                LEFT JOIN tickets t ON ed.ticket_id = t.id
                WHERE ed.ecu_id = ? AND ed.username = ?
                ORDER BY ed.upload_date DESC
            ");
            $dump_stmt->execute([$ecu_data['id'], $username]);
            $user_dump_requests = $dump_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log('Error checking dump requests: ' . $e->getMessage());
    }
}

// جلب العلامات التجارية للفلتر
try {
    $brands = $pdo->query("SELECT DISTINCT brand FROM airbag_ecus ORDER BY brand")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $brands = [];
    error_log('Error fetching brands: ' . $e->getMessage());
}

// إضافة تسجيل للبحث (إذا كنت تريد تتبع عمليات البحث)
if ($has_result && !empty($ecu_data)) {
    try {
        // تحقق من وجود جدول السجلات أولاً
        $check_table = $pdo->query("SHOW TABLES LIKE 'search_logs'");
        if ($check_table->rowCount() > 0) {
            $log_stmt = $pdo->prepare("
                INSERT INTO search_logs (user_id, ecu_id, brand, model, ecu_number, search_term, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $user_id = $_SESSION['user_id'] ?? 0;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $search_term = $query ?: "$selected_brand $selected_model $selected_ecu";
            
            $log_stmt->execute([
                $user_id,
                $ecu_data['id'],
                $ecu_data['brand'],
                $ecu_data['model'],
                $ecu_data['ecu_number'],
                $search_term,
                $ip_address
            ]);
        }
    } catch (Exception $e) {
        error_log('Error logging search: ' . $e->getMessage());
    }
}

// CSS مخصص للصفحة
$page_css = <<<CSS
.main-container{background:rgba(0,0,0,.7);padding:30px;width:95%;max-width:1200px;border-radius:16px;text-align:center;margin:30px auto;box-shadow:0 0 40px rgba(0,200,255,.15);backdrop-filter:blur(12px);border:1px solid rgba(66,135,245,.25)}.message-container{margin-bottom:20px}.success-message{background-color:rgba(39,174,96,.2);color:#2ecc71;border:1px solid rgba(39,174,96,.4);border-radius:8px;padding:15px;text-align:center;margin-bottom:20px}.error-message{background-color:rgba(231,76,60,.2);color:#e74c3c;border:1px solid rgba(231,76,60,.4);border-radius:8px;padding:15px;text-align:center;margin-bottom:20px}.search-container{background:rgba(255,255,255,.05);padding:25px;border-radius:12px;margin-bottom:30px;border:1px solid rgba(66,135,245,.15)}.search-title{color:#00d4ff;margin-bottom:20px;font-size:1.5em}.search-form{display:flex;flex-direction:column;gap:15px;max-width:800px;margin:0 auto}.form-group{display:flex;flex-direction:column;text-align:right}.form-group label{margin-bottom:8px;color:#a8d8ff;font-weight:700}.form-control{padding:12px;background:rgba(255,255,255,.1);border:1px solid rgba(66,135,245,.3);border-radius:8px;color:#fff;text-align:right;direction:rtl}.form-control:focus{outline:0;border-color:#00d4ff;background:rgba(255,255,255,.15)}.search-actions{display:flex;justify-content:center;gap:15px;margin-top:20px}.btn{padding:12px 25px;border:none;border-radius:8px;cursor:pointer;font-weight:700;transition:all .3s ease;text-decoration:none;display:inline-block}.btn-primary{background:linear-gradient(145deg,#1e90ff,#0070cc);color:#fff}.btn-primary:hover{background:linear-gradient(145deg,#2eaaff,#0088ff);transform:translateY(-2px)}.btn-secondary{background:linear-gradient(145deg,#6c757d,#5a6268);color:#fff}.btn-secondary:hover{background:linear-gradient(145deg,#7a8288,#6c757d);transform:translateY(-2px)}.btn-success{background:linear-gradient(145deg,#28a745,#218838);color:#fff}.btn-success:hover{background:linear-gradient(145deg,#34ce57,#28a745);transform:translateY(-2px)}.result-container{background:rgba(255,255,255,.05);border-radius:12px;padding:25px;margin-top:30px;border:1px solid rgba(66,135,245,.15);text-align:right;direction:rtl}.result-title{color:#00d4ff;margin-bottom:20px;font-size:1.5em;text-align:center}.data-table{width:100%;border-collapse:collapse;margin:15px 0}.data-table td,.data-table th{padding:12px;text-align:right;border-bottom:1px solid rgba(255,255,255,.1)}.data-table th{background:rgba(0,0,0,.3);color:#00d4ff;font-weight:700}.data-table td{color:#a8d8ff}.instructions{background:rgba(0,0,0,.3);padding:20px;border-radius:10px;margin-top:20px;text-align:right;direction:rtl;border:1px solid rgba(66,135,245,.15)}.instructions ol{text-align:right;padding-right:20px}.instructions li{margin-bottom:10px;color:#a8d8ff}.upload-form{background:rgba(0,123,255,.1);border:1px solid rgba(0,123,255,.3);border-radius:8px;padding:20px;margin-top:25px;text-align:right}.upload-title{color:#00d4ff;margin-bottom:15px;text-align:center;font-size:1.3em}.file-upload-container{display:flex;flex-direction:column;gap:15px;margin-bottom:20px}.file-input-wrapper{position:relative;margin-bottom:15px;text-align:center}.file-input{opacity:0;position:absolute;top:0;left:0;width:100%;height:100%;cursor:pointer;z-index:10}.file-input-label{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;background:rgba(255,255,255,.05);border:2px dashed rgba(66,135,245,.3);border-radius:10px;cursor:pointer;transition:all .3s ease}.file-input-label:hover{background:rgba(255,255,255,.1);border-color:#00d4ff}.file-input-icon{font-size:3em;color:#00d4ff;margin-bottom:10px}.file-selected{display:none;margin-top:10px;color:#00d4ff}.upload-form-buttons{display:flex;justify-content:center;gap:15px;margin-top:20px}.file-type-selector{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin:15px 0}.file-type-option{background:rgba(255,255,255,.05);border:1px solid rgba(66,135,245,.3);padding:8px 15px;border-radius:20px;cursor:pointer;transition:all .3s ease}.file-type-option.selected{background:rgba(0,123,255,.2);border-color:#00d4ff;color:#00d4ff}.file-type-option:hover{background:rgba(255,255,255,.1)}.alert{padding:15px;border-radius:10px;margin:15px 0;text-align:center;direction:rtl}.alert-info{background:rgba(23,162,184,.2);border:1px solid #17a2b8;color:#aef0ff}.alert-warning{background:rgba(255,193,7,.2);border:1px solid #ffc107;color:#ffe699}.alert-success{background:rgba(40,167,69,.2);border:1px solid #28a745;color:#beffdc}.image-container{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:15px;margin:20px 0}.ecu-image{border:1px solid rgba(66,135,245,.3);border-radius:8px;overflow:hidden;background:rgba(0,0,0,.5);position:relative}.ecu-image img{width:100%;height:auto;transition:transform .3s ease;cursor:pointer}.ecu-image img:hover{transform:scale(1.05)}.image-caption{background:rgba(0,0,0,.7);color:#a8d8ff;padding:8px;text-align:center}.modal{display:none;position:fixed;z-index:2000;left:0;top:0;width:100%;height:100%;background-color:rgba(0,0,0,.8)}.modal-content{background:rgba(0,0,0,.9);margin:5% auto;padding:30px;border-radius:15px;max-width:80%;max-height:80vh;overflow:auto;border:1px solid rgba(66,135,245,.3);position:relative}.close{color:#aaa;position:absolute;top:10px;right:20px;font-size:28px;font-weight:700}.close:hover{color:#fff;cursor:pointer}.search-results{margin:20px 0}.search-results table{width:100%;border-collapse:collapse}.search-results td,.search-results th{padding:10px;text-align:right;border-bottom:1px solid rgba(255,255,255,.1)}.search-results tr:hover{background:rgba(255,255,255,.05);cursor:pointer}.search-results .result-link{color:#40a9ff;text-decoration:none}.search-results .result-link:hover{text-decoration:underline}.info-box{background:rgba(0,123,255,.1);border:1px solid rgba(0,123,255,.3);border-radius:8px;padding:15px;margin-top:20px;margin-bottom:20px}.info-box h3{color:#00d4ff;margin-top:0;margin-bottom:10px}.info-box p{color:#a8d8ff;margin:0}.autocomplete-container{position:relative}.autocomplete-results{position:absolute;top:100%;left:0;right:0;z-index:1000;max-height:200px;overflow-y:auto;background:rgba(0,0,0,.9);border:1px solid rgba(66,135,245,.3);border-radius:0 0 8px 8px;display:none}.autocomplete-item{padding:10px 15px;cursor:pointer;text-align:right;border-bottom:1px solid rgba(255,255,255,.1)}.autocomplete-item.selected,.autocomplete-item:hover{background:rgba(66,135,245,.3)}.previous-uploads{margin-top:25px;padding:15px;background:rgba(0,0,0,.3);border-radius:10px;text-align:right}.previous-uploads-title{color:#00d4ff;margin-bottom:10px;text-align:center}.previous-uploads-table{width:100%;border-collapse:collapse;margin-top:10px}.previous-uploads-table td,.previous-uploads-table th{padding:10px;text-align:right;border-bottom:1px solid rgba(255,255,255,.1)}.previous-uploads-table th{background:rgba(0,0,0,.2);color:#00d4ff}.ticket-status{padding:5px 10px;border-radius:15px;font-size:.85em;display:inline-block}.status-pending{background-color:rgba(255,193,7,.2);color:#ffc107;border:1px solid rgba(255,193,7,.3)}.status-reviewed{background-color:rgba(40,167,69,.2);color:#28a745;border:1px solid rgba(40,167,69,.3)}.status-cancelled{background-color:rgba(220,53,69,.2);color:#dc3545;border:1px solid rgba(220,53,69,.3)}@media (min-width:768px){.search-form{display:grid;grid-template-columns:1fr 1fr;gap:20px}.form-group.full-width{grid-column:span 2}}@media (max-width:767px){.main-container{padding:20px;width:90%}.btn{padding:10px 15px;font-size:14px}.search-actions{flex-direction:column;align-items:center}.image-container{grid-template-columns:1fr}.upload-form-buttons{flex-direction:column}}.back-link{display:inline-block;margin-top:20px;padding:12px 25px;background:linear-gradient(145deg,#6c757d,#5a6268);color:#fff;text-decoration:none;border-radius:10px;transition:all .3s ease}.back-link:hover{background:linear-gradient(145deg,#7a8288,#6c757d);transform:translateY(-2px)}
CSS;

// تعريف محتوى الصفحة
ob_start();
?>

<div class="main-container">
  <h1><?= $display_title ?></h1>
  
  <?php if ($success_message): ?>
    <div class="success-message">
      <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
    </div>
  <?php endif; ?>
  
  <?php if ($error_message): ?>
    <div class="error-message">
      <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
    </div>
  <?php endif; ?>
  
  <!-- قسم البحث -->
  <div class="search-container">
    <h2 class="search-title">🔍 ابحث عن بيانات إعادة ضبط الإيرباق</h2>
    
    <form method="GET" action="" class="search-form">
      <input type="hidden" name="search" value="1">
      
      <div class="form-group">
        <label for="brand">العلامة التجارية</label>
        <div class="autocomplete-container">
          <input type="text" id="brand" name="brand" class="form-control" value="<?= htmlspecialchars($selected_brand) ?>" placeholder="أدخل العلامة التجارية...">
          <div id="brand-results" class="autocomplete-results"></div>
        </div>
      </div>
      
      <div class="form-group">
        <label for="model">الموديل</label>
        <div class="autocomplete-container">
          <input type="text" id="model" name="model" class="form-control" value="<?= htmlspecialchars($selected_model) ?>" placeholder="أدخل الموديل...">
          <div id="model-results" class="autocomplete-results"></div>
        </div>
      </div>
      
      <div class="form-group">
        <label for="ecu">رقم كمبيوتر الإيرباق</label>
        <div class="autocomplete-container">
          <input type="text" id="ecu" name="ecu" class="form-control" value="<?= htmlspecialchars($selected_ecu) ?>" placeholder="أدخل رقم كمبيوتر الإيرباق...">
          <div id="ecu-results" class="autocomplete-results"></div>
        </div>
      </div>
      
      <div class="form-group full-width">
        <label for="query">بحث عام (العلامة التجارية، الموديل، الرقم، نوع EEPROM)</label>
        <input type="text" id="query" name="query" class="form-control" value="<?= htmlspecialchars($query) ?>" placeholder="أدخل كلمات البحث...">
      </div>
      
      <div class="search-actions full-width">
        <button type="submit" class="btn btn-primary">🔍 بحث</button>
        <a href="airbag-reset.php" class="btn btn-secondary">↺ إعادة تعيين</a>
      </div>
    </form>
  </div>
  
  <?php if (!empty($search_message)): ?>
    <div class="alert alert-info">
      <?= htmlspecialchars($search_message) ?>
    </div>
    
    <?php if (isset($search_results) && count($search_results) > 0): ?>
      <div class="search-results">
        <table>
          <thead>
            <tr>
              <th>العلامة التجارية</th>
              <th>الموديل</th>
              <th>رقم الكمبيوتر</th>
              <th>نوع EEPROM</th>
              <th>الإجراء</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($search_results as $result): ?>
              <tr>
                <td><?= htmlspecialchars($result['brand']) ?></td>
                <td><?= htmlspecialchars($result['model']) ?></td>
                <td><?= htmlspecialchars($result['ecu_number']) ?></td>
                <td><?= htmlspecialchars($result['eeprom_type'] ?? 'غير متوفر') ?></td>
                <td>
                  <a href="airbag-reset.php?ecu_id=<?= $result['id'] ?>" class="result-link">
                    عرض التفاصيل
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
  
  <?php if ($has_result && !empty($ecu_data)): ?>
    <!-- عرض نتائج البحث -->
    <div class="result-container">
      <h2 class="result-title">🚗 بيانات كمبيوتر الإيرباق</h2>
      
      <table class="data-table">
        <tr>
          <th>العلامة التجارية:</th>
          <td><?= htmlspecialchars($ecu_data['brand']) ?></td>
        </tr>
        <tr>
          <th>الموديل:</th>
          <td><?= htmlspecialchars($ecu_data['model']) ?></td>
        </tr>
        <tr>
          <th>رقم كمبيوتر الإيرباق:</th>
          <td><?= htmlspecialchars($ecu_data['ecu_number']) ?></td>
        </tr>
        <?php if (!empty($ecu_data['eeprom_type'])): ?>
        <tr>
          <th>نوع EEPROM:</th>
          <td><?= htmlspecialchars($ecu_data['eeprom_type']) ?></td>
        </tr>
        <?php endif; ?>
        <?php if (isset($ecu_data['crash_location']) && !empty($ecu_data['crash_location'])): ?>
        <tr>
          <th>موقع بيانات الحادث:</th>
          <td><?= htmlspecialchars($ecu_data['crash_location']) ?></td>
        </tr>
        <?php endif; ?>
        <?php if (isset($ecu_data['reset_procedure']) && !empty($ecu_data['reset_procedure'])): ?>
        <tr>
          <th>إجراءات إعادة الضبط:</th>
          <td><?= nl2br(htmlspecialchars($ecu_data['reset_procedure'])) ?></td>
        </tr>
        <?php endif; ?>
      </table>
      
      <?php if (isset($ecu_data['images']) && count($ecu_data['images']) > 0): ?>
        <h3 style="color: #00d4ff; margin-top: 20px;">📷 صور مخطط الإيرباق</h3>
        <div class="image-container">
          <?php foreach ($ecu_data['images'] as $index => $image): ?>
            <div class="ecu-image">
              <img src="uploads/ecu_images/<?= htmlspecialchars($image['filename']) ?>" 
                   alt="<?= htmlspecialchars($ecu_data['brand'] . ' ' . $ecu_data['model']) ?>"
                   onclick="openImageModal('uploads/ecu_images/<?= htmlspecialchars($image['filename']) ?>')">
              <?php if (isset($image['description']) && !empty($image['description'])): ?>
                <div class="image-caption"><?= htmlspecialchars($image['description']) ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="alert alert-warning">
          لا توجد صور متاحة لهذا الكمبيوتر
        </div>
      <?php endif; ?>
      
      <div class="instructions">
        <h3 style="color: #00d4ff;">📋 تعليمات إعادة ضبط الإيرباق</h3>
        <ol>
          <li>قم بتوصيل EEPROM المناسب بجهاز البرمجة.</li>
          <li>استخدم برنامج FlexAutoPro لقراءة محتوى الـ EEPROM.</li>
          <li>قم بتحديد موقع بيانات الحادث وفقاً للمعلومات المعروضة أعلاه.</li>
          <li>امسح بيانات الحادث (Crash Data) واستبدلها بالقيم الافتراضية.</li>
          <li>اكتب البيانات المعدلة مرة أخرى إلى EEPROM.</li>
          <li>أعد تركيب EEPROM في وحدة الإيرباق وتأكد من التوصيل الصحيح.</li>
          <li>قم بتوصيل السيارة بجهاز فحص وتأكد من عدم وجود أخطاء.</li>
        </ol>
      </div>
      
      <div class="info-box">
        <h3>🛠️ ملاحظة فنية</h3>
        <p>
          تأكد دائمًا من مقارنة رقم كمبيوتر الإيرباق الخاص بك مع الرقم المعروض. 
          في حالة عدم التطابق الدقيق، قد تكون هناك اختلافات في موقع بيانات الحادث.
          استخدم هذه المعلومات على مسؤوليتك الخاصة وتأكد من عمل نسخة احتياطية قبل أي تعديل.
        </p>
      </div>

      <!-- طلبات الفحص السابقة -->
      <?php if (!empty($user_dump_requests)): ?>
        <div class="previous-uploads">
          <h3 class="previous-uploads-title">📄 طلباتك السابقة لهذا الكمبيوتر</h3>
          <table class="previous-uploads-table">
            <thead>
              <tr>
                <th>تاريخ الطلب</th>
                <th>نوع الملف</th>
                <th>اسم الملف</th>
                <th>حالة الطلب</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($user_dump_requests as $request): ?>
                <tr>
                  <td><?= date('Y/m/d H:i', strtotime($request['upload_date'])) ?></td>
                  <td>
                    <?php
                    switch($request['dump_type']) {
                      case 'eeprom': echo 'ذاكرة EEPROM'; break;
                      case 'flash': echo 'ذاكرة الفلاش'; break;
                      case 'cpu': echo 'وحدة المعالجة'; break;
                      default: echo htmlspecialchars($request['dump_type']);
                    }
                    ?>
                  </td>
                  <td><?= htmlspecialchars($request['original_filename']) ?></td>
                  <td>
                    <?php if (isset($request['ticket_status']) && $request['ticket_status'] === 'cancelled'): ?>
                      <span class="ticket-status status-cancelled">ملغي</span>
                    <?php elseif (isset($request['is_seen']) && $request['is_seen']): ?>
                      <span class="ticket-status status-reviewed">تمت المراجعة</span>
                    <?php else: ?>
                      <span class="ticket-status status-pending">قيد المراجعة</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <div style="text-align: center; margin-top: 15px;">
            <a href="includes/my_tickets.php" class="btn btn-primary">
              <i class="fas fa-ticket-alt"></i> عرض جميع تذاكري
            </a>
          </div>
        </div>
      <?php endif; ?>

      <!-- نموذج تحميل الملف -->
      <div class="upload-form">
        <h3 class="upload-title">📤 تحميل ملف الدامب لإعادة ضبط الإيرباق</h3>
        
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="upload_dump" value="1">
          <input type="hidden" name="ecu_id" value="<?= $ecu_data['id'] ?>">
          
          <div class="file-input-wrapper">
            <input type="file" id="dump_file" name="dump_file" class="file-input" accept=".bin,.hex,.dump,.rom,.dat,.img,.eep,.srec,.zip" required>
            <label for="dump_file" class="file-input-label">
              <div class="file-input-icon">
                <i class="fas fa-file-upload"></i>
              </div>
              <div>اضغط هنا لتحميل ملف الدامب</div>
              <div style="font-size: 0.8em; color: #a8d8ff; margin-top: 5px;">
                الملفات المدعومة: .bin, .hex, .dump, .rom, .dat, .img, .eep, .srec, .zip
              </div>
            </label>
            <div id="file-selected" class="file-selected">تم اختيار: <span id="file-name"></span></div>
          </div>
          
          <div class="form-group">
            <label>نوع الدامب:</label>
            <div class="file-type-selector">
              <div class="file-type-option selected" data-value="eeprom">ذاكرة EEPROM</div>
              <div class="file-type-option" data-value="flash">ذاكرة الفلاش</div>
              <div class="file-type-option" data-value="cpu">وحدة المعالجة CPU</div>
            </div>
            <input type="hidden" name="dump_type" id="dump_type" value="eeprom">
          </div>
          
          <div class="form-group">
            <label for="chassis">رقم الشاصي (الهيكل):</label>
            <input type="text" id="chassis" name="chassis" class="form-control" placeholder="أدخل رقم شاصي السيارة...">
          </div>
          
          <div class="form-group">
            <label for="phone">رقم الهاتف:</label>
            <input type="text" id="phone" name="phone" class="form-control" placeholder="رقم هاتفك للتواصل..." required>
          </div>
          
          <div class="form-group">
            <label for="notes">ملاحظات إضافية:</label>
            <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="أي ملاحظات أو معلومات إضافية ترغب في إضافتها..."></textarea>
          </div>
          
          <div class="upload-form-buttons">
            <button type="submit" class="btn btn-success">
              <i class="fas fa-paper-plane"></i> إرسال الطلب
            </button>
            <button type="reset" class="btn btn-secondary">
              <i class="fas fa-undo"></i> إعادة تعيين
            </button>
          </div>
        </form>
      </div>
    </div>
  <?php elseif (!isset($search_results) || count($search_results) === 0): ?>
    <!-- معلومات افتراضية إذا لم تكن هناك نتائج بحث -->
    <div class="info-box">
      <h3>👋 مرحبًا بك في نظام مسح وإعادة ضبط الإيرباق</h3>
      <p>
        استخدم نموذج البحث أعلاه للعثور على معلومات حول كمبيوتر الإيرباق الخاص بسيارتك.
        يمكنك البحث عن طريق العلامة التجارية أو الموديل أو رقم الكمبيوتر.
      </p>
      <p style="margin-top: 10px;">
        بمجرد العثور على الكمبيوتر المطلوب، ستتمكن من:
      </p>
      <ul style="text-align: right; padding-right: 20px; margin-top: 10px; color: #a8d8ff;">
        <li>رؤية صور المخطط وتعليمات إعادة الضبط</li>
        <li>تحميل ملف الدامب الخاص بكمبيوتر السيارة</li>
        <li>إرسال طلب إعادة ضبط للفريق الفني</li>
        <li>متابعة حالة طلبك في قسم "تذاكري"</li>
      </ul>
    </div>
  <?php endif; ?>
  
  <!-- زر العودة -->
  <a href="home.php" class="back-link">
    ↩️ العودة إلى الصفحة الرئيسية
  </a>
</div>

<!-- مودال عرض الصور -->
<div id="imageModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeImageModal()">&times;</span>
    <img id="modalImage" src="" alt="صورة الإيرباق" style="width: 100%; height: auto;">
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded",function(){function e(e,t,a,n){const o=document.getElementById(e),i=document.getElementById(t);let c=-1,s=[];o.addEventListener("input",function(){const e=this.value.trim();if(e.length<1)return void(i.style.display="none");let t="";if(n){const e=n();for(const a in e)e[a]&&(t+="&"+a+"="+encodeURIComponent(e[a]))}fetch("search_airbag_ecus.php?action="+a+"&q="+encodeURIComponent(e)+t).then(e=>e.json()).then(e=>{if(e.error)return void console.error(e.error);if(s=e,0===s.length)return void(i.style.display="none");i.innerHTML="",s.forEach((e,t)=>{const a=document.createElement("div");a.className="autocomplete-item",a.textContent=e,a.addEventListener("click",function(){o.value=e,i.style.display="none"}),i.appendChild(a)}),i.style.display="block",c=-1}).catch(e=>{console.error("Error fetching autocomplete results:",e)})}),o.addEventListener("keydown",function(e){const t=i.querySelectorAll(".autocomplete-item");if(0!==t.length)if("ArrowDown"===e.key)e.preventDefault(),c=(c+1)%t.length,a(t);else if("ArrowUp"===e.key)e.preventDefault(),c=(c-1+t.length)%t.length,a(t);else if("Enter"===e.key&&-1!==c)e.preventDefault(),o.value=s[c],i.style.display="none";else if("Escape"===e.key)i.style.display="none"});function a(e){e.forEach((e,t)=>{t===c?(e.classList.add("selected"),e.scrollIntoView({block:"nearest"})):e.classList.remove("selected")})}document.addEventListener("click",function(e){e.target!==o&&e.target!==i&&(i.style.display="none")})}e("brand","brand-results","brands"),e("model","model-results","models",function(){return{brand:document.getElementById("brand").value}}),e("ecu","ecu-results","ecus",function(){return{brand:document.getElementById("brand").value,model:document.getElementById("model").value}});const t=document.querySelectorAll(".file-type-option"),a=document.getElementById("dump_type");t.forEach(e=>{e.addEventListener("click",function(){t.forEach(e=>e.classList.remove("selected")),this.classList.add("selected"),a.value=this.getAttribute("data-value")})});const n=document.getElementById("dump_file"),o=document.getElementById("file-selected"),i=document.getElementById("file-name");n.addEventListener("change",function(){if(this.files.length>0){i.textContent=this.files[0].name,o.style.display="block";const e=this.files[0].size;if(5242880<e)return alert("حجم الملف كبير جدًا. الحد الأقصى هو 5 ميجابايت."),this.value="",void(o.style.display="none");const t=this.files[0].name.split(".").pop().toLowerCase();["bin","hex","dump","rom","dat","img","eep","srec","zip"].includes(t)||(alert("نوع الملف غير مدعوم. يُسمح فقط بملفات: bin, hex, dump, rom, dat, img, eep, srec, zip"),this.value="",o.style.display="none")}else o.style.display="none"});const c=document.querySelector(".success-message"),s=document.querySelector(".error-message");(c||s)&&setTimeout(function(){c&&(c.style.display="none"),s&&(s.style.display="none")},5e3)});function openImageModal(e){const t=document.getElementById("imageModal"),a=document.getElementById("modalImage");t.style.display="block",a.src=e}function closeImageModal(){document.getElementById("imageModal").style.display="none"}window.onclick=function(e){const t=document.getElementById("imageModal");e.target===t&&closeImageModal()};
</script>

<?php
$page_content = ob_get_clean();

// إدراج القالب
include __DIR__ . '/includes/layout.php';
?>