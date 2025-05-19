
<?php
/**
 * FlexAutoPro - نظام بحث وإعادة ضبط الإيرباق للعملاء
 * 
 * صفحة العميل لبحث واستعراض بيانات كمبيوترات الإيرباق
 * 
 * @version     2.0.0
 * @author      FlexAutoPro Team
 * @copyright   2025 FlexAutoPro
 */

session_start();
require_once __DIR__ . '/includes/db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'العميل';
$user_role = $_SESSION['user_role'] ?? 'customer';
$email = $_SESSION['email'] ?? '';

// إعداد عنوان الصفحة
$page_title = 'مسح وإعادة ضبط بيانات الإيرباق';
$display_title = 'نظام مسح وإعادة ضبط الإيرباق';

// متغيرات البحث
$query = $_GET['query'] ?? '';
$selected_brand = $_GET['brand'] ?? '';
$selected_model = $_GET['model'] ?? '';
$selected_ecu = $_GET['ecu'] ?? '';

// نتائج البحث
$ecu_data = null;
$has_result = false;
$search_message = '';

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

// جلب العلامات التجارية للفلتر
$brands = $pdo->query("SELECT DISTINCT brand FROM airbag_ecus ORDER BY brand")->fetchAll(PDO::FETCH_COLUMN);

// إضافة تسجيل للبحث (إذا كنت تريد تتبع عمليات البحث)
if ($has_result && !empty($ecu_data)) {
    try {
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
    } catch (Exception $e) {
        // لا نقوم بعرض أخطاء السجل للمستخدم
        error_log('Error logging search: ' . $e->getMessage());
    }
}

// CSS مخصص للصفحة
$page_css = <<<CSS
.main-container {
  background: rgba(0, 0, 0, 0.7);
  padding: 30px;
  width: 95%;
  max-width: 1200px;
  border-radius: 16px;
  text-align: center;
  margin: 30px auto;
  box-shadow: 0 0 40px rgba(0, 200, 255, 0.15);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(66, 135, 245, 0.25);
}

.search-container {
  background: rgba(255, 255, 255, 0.05);
  padding: 25px;
  border-radius: 12px;
  margin-bottom: 30px;
  border: 1px solid rgba(66, 135, 245, 0.15);
}

.search-title {
  color: #00d4ff;
  margin-bottom: 20px;
  font-size: 1.5em;
}

.search-form {
  display: flex;
  flex-direction: column;
  gap: 15px;
  max-width: 800px;
  margin: 0 auto;
}

.form-group {
  display: flex;
  flex-direction: column;
  text-align: right;
}

.form-group label {
  margin-bottom: 8px;
  color: #a8d8ff;
  font-weight: bold;
}

.form-control {
  padding: 12px;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(66, 135, 245, 0.3);
  border-radius: 8px;
  color: white;
  text-align: right;
  direction: rtl;
}

.form-control:focus {
  outline: none;
  border-color: #00d4ff;
  background: rgba(255, 255, 255, 0.15);
}

.search-actions {
  display: flex;
  justify-content: center;
  gap: 15px;
  margin-top: 20px;
}

.btn {
  padding: 12px 25px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: bold;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-block;
}

.btn-primary {
  background: linear-gradient(145deg, #1e90ff, #0070cc);
  color: white;
}

.btn-primary:hover {
  background: linear-gradient(145deg, #2eaaff, #0088ff);
  transform: translateY(-2px);
}

.btn-secondary {
  background: linear-gradient(145deg, #6c757d, #5a6268);
  color: white;
}

.btn-secondary:hover {
  background: linear-gradient(145deg, #7a8288, #6c757d);
  transform: translateY(-2px);
}

.result-container {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 12px;
  padding: 25px;
  margin-top: 30px;
  border: 1px solid rgba(66, 135, 245, 0.15);
  text-align: right;
  direction: rtl;
}

.result-title {
  color: #00d4ff;
  margin-bottom: 20px;
  font-size: 1.5em;
  text-align: center;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  margin: 15px 0;
}

.data-table th,
.data-table td {
  padding: 12px;
  text-align: right;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.data-table th {
  background: rgba(0, 0, 0, 0.3);
  color: #00d4ff;
  font-weight: bold;
}

.data-table td {
  color: #a8d8ff;
}

.instructions {
  background: rgba(0, 0, 0, 0.3);
  padding: 20px;
  border-radius: 10px;
  margin-top: 20px;
  text-align: right;
  direction: rtl;
  border: 1px solid rgba(66, 135, 245, 0.15);
}

.instructions ol {
  text-align: right;
  padding-right: 20px;
}

.instructions li {
  margin-bottom: 10px;
  color: #a8d8ff;
}

.alert {
  padding: 15px;
  border-radius: 10px;
  margin: 15px 0;
  text-align: center;
  direction: rtl;
}

.alert-info {
  background: rgba(23, 162, 184, 0.2);
  border: 1px solid #17a2b8;
  color: #aef0ff;
}

.alert-warning {
  background: rgba(255, 193, 7, 0.2);
  border: 1px solid #ffc107;
  color: #ffe699;
}

.image-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 15px;
  margin: 20px 0;
}

.ecu-image {
  border: 1px solid rgba(66, 135, 245, 0.3);
  border-radius: 8px;
  overflow: hidden;
  background: rgba(0, 0, 0, 0.5);
  position: relative;
}

.ecu-image img {
  width: 100%;
  height: auto;
  transition: transform 0.3s ease;
  cursor: pointer;
}

.ecu-image img:hover {
  transform: scale(1.05);
}

.image-caption {
  background: rgba(0, 0, 0, 0.7);
  color: #a8d8ff;
  padding: 8px;
  text-align: center;
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
  background: rgba(0, 0, 0, 0.9);
  margin: 5% auto;
  padding: 30px;
  border-radius: 15px;
  max-width: 80%;
  max-height: 80vh;
  overflow: auto;
  border: 1px solid rgba(66, 135, 245, 0.3);
  position: relative;
}

.close {
  color: #aaa;
  position: absolute;
  top: 10px;
  right: 20px;
  font-size: 28px;
  font-weight: bold;
}

.close:hover {
  color: white;
  cursor: pointer;
}

.search-results {
  margin: 20px 0;
}

.search-results table {
  width: 100%;
  border-collapse: collapse;
}

.search-results th,
.search-results td {
  padding: 10px;
  text-align: right;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.search-results tr:hover {
  background: rgba(255, 255, 255, 0.05);
  cursor: pointer;
}

.search-results .result-link {
  color: #40a9ff;
  text-decoration: none;
}

.search-results .result-link:hover {
  text-decoration: underline;
}

.info-box {
  background: rgba(0, 123, 255, 0.1);
  border: 1px solid rgba(0, 123, 255, 0.3);
  border-radius: 8px;
  padding: 15px;
  margin-top: 20px;
  margin-bottom: 20px;
}

.info-box h3 {
  color: #00d4ff;
  margin-top: 0;
  margin-bottom: 10px;
}

.info-box p {
  color: #a8d8ff;
  margin: 0;
}

.autocomplete-container {
  position: relative;
}

.autocomplete-results {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  z-index: 1000;
  max-height: 200px;
  overflow-y: auto;
  background: rgba(0, 0, 0, 0.9);
  border: 1px solid rgba(66, 135, 245, 0.3);
  border-radius: 0 0 8px 8px;
  display: none;
}

.autocomplete-item {
  padding: 10px 15px;
  cursor: pointer;
  text-align: right;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.autocomplete-item:hover,
.autocomplete-item.selected {
  background: rgba(66, 135, 245, 0.3);
}

/* تخطيط متجاوب */
@media (min-width: 768px) {
  .search-form {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
  }
  
  .form-group.full-width {
    grid-column: span 2;
  }
}

@media (max-width: 767px) {
  .main-container {
    padding: 20px;
    width: 90%;
  }
  
  .btn {
    padding: 10px 15px;
    font-size: 14px;
  }
  
  .search-actions {
    flex-direction: column;
    align-items: center;
  }
  
  .image-container {
    grid-template-columns: 1fr;
  }
}
CSS;

// محتوى الصفحة
ob_start();
?>
<div class="main-container">
  <h1><?= $display_title ?></h1>
  
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
        <?php if (!empty($ecu_data['crash_location'])): ?>
        <tr>
          <th>موقع بيانات الحادث:</th>
          <td><?= htmlspecialchars($ecu_data['crash_location']) ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($ecu_data['reset_procedure'])): ?>
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
              <?php if (!empty($image['description'])): ?>
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
        بمجرد العثور على الكمبيوتر المطلوب، ستتمكن من رؤية صور المخطط وتعليمات إعادة الضبط.
      </p>
    </div>
  <?php endif; ?>
</div>

<!-- مودال عرض الصور -->
<div id="imageModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeImageModal()">&times;</span>
    <img id="modalImage" src="" alt="صورة الإيرباق" style="width: 100%; height: auto;">
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // الإكمال التلقائي للماركة
  setupAutocomplete('brand', 'brand-results', 'brands');
  
  // الإكمال التلقائي للموديل
  setupAutocomplete('model', 'model-results', 'models', function() {
    return {
      brand: document.getElementById('brand').value
    };
  });
  
  // الإكمال التلقائي لرقم الكمبيوتر
  setupAutocomplete('ecu', 'ecu-results', 'ecus', function() {
    return {
      brand: document.getElementById('brand').value,
      model: document.getElementById('model').value
    };
  });
});

// دالة إعداد الإكمال التلقائي
function setupAutocomplete(inputId, resultsId, action, paramsCallback) {
  const input = document.getElementById(inputId);
  const resultsContainer = document.getElementById(resultsId);
  
  let selectedIndex = -1;
  let items = [];
  
  input.addEventListener('input', function() {
    const query = this.value.trim();
    if (query.length < 1) {
      resultsContainer.style.display = 'none';
      return;
    }
    
    // بناء المعلمات الإضافية
    let extraParams = '';
    if (paramsCallback) {
      const params = paramsCallback();
      for (const key in params) {
        if (params[key]) {
          extraParams += `&${key}=${encodeURIComponent(params[key])}`;
        }
      }
    }
    
    // إجراء طلب الإكمال التلقائي
    fetch(`search_airbag_ecus.php?action=${action}&q=${encodeURIComponent(query)}${extraParams}`)
      .then(response => response.json())
      .then(data => {
        if (data.error) {
          console.error(data.error);
          return;
        }
        
        items = data;
        
        if (items.length === 0) {
          resultsContainer.style.display = 'none';
          return;
        }
        
        // عرض النتائج
        resultsContainer.innerHTML = '';
        items.forEach((item, index) => {
          const div = document.createElement('div');
          div.className = 'autocomplete-item';
          div.textContent = item;
          div.addEventListener('click', function() {
            input.value = item;
            resultsContainer.style.display = 'none';
          });
          resultsContainer.appendChild(div);
        });
        
        resultsContainer.style.display = 'block';
        selectedIndex = -1;
      })
      .catch(error => {
        console.error('Error fetching autocomplete results:', error);
      });
  });
  
  // التنقل في القائمة باستخدام لوحة المفاتيح
  input.addEventListener('keydown', function(e) {
    const itemElements = resultsContainer.querySelectorAll('.autocomplete-item');
    
    if (itemElements.length === 0) return;
    
    // السهم لأسفل
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedIndex = (selectedIndex + 1) % itemElements.length;
      updateSelectedItem(itemElements);
    }
    // السهم لأعلى
    else if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedIndex = (selectedIndex - 1 + itemElements.length) % itemElements.length;
      updateSelectedItem(itemElements);
    }
    // Enter
    else if (e.key === 'Enter' && selectedIndex !== -1) {
      e.preventDefault();
      input.value = items[selectedIndex];
      resultsContainer.style.display = 'none';
    }
    // Escape
    else if (e.key === 'Escape') {
      resultsContainer.style.display = 'none';
    }
  });
  
  // تحديث العنصر المحدد
  function updateSelectedItem(itemElements) {
    itemElements.forEach((item, index) => {
      if (index === selectedIndex) {
        item.classList.add('selected');
        item.scrollIntoView({ block: 'nearest' });
      } else {
        item.classList.remove('selected');
      }
    });
  }
  
  // إخفاء القائمة عند النقر في مكان آخر
  document.addEventListener('click', function(e) {
    if (e.target !== input && e.target !== resultsContainer) {
      resultsContainer.style.display = 'none';
    }
  });
}

// دوال عرض الصور
function openImageModal(src) {
  const modal = document.getElementById('imageModal');
  const modalImg = document.getElementById('modalImage');
  modal.style.display = 'block';
  modalImg.src = src;
}

function closeImageModal() {
  document.getElementById('imageModal').style.display = 'none';
}

// إغلاق المودال عند النقر خارجه
window.onclick = function(event) {
  const modal = document.getElementById('imageModal');
  if (event.target === modal) {
    closeImageModal();
  }
}
</script>

<?php
$page_content = ob_get_clean();

// إدراج القالب
include __DIR__ . '/includes/layout.php';
?>
