<?php
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
$page_title = 'إدارة صور الكمبيوترات';
$display_title = 'إدارة صور التوصيلات والبوردات';

$message = '';
$error = '';

// معالجة رفع الصور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'upload_images') {
            $brand = trim($_POST['brand']);
            $model = trim($_POST['model']);
            $ecu_number = trim($_POST['ecu_number']);
            $description = trim($_POST['description']);
            
            if (empty($brand) || empty($model) || empty($ecu_number)) {
                throw new Exception('جميع الحقول الأساسية مطلوبة');
            }
            
            // التحقق من وجود الكمبيوتر في قاعدة البيانات
            $stmt = $pdo->prepare("SELECT id FROM airbag_ecus WHERE brand = ? AND model = ? AND ecu_number = ?");
            $stmt->execute([$brand, $model, $ecu_number]);
            if (!$stmt->fetch()) {
                throw new Exception('رقم الكمبيوتر غير موجود في قاعدة البيانات');
            }
            
            // إنشاء مجلد الصور
            $upload_dir = __DIR__ . '/uploads/ecu_images/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $wiring_image = null;
            $board_image = null;
            
            // معالجة صورة التوصيلات
            if (isset($_FILES['wiring_image']) && $_FILES['wiring_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['wiring_image'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (!in_array($file_ext, $allowed_ext)) {
                    throw new Exception('نوع ملف صورة التوصيلات غير مدعوم');
                }
                
                if ($file['size'] > 5 * 1024 * 1024) {
                    throw new Exception('حجم صورة التوصيلات كبير جداً (أكثر من 5 ميغابايت)');
                }
                
                $wiring_image = 'wiring_' . uniqid() . '.' . $file_ext;
                if (!move_uploaded_file($file['tmp_name'], $upload_dir . $wiring_image)) {
                    throw new Exception('فشل في حفظ صورة التوصيلات');
                }
            }
            
            // معالجة صورة البورد
            if (isset($_FILES['board_image']) && $_FILES['board_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['board_image'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (!in_array($file_ext, $allowed_ext)) {
                    throw new Exception('نوع ملف صورة البورد غير مدعوم');
                }
                
                if ($file['size'] > 5 * 1024 * 1024) {
                    throw new Exception('حجم صورة البورد كبير جداً (أكثر من 5 ميغابايت)');
                }
                
                $board_image = 'board_' . uniqid() . '.' . $file_ext;
                if (!move_uploaded_file($file['tmp_name'], $upload_dir . $board_image)) {
                    throw new Exception('فشل في حفظ صورة البورد');
                }
            }
            
            // إدخال أو تحديث البيانات
            $stmt = $pdo->prepare("
                INSERT INTO ecu_images (brand, model, ecu_number, wiring_image, board_image, description)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                wiring_image = COALESCE(VALUES(wiring_image), wiring_image),
                board_image = COALESCE(VALUES(board_image), board_image),
                description = VALUES(description)
            ");
            
            $stmt->execute([$brand, $model, $ecu_number, $wiring_image, $board_image, $description]);
            
            $message = 'تم رفع الصور بنجاح';
        }
        
        if ($_POST['action'] === 'delete_image') {
            $id = (int)$_POST['id'];
            $image_type = $_POST['image_type'];
            
            // جلب معلومات الصورة
            $stmt = $pdo->prepare("SELECT wiring_image, board_image FROM ecu_images WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $file_to_delete = '';
                if ($image_type === 'wiring' && $row['wiring_image']) {
                    $file_to_delete = $row['wiring_image'];
                    $stmt = $pdo->prepare("UPDATE ecu_images SET wiring_image = NULL WHERE id = ?");
                } elseif ($image_type === 'board' && $row['board_image']) {
                    $file_to_delete = $row['board_image'];
                    $stmt = $pdo->prepare("UPDATE ecu_images SET board_image = NULL WHERE id = ?");
                }
                
                if ($file_to_delete) {
                    $stmt->execute([$id]);
                    $file_path = $upload_dir . $file_to_delete;
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                    $message = 'تم حذف الصورة بنجاح';
                }
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// جلب العلامات التجارية
$brands = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT brand FROM airbag_ecus ORDER BY brand");
    $brands = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching brands: " . $e->getMessage());
}

// جلب الصور الموجودة
$images = [];
try {
    $stmt = $pdo->query("
        SELECT ei.*, ae.eeprom_type 
        FROM ecu_images ei
        LEFT JOIN airbag_ecus ae ON ei.brand = ae.brand AND ei.model = ae.model AND ei.ecu_number = ae.ecu_number
        ORDER BY ei.brand, ei.model, ei.ecu_number
    ");
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching images: " . $e->getMessage());
}

// CSS مخصص للصفحة
$page_css = <<<CSS
.container {
  background: rgba(0, 0, 0, 0.7);
  padding: 35px;
  width: 95%;
  max-width: 1200px;
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
  text-align: right;
  direction: rtl;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin: 20px 0;
}

.form-group {
  text-align: right;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  color: #a8d8ff;
  font-weight: bold;
}

.form-control {
  width: 100%;
  padding: 10px;
  background: rgba(255, 255, 255, 0.1);
  border: 2px solid rgba(66, 135, 245, 0.3);
  border-radius: 8px;
  color: white;
  direction: rtl;
  text-align: right;
}

.form-control:focus {
  outline: none;
  border-color: #00d4ff;
  background: rgba(255, 255, 255, 0.15);
}

.file-input-group {
  margin: 15px 0;
  padding: 15px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  border: 1px dashed rgba(66, 135, 245, 0.3);
}

.file-input {
  width: 100%;
  padding: 8px;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(66, 135, 245, 0.3);
  border-radius: 6px;
  color: white;
}

.submit-btn {
  background: linear-gradient(145deg, #28a745, #20a83a);
  color: white;
  padding: 12px 30px;
  border: none;
  border-radius: 8px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.3s ease;
}

.submit-btn:hover {
  background: linear-gradient(145deg, #34ce57, #28a745);
  transform: translateY(-2px);
}

.images-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
  margin: 30px 0;
}

.image-card {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 20px;
  backdrop-filter: blur(5px);
  border: 1px solid rgba(66, 135, 245, 0.3);
}

.image-card h3 {
  color: #00d4ff;
  margin-bottom: 15px;
  font-size: 18px;
}

.image-card .details {
  text-align: right;
  direction: rtl;
  margin-bottom: 15px;
  color: #a8d8ff;
}

.image-preview {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 10px;
  margin: 15px 0;
}

.image-preview div {
  text-align: center;
}

.image-preview img {
  width: 100%;
  height: 80px;
  object-fit: cover;
  border-radius: 8px;
  cursor: pointer;
  transition: transform 0.3s ease;
}

.image-preview img:hover {
  transform: scale(1.05);
}

.delete-btn {
  background: linear-gradient(145deg, #dc3545, #c82333);
  color: white;
  padding: 5px 12px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  margin-top: 5px;
}

.delete-btn:hover {
  background: linear-gradient(145deg, #e4606d, #dc3545);
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

.modal {
  display: none;
  position: fixed;
  z-index: 2000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.8);
}

.modal-content {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  max-width: 90%;
  max-height: 90%;
}

.modal img {
  width: 100%;
  height: auto;
  border-radius: 10px;
}

.close {
  position: absolute;
  top: 10px;
  right: 20px;
  color: white;
  font-size: 35px;
  font-weight: bold;
  cursor: pointer;
}

.close:hover {
  color: #ccc;
}
CSS;

// تعريف محتوى الصفحة
ob_start();
?>
<div class="container">
  <h1>🖼️ إدارة صور الكمبيوترات</h1>

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

  <!-- نموذج رفع الصور -->
  <div class="upload-form">
    <h2>رفع صور جديدة</h2>
    <form method="POST" enctype="multipart/form-data" id="uploadForm">
      <input type="hidden" name="action" value="upload_images">
      
      <div class="form-grid">
        <div class="form-group">
          <label for="brand">العلامة التجارية</label>
          <select name="brand" id="brand" class="form-control" required>
            <option value="">اختر العلامة التجارية</option>
            <?php foreach ($brands as $brand): ?>
              <option value="<?= htmlspecialchars($brand) ?>"><?= htmlspecialchars($brand) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="model">الموديل</label>
          <select name="model" id="model" class="form-control" required>
            <option value="">اختر الموديل</option>
          </select>
        </div>

        <div class="form-group">
          <label for="ecu_number">رقم الكمبيوتر</label>
          <select name="ecu_number" id="ecu_number" class="form-control" required>
            <option value="">اختر رقم الكمبيوتر</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label for="description">وصف إضافي (اختياري)</label>
        <textarea name="description" id="description" class="form-control" rows="3" 
                  placeholder="أدخل أي ملاحظات أو تعليمات خاصة"></textarea>
      </div>

      <div class="file-input-group">
        <label for="wiring_image">صورة التوصيلات</label>
        <input type="file" name="wiring_image" id="wiring_image" class="file-input" 
               accept="image/*">
      </div>

      <div class="file-input-group">
        <label for="board_image">صورة البورد الداخلية</label>
        <input type="file" name="board_image" id="board_image" class="file-input" 
               accept="image/*">
      </div>

      <button type="submit" class="submit-btn">
        📤 رفع الصور
      </button>
    </form>
  </div>

  <!-- عرض الصور الموجودة -->
  <h2>الصور الموجودة</h2>
  <div class="images-grid">
    <?php foreach ($images as $image): ?>
      <div class="image-card">
        <h3><?= htmlspecialchars($image['brand'] . ' - ' . $image['model']) ?></h3>
        <div class="details">
          <strong>رقم الكمبيوتر:</strong> <?= htmlspecialchars($image['ecu_number']) ?><br>
          <?php if ($image['eeprom_type']): ?>
            <strong>نوع EEPROM:</strong> <?= htmlspecialchars($image['eeprom_type']) ?><br>
          <?php endif; ?>
          <?php if ($image['description']): ?>
            <strong>الوصف:</strong> <?= htmlspecialchars($image['description']) ?>
          <?php endif; ?>
        </div>

        <div class="image-preview">
          <?php if ($image['wiring_image']): ?>
            <div>
              <strong>التوصيلات</strong>
              <img src="uploads/ecu_images/<?= htmlspecialchars($image['wiring_image']) ?>" 
                   alt="صورة التوصيلات" onclick="openModal(this.src)">
              <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="delete_image">
                <input type="hidden" name="id" value="<?= $image['id'] ?>">
                <input type="hidden" name="image_type" value="wiring">
                <button type="submit" class="delete-btn" 
                        onclick="return confirm('هل أنت متأكد من حذف صورة التوصيلات؟')">
                  🗑️ حذف
                </button>
              </form>
            </div>
          <?php endif; ?>

          <?php if ($image['board_image']): ?>
            <div>
              <strong>البورد</strong>
              <img src="uploads/ecu_images/<?= htmlspecialchars($image['board_image']) ?>" 
                   alt="صورة البورد" onclick="openModal(this.src)">
              <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="delete_image">
                <input type="hidden" name="id" value="<?= $image['id'] ?>">
                <input type="hidden" name="image_type" value="board">
                <button type="submit" class="delete-btn" 
                        onclick="return confirm('هل أنت متأكد من حذف صورة البورد؟')">
                  🗑️ حذف
                </button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- زر العودة -->
  <a href="home.php" class="back-link">
    ↩️ العودة إلى الصفحة الرئيسية
  </a>
</div>

<!-- مودال عرض الصور -->
<div id="imageModal" class="modal">
  <span class="close">&times;</span>
  <div class="modal-content">
    <img id="modalImage" src="" alt="">
  </div>
</div>

<script>
// استخدام نفس JavaScript المستخدم في صفحة airbag-reset
document.addEventListener('DOMContentLoaded', function() {
  const brandSelect = document.getElementById('brand');
  const modelSelect = document.getElementById('model');
  const ecuSelect = document.getElementById('ecu_number');

  // تحديث الموديلات عند اختيار العلامة التجارية
  brandSelect.addEventListener('change', function() {
    const brand = this.value;
    modelSelect.innerHTML = '<option value="">اختر الموديل</option>';
    ecuSelect.innerHTML = '<option value="">اختر رقم الكمبيوتر</option>';

    if (brand) {
      fetch('ajax/get_models.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'brand=' + encodeURIComponent(brand)
      })
      .then(response => response.json())
      .then(data => {
        data.forEach(model => {
          const option = document.createElement('option');
          option.value = model;
          option.textContent = model;
          modelSelect.appendChild(option);
        });
      })
      .catch(error => console.error('Error:', error));
    }
  });

  // تحديث أرقام الكمبيوتر عند اختيار الموديل
  modelSelect.addEventListener('change', function() {
    const brand = brandSelect.value;
    const model = this.value;
    ecuSelect.innerHTML = '<option value="">اختر رقم الكمبيوتر</option>';

    if (brand && model) {
      fetch('ajax/get_ecus.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'brand=' + encodeURIComponent(brand) + '&model=' + encodeURIComponent(model)
      })
      .then(response => response.json())
      .then(data => {
        data.forEach(ecu => {
          const option = document.createElement('option');
          option.value = ecu;
          option.textContent = ecu;
          ecuSelect.appendChild(option);
        });
      })
      .catch(error => console.error('Error:', error));
    }
  });

  // فتح مودال الصورة
  window.openModal = function(imageSrc) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    modal.style.display = 'block';
    modalImg.src = imageSrc;
  };

  // إغلاق المودال
  document.getElementsByClassName('close')[0].addEventListener('click', function() {
    document.getElementById('imageModal').style.display = 'none';
  });

  window.addEventListener('click', function(event) {
    const modal = document.getElementById('imageModal');
    if (event.target === modal) {
      modal.style.display = 'none';
    }
  });
});
</script>

<?php
$page_content = ob_get_clean();

// إدراج القالب
include __DIR__ . '/includes/layout.php';
?>