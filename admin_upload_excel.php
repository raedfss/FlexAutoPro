<?php
use Shuchkin\SimpleXLSX;
session_start();
require_once __DIR__ . '/includes/db.php';

// التحقق من تسجيل الدخول والصلاحيات
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
$user_type = $_SESSION['user_role'];
$email = $_SESSION['email'];

// إعداد عنوان الصفحة
$page_title = 'رفع ملف Excel - إدارة بيانات الإيرباق';
$display_title = 'رفع ومعالجة ملف Excel للإيرباق';

// إنشاء جدول airbag_ecus إذا لم يكن موجوداً
try {
    $create_table_sql = "
    CREATE TABLE IF NOT EXISTS airbag_ecus (
        id INT AUTO_INCREMENT PRIMARY KEY,
        brand VARCHAR(100) NOT NULL,
        model VARCHAR(100) NOT NULL,
        ecu_number VARCHAR(100) NOT NULL,
        eeprom_type VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_combo (brand, model, ecu_number),
        INDEX idx_brand (brand),
        INDEX idx_model (model),
        INDEX idx_ecu_number (ecu_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $pdo->exec($create_table_sql);
} catch (PDOException $e) {
    error_log("Error creating airbag_ecus table: " . $e->getMessage());
}

$message = '';
$error = '';
$stats = null;

// معالجة رفع الملف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    try {
        // التحقق من الملف
        $file = $_FILES['excel_file'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['xlsx', 'xls', 'csv'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($file_ext, $allowed_ext)) {
            throw new Exception('نوع الملف غير مدعوم. الرجاء رفع ملف Excel (.xlsx, .xls) أو CSV (.csv)');
        }

        if ($file['size'] > $max_size) {
            throw new Exception('حجم الملف كبير جداً. الحد الأقصى 5 ميغابايت');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('خطأ في رفع الملف');
        }

        // إنشاء مجلد مؤقت
        $upload_dir = __DIR__ . '/uploads/temp/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // نقل الملف إلى المجلد المؤقت
        $temp_file = $upload_dir . uniqid() . '.' . $file_ext;
        if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
            throw new Exception('فشل في حفظ الملف');
        }

        // قراءة وتحليل الملف
        $data = [];
        
        if ($file_ext === 'csv') {
            // معالجة ملف CSV
            if (($handle = fopen($temp_file, "r")) !== FALSE) {
                $headers = fgetcsv($handle);
                while (($row = fgetcsv($handle)) !== FALSE) {
                    if (count($row) >= 3) {
                        $data[] = [
                            'brand' => trim($row[0]),
                            'model' => trim($row[1]),
                            'ecu_number' => trim($row[2]),
                            'eeprom_type' => isset($row[3]) ? trim($row[3]) : ''
                        ];
                    }
                }
                fclose($handle);
            }
        } else {
            // معالجة ملف Excel باستخدام SimpleXLSX
            require_once __DIR__ . '/includes/SimpleXLSX.php';
            
            if ($xlsx = SimpleXLSX::parse($temp_file)) {
                $rows = $xlsx->rows();
                $header_skipped = false;
                
                foreach ($rows as $row) {
                    if (!$header_skipped) {
                        $header_skipped = true;
                        continue; // تجاهل الصف الأول (العناوين)
                    }
                    
                    if (count($row) >= 3 && !empty($row[0]) && !empty($row[1]) && !empty($row[2])) {
                        $data[] = [
                            'brand' => trim($row[0]),
                            'model' => trim($row[1]),
                            'ecu_number' => trim($row[2]),
                            'eeprom_type' => isset($row[3]) ? trim($row[3]) : ''
                        ];
                    }
                }
            } else {
                throw new Exception('خطأ في قراءة ملف Excel: ' . SimpleXLSX::parseError());
            }
        }

        // إدخال البيانات في قاعدة البيانات
        $inserted = 0;
        $duplicates = 0;
        $errors = 0;

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO airbag_ecus (brand, model, ecu_number, eeprom_type) 
            VALUES (?, ?, ?, ?)
        ");

        foreach ($data as $row) {
            if (empty($row['brand']) || empty($row['model']) || empty($row['ecu_number'])) {
                $errors++;
                continue;
            }

            try {
                $stmt->execute([
                    $row['brand'],
                    $row['model'],
                    $row['ecu_number'],
                    $row['eeprom_type']
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $inserted++;
                } else {
                    $duplicates++;
                }
            } catch (PDOException $e) {
                $errors++;
                error_log("Error inserting airbag data: " . $e->getMessage());
            }
        }

        // حذف الملف المؤقت
        unlink($temp_file);

        // إعداد إحصائيات النتيجة
        $stats = [
            'total' => count($data),
            'inserted' => $inserted,
            'duplicates' => $duplicates,
            'errors' => $errors
        ];

        $message = "تم معالجة الملف بنجاح!";

    } catch (Exception $e) {
        $error = $e->getMessage();
        if (isset($temp_file) && file_exists($temp_file)) {
            unlink($temp_file);
        }
    }
}

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

.upload-form {
  background: rgba(255, 255, 255, 0.1);
  padding: 30px;
  border-radius: 12px;
  margin: 25px 0;
  backdrop-filter: blur(10px);
}

.file-input-wrapper {
  position: relative;
  margin: 20px 0;
}

.file-input {
  position: absolute;
  opacity: 0;
  width: 100%;
  height: 100%;
  cursor: pointer;
}

.file-input-label {
  display: inline-block;
  padding: 15px 30px;
  background: linear-gradient(145deg, #1e90ff, #0070cc);
  color: white;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.3s ease;
  border: 2px dashed rgba(255, 255, 255, 0.3);
}

.file-input-label:hover {
  background: linear-gradient(145deg, #2eaaff, #0088ff);
  transform: translateY(-2px);
}

.upload-btn {
  background: linear-gradient(145deg, #28a745, #20a83a);
  color: white;
  padding: 15px 35px;
  border: none;
  border-radius: 10px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.3s ease;
  margin-top: 15px;
}

.upload-btn:hover {
  background: linear-gradient(145deg, #34ce57, #28a745);
  transform: translateY(-2px);
}

.upload-btn:disabled {
  background: #6c757d;
  cursor: not-allowed;
  transform: none;
}

.alert {
  padding: 15px;
  border-radius: 10px;
  margin: 15px 0;
  font-weight: bold;
}

.alert-success {
  background: rgba(40, 167, 69, 0.2);
  border: 1px solid #28a745;
  color: #d4edda;
}

.alert-error {
  background: rgba(220, 53, 69, 0.2);
  border: 1px solid #dc3545;
  color: #f8d7da;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin: 20px 0;
}

.stat-card {
  background: rgba(255, 255, 255, 0.1);
  padding: 20px;
  border-radius: 10px;
  backdrop-filter: blur(5px);
}

.stat-number {
  font-size: 2em;
  font-weight: bold;
  color: #00d4ff;
}

.stat-label {
  color: #a8d8ff;
  margin-top: 5px;
}

.instructions {
  background: rgba(255, 255, 255, 0.1);
  padding: 20px;
  border-radius: 10px;
  margin: 20px 0;
  text-align: right;
  direction: rtl;
}

.instructions h3 {
  color: #00d4ff;
  margin-bottom: 15px;
}

.instructions ul {
  text-align: right;
  padding-right: 20px;
}

.instructions li {
  margin: 8px 0;
  color: #a8d8ff;
}

.back-link {
  display: inline-block;
  margin-top: 20px;
  padding: 12px 25px;
  background: linear-gradient(145deg, #6c757d, #5a6268);
  color: white;
  text-decoration: none;
  border-radius: 10px;
  transition: all 0.3s ease;
}

.back-link:hover {
  background: linear-gradient(145deg, #7a8288, #6c757d);
  transform: translateY(-2px);
}

.selected-file {
  color: #28a745;
  font-weight: bold;
  margin-top: 10px;
}
CSS;

// تعريف محتوى الصفحة
ob_start();
?>
<div class="container">
  <h1>📋 رفع ومعالجة ملف Excel للإيرباق</h1>
  
  <!-- تعليمات الاستخدام -->
  <div class="instructions">
    <h3>تعليمات هامة:</h3>
    <ul>
      <li>تأكد من أن الملف يحتوي على الأعمدة التالية: Brand, Model, ECU/Part Number</li>
      <li>الصف الأول يجب أن يحتوي على عناوين الأعمدة</li>
      <li>صيغ الملفات المدعومة: .xlsx, .xls, .csv</li>
      <li>الحد الأقصى لحجم الملف: 5 ميغابايت</li>
      <li>سيتم تجاهل السجلات المكررة تلقائياً</li>
    </ul>
  </div>

  <!-- رسائل النجاح والخطأ -->
  <?php if ($message): ?>
    <div class="alert alert-success">
      ✅ <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-error">
      ❌ <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <!-- إحصائيات النتيجة -->
  <?php if ($stats): ?>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-number"><?= $stats['total'] ?></div>
        <div class="stat-label">إجمالي السجلات</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?= $stats['inserted'] ?></div>
        <div class="stat-label">تم إدخالها</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?= $stats['duplicates'] ?></div>
        <div class="stat-label">مكررة</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?= $stats['errors'] ?></div>
        <div class="stat-label">أخطاء</div>
      </div>
    </div>
  <?php endif; ?>

  <!-- نموذج رفع الملف -->
  <form method="POST" enctype="multipart/form-data" class="upload-form" id="uploadForm">
    <h3>📁 اختر ملف Excel أو CSV</h3>
    
    <div class="file-input-wrapper">
      <input type="file" name="excel_file" id="excelFile" class="file-input" 
             accept=".xlsx,.xls,.csv" required onchange="updateFileName()">
      <label for="excelFile" class="file-input-label">
        📎 انقر لاختيار الملف
      </label>
    </div>
    
    <div id="selectedFile" class="selected-file" style="display: none;"></div>
    
    <button type="submit" class="upload-btn" id="uploadBtn" disabled>
      🚀 رفع ومعالجة الملف
    </button>
  </form>

  <!-- زر العودة -->
  <a href="home.php" class="back-link">
    ↩️ العودة إلى الصفحة الرئيسية
  </a>
</div>

<script>
function updateFileName() {
    const fileInput = document.getElementById('excelFile');
    const selectedFileDiv = document.getElementById('selectedFile');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (fileInput.files.length > 0) {
        const fileName = fileInput.files[0].name;
        selectedFileDiv.textContent = '✓ تم اختيار: ' + fileName;
        selectedFileDiv.style.display = 'block';
        uploadBtn.disabled = false;
    } else {
        selectedFileDiv.style.display = 'none';
        uploadBtn.disabled = true;
    }
}

// منع إرسال النموذج مرتين
document.getElementById('uploadForm').addEventListener('submit', function() {
    document.getElementById('uploadBtn').disabled = true;
    document.getElementById('uploadBtn').textContent = '⏳ جاري المعالجة...';
});
</script>

<?php
$page_content = ob_get_clean();

// إدراج القالب
include __DIR__ . '/includes/layout.php';
?>