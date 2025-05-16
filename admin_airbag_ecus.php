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
$page_title = 'إدارة بيانات الإيرباق';
$display_title = 'إدارة قاعدة بيانات كمبيوترات الإيرباق';

$message = '';
$error = '';

// معالجة طلبات POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add_ecu') {
            $brand = trim($_POST['brand']);
            $model = trim($_POST['model']);
            $ecu_number = trim($_POST['ecu_number']);
            $eeprom_type = trim($_POST['eeprom_type']);
            
            if (empty($brand) || empty($model) || empty($ecu_number)) {
                throw new Exception('العلامة التجارية والموديل ورقم الكمبيوتر مطلوبة');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO airbag_ecus (brand, model, ecu_number, eeprom_type) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$brand, $model, $ecu_number, $eeprom_type]);
            
            $message = 'تم إضافة الكمبيوتر بنجاح';
        }
        
        if ($_POST['action'] === 'edit_ecu') {
            $id = (int)$_POST['id'];
            $brand = trim($_POST['brand']);
            $model = trim($_POST['model']);
            $ecu_number = trim($_POST['ecu_number']);
            $eeprom_type = trim($_POST['eeprom_type']);
            
            if (empty($brand) || empty($model) || empty($ecu_number)) {
                throw new Exception('العلامة التجارية والموديل ورقم الكمبيوتر مطلوبة');
            }
            
            $stmt = $pdo->prepare("
                UPDATE airbag_ecus 
                SET brand = ?, model = ?, ecu_number = ?, eeprom_type = ? 
                WHERE id = ?
            ");
            $stmt->execute([$brand, $model, $ecu_number, $eeprom_type, $id]);
            
            $message = 'تم تحديث بيانات الكمبيوتر بنجاح';
        }
        
        if ($_POST['action'] === 'delete_ecu') {
            $id = (int)$_POST['id'];
            
            // حذف الصور المرتبطة أولاً
            $stmt = $pdo->prepare("SELECT * FROM ecu_images WHERE id = ?");
            $stmt->execute([$id]);
            
            // ثم حذف السجل
            $stmt = $pdo->prepare("DELETE FROM airbag_ecus WHERE id = ?");
            $stmt->execute([$id]);
            
            $message = 'تم حذف الكمبيوتر بنجاح';
        }
        
        if ($_POST['action'] === 'export_excel') {
            // تصدير البيانات إلى Excel
            exportToExcel($pdo);
            exit;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// جلب البيانات مع الفلترة
$search_brand = $_GET['search_brand'] ?? '';
$search_model = $_GET['search_model'] ?? '';
$search_ecu = $_GET['search_ecu'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where_conditions = [];
$params = [];

if (!empty($search_brand)) {
    $where_conditions[] = "brand LIKE ?";
    $params[] = '%' . $search_brand . '%';
}
if (!empty($search_model)) {
    $where_conditions[] = "model LIKE ?";
    $params[] = '%' . $search_model . '%';
}
if (!empty($search_ecu)) {
    $where_conditions[] = "ecu_number LIKE ?";
    $params[] = '%' . $search_ecu . '%';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// عدد السجلات
$count_sql = "SELECT COUNT(*) FROM airbag_ecus $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();

// جلب البيانات
$sql = "
    SELECT ae.*, 
           (SELECT COUNT(*) FROM ecu_images ei WHERE ei.brand = ae.brand AND ei.model = ae.model AND ei.ecu_number = ae.ecu_number) as has_images
    FROM airbag_ecus ae 
    $where_clause 
    ORDER BY ae.brand, ae.model, ae.ecu_number 
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ecus = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_pages = ceil($total_records / $per_page);

// جلب العلامات التجارية للفلتر
$brands = $pdo->query("SELECT DISTINCT brand FROM airbag_ecus ORDER BY brand")->fetchAll(PDO::FETCH_COLUMN);

// دالة تصدير Excel
function exportToExcel($pdo) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="airbag_ecus_' . date('Y-m-d') . '.xls"');
    
    echo '<table border="1">';
    echo '<tr><th>العلامة التجارية</th><th>الموديل</th><th>رقم الكمبيوتر</th><th>نوع EEPROM</th><th>تاريخ الإنشاء</th></tr>';
    
    $stmt = $pdo->query("SELECT * FROM airbag_ecus ORDER BY brand, model, ecu_number");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['brand']) . '</td>';
        echo '<td>' . htmlspecialchars($row['model']) . '</td>';
        echo '<td>' . htmlspecialchars($row['ecu_number']) . '</td>';
        echo '<td>' . htmlspecialchars($row['eeprom_type']) . '</td>';
        echo '<td>' . htmlspecialchars($row['created_at']) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}

// CSS مخصص للصفحة
$page_css = <<<CSS
.container {
  background: rgba(0, 0, 0, 0.7);
  padding: 35px;
  width: 95%;
  max-width: 1400px;
  border-radius: 16px;
  text-align: center;
  margin: 30px auto;
  box-shadow: 0 0 40px rgba(0, 200, 255, 0.15);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(66, 135, 245, 0.25);
}

.action-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 20px 0;
  flex-wrap: wrap;
  gap: 15px;
}

.search-form {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}

.search-input {
  padding: 8px 12px;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(66, 135, 245, 0.3);
  border-radius: 6px;
  color: white;
  min-width: 150px;
}

.search-input::placeholder {
  color: rgba(255, 255, 255, 0.5);
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
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

.btn-success {
  background: linear-gradient(145deg, #28a745, #20a83a);
  color: white;
}

.btn-success:hover {
  background: linear-gradient(145deg, #34ce57, #28a745);
}

.btn-warning {
  background: linear-gradient(145deg, #ffc107, #e0a800);
  color: #000;
}

.btn-warning:hover {
  background: linear-gradient(145deg, #ffcd39, #ffc107);
}

.btn-danger {
  background: linear-gradient(145deg, #dc3545, #c82333);
  color: white;
}

.btn-danger:hover {
  background: linear-gradient(145deg, #e4606d, #dc3545);
}

.btn-small {
  padding: 5px 10px;
  font-size: 12px;
}

.table-container {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 10px;
  overflow: hidden;
  margin: 20px 0;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
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

.data-table tr:hover {
  background: rgba(255, 255, 255, 0.05);
}

.pagination {
  display: flex;
  justify-content: center;
  gap: 10px;
  margin: 20px 0;
}

.pagination a, .pagination span {
  padding: 8px 12px;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(66, 135, 245, 0.3);
  border-radius: 6px;
  color: white;
  text-decoration: none;
  transition: all 0.3s ease;
}

.pagination a:hover {
  background: rgba(66, 135, 245, 0.3);
}

.pagination .current {
  background: linear-gradient(145deg, #1e90ff, #0070cc);
  border-color: #00d4ff;
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
  width: 90%;
  max-width: 500px;
  border: 1px solid rgba(66, 135, 245, 0.3);
}

.modal-form {
  text-align: right;
  direction: rtl;
}

.form-group {
  margin: 15px 0;
}

.form-group label {
  display: block;
  color: #a8d8ff;
  font-weight: bold;
  margin-bottom: 8px;
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

.close {
  color: #aaa;
  float: left;
  font-size: 28px;
  font-weight: bold;
  margin-top: -10px;
}

.close:hover {
  color: white;
  cursor: pointer;
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

.has-images {
  color: #28a745;
  font-weight: bold;
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
  text-align: center;
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
CSS;

// تعريف محتوى الصفحة
ob_start();
?>
<div class="container">
  <h1>🗃️ إدارة بيانات الإيرباق</h1>

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

  <!-- إحصائيات -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-number"><?= $total_records ?></div>
      <div class="stat-label">إجمالي الكمبيوترات</div>
    </div>
    <div class="stat-card">
      <div class="stat-number"><?= count($brands) ?></div>
      <div class="stat-label">عدد العلامات التجارية</div>
    </div>
    <div class="stat-card">
      <?php
      $models_count = $pdo->query("SELECT COUNT(DISTINCT CONCAT(brand, '-', model)) FROM airbag_ecus")->fetchColumn();
      ?>
      <div class="stat-number"><?= $models_count ?></div>
      <div class="stat-label">عدد الموديلات</div>
    </div>
    <div class="stat-card">
      <?php
      $with_images = $pdo->query("SELECT COUNT(DISTINCT CONCAT(ei.brand, '-', ei.model, '-', ei.ecu_number)) FROM ecu_images ei")->fetchColumn();
      ?>
      <div class="stat-number"><?= $with_images ?></div>
      <div class="stat-label">لديها صور</div>
    </div>
  </div>

  <!-- شريط الأدوات -->
  <div class="action-bar">
    <div>
      <button class="btn btn-success" onclick="openAddModal()">
        ➕ إضافة كمبيوتر جديد
      </button>
      <form method="POST" style="display: inline;">
        <input type="hidden" name="action" value="export_excel">
        <button type="submit" class="btn btn-primary">
          📥 تصدير إلى Excel
        </button>
      </form>
    </div>

    <!-- نموذج البحث -->
    <form method="GET" class="search-form">
      <input type="text" name="search_brand" value="<?= htmlspecialchars($search_brand) ?>" 
             placeholder="العلامة التجارية" class="search-input">
      <input type="text" name="search_model" value="<?= htmlspecialchars($search_model) ?>" 
             placeholder="الموديل" class="search-input">
      <input type="text" name="search_ecu" value="<?= htmlspecialchars($search_ecu) ?>" 
             placeholder="رقم الكمبيوتر" class="search-input">
      <button type="submit" class="btn btn-primary">🔍 بحث</button>
      <a href="admin_airbag_ecus.php" class="btn btn-warning">🔄 إعادة تعيين</a>
    </form>
  </div>

  <!-- جدول البيانات -->
  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th>الإجراءات</th>
          <th>الصور</th>
          <th>نوع EEPROM</th>
          <th>رقم الكمبيوتر</th>
          <th>الموديل</th>
          <th>العلامة التجارية</th>
          <th>تاريخ الإنشاء</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ecus as $ecu): ?>
          <tr>
            <td>
              <button class="btn btn-warning btn-small" onclick="openEditModal(<?= htmlspecialchars(json_encode($ecu)) ?>)">
                ✏️ تعديل
              </button>
              <form method="POST" style="display: inline;" 
                    onsubmit="return confirm('هل أنت متأكد من حذف هذا الكمبيوتر؟')">
                <input type="hidden" name="action" value="delete_ecu">
                <input type="hidden" name="id" value="<?= $ecu['id'] ?>">
                <button type="submit" class="btn btn-danger btn-small">
                  🗑️ حذف
                </button>
              </form>
            </td>
            <td>
              <?php if ($ecu['has_images'] > 0): ?>
                <span class="has-images">✅ متوفرة</span>
              <?php else: ?>
                <span>❌ غير متوفرة</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($ecu['eeprom_type']) ?></td>
            <td><?= htmlspecialchars($ecu['ecu_number']) ?></td>
            <td><?= htmlspecialchars($ecu['model']) ?></td>
            <td><?= htmlspecialchars($ecu['brand']) ?></td>
            <td><?= date('Y/m/d H:i', strtotime($ecu['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- ترقيم الصفحات -->
  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>&search_brand=<?= urlencode($search_brand) ?>&search_model=<?= urlencode($search_model) ?>&search_ecu=<?= urlencode($search_ecu) ?>">السابق</a>
      <?php endif; ?>

      <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
        <?php if ($i == $page): ?>
          <span class="current"><?= $i ?></span>
        <?php else: ?>
          <a href="?page=<?= $i ?>&search_brand=<?= urlencode($search_brand) ?>&search_model=<?= urlencode($search_model) ?>&search_ecu=<?= urlencode($search_ecu) ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page + 1 ?>&search_brand=<?= urlencode($search_brand) ?>&search_model=<?= urlencode($search_model) ?>&search_ecu=<?= urlencode($search_ecu) ?>">التالي</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- زر العودة -->
  <a href="home.php" class="back-link">
    ↩️ العودة إلى الصفحة الرئيسية
  </a>
</div>

<!-- مودال إضافة كمبيوتر جديد -->
<div id="addModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeAddModal()">&times;</span>
    <h2>إضافة كمبيوتر جديد</h2>
    <form method="POST" class="modal-form">
      <input type="hidden" name="action" value="add_ecu">
      
      <div class="form-group">
        <label for="add_brand">العلامة التجارية</label>
        <input type="text" name="brand" id="add_brand" class="form-control" required>
      </div>

      <div class="form-group">
        <label for="add_model">الموديل</label>
        <input type="text" name="model" id="add_model" class="form-control" required>
      </div>

      <div class="form-group">
        <label for="add_ecu_number">رقم الكمبيوتر</label>
        <input type="text" name="ecu_number" id="add_ecu_number" class="form-control" required>
      </div>

      <div class="form-group">
        <label for="add_eeprom_type">نوع EEPROM (اختياري)</label>
        <input type="text" name="eeprom_type" id="add_eeprom_type" class="form-control">
      </div>

      <button type="submit" class="btn btn-success">✅ إضافة</button>
      <button type="button" class="btn btn-danger" onclick="closeAddModal()">❌ إلغاء</button>
    </form>
  </div>
</div>

<!-- مودال تعديل كمبيوتر -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeEditModal()">&times;</span>
    <h2>تعديل بيانات الكمبيوتر</h2>
    <form method="POST" class="modal-form">
      <input type="hidden" name="action" value="edit_ecu">
      <input type="hidden" name="id" id="edit_id">
      
      <div class="form-group">
        <label for="edit_brand">العلامة التجارية</label>
        <input type="text" name="brand" id="edit_brand" class="form-control" required>
      </div>

      <div class="form-group">
        <label for="edit_model">الموديل</label>
        <input type="text" name="model" id="edit_model" class="form-control" required>
      </div>

      <div class="form-group">
        <label for="edit_ecu_number">رقم الكمبيوتر</label>
        <input type="text" name="ecu_number" id="edit_ecu_number" class="form-control" required>
      </div>

      <div class="form-group">
        <label for="edit_eeprom_type">نوع EEPROM (اختياري)</label>
        <input type="text" name="eeprom_type" id="edit_eeprom_type" class="form-control">
      </div>

      <button type="submit" class="btn btn-success">✅ حفظ التغييرات</button>
      <button type="button" class="btn btn-danger" onclick="closeEditModal()">❌ إلغاء</button>
    </form>
  </div>
</div>

<script>
function openAddModal() {
  document.getElementById('addModal').style.display = 'block';
}

function closeAddModal() {
  document.getElementById('addModal').style.display = 'none';
}

function openEditModal(ecu) {
  document.getElementById('edit_id').value = ecu.id;
  document.getElementById('edit_brand').value = ecu.brand;
  document.getElementById('edit_model').value = ecu.model;
  document.getElementById('edit_ecu_number').value = ecu.ecu_number;
  document.getElementById('edit_eeprom_type').value = ecu.eeprom_type || '';
  document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
}

// إغلاق المودال عند النقر خارجه
window.onclick = function(event) {
  const addModal = document.getElementById('addModal');
  const editModal = document.getElementById('editModal');
  
  if (event.target === addModal) {
    closeAddModal();
  }
  if (event.target === editModal) {
    closeEditModal();
  }
}
</script>

<?php
$page_content = ob_get_clean();

// إدراج القالب
include __DIR__ . '/includes/layout.php';
?>