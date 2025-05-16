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
$page_title = 'إدارة طلبات الإيرباق';
$display_title = 'إدارة طلبات مسح بيانات الحوادث';

$message = '';
$error = '';

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update_status') {
            $request_id = (int)$_POST['request_id'];
            $status = $_POST['status'];
            $notes = trim($_POST['notes']);
            
            $stmt = $pdo->prepare("
                UPDATE airbag_requests 
                SET status = ?, notes = ?, processed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $notes, $request_id]);
            
            // إرسال إشعار للعميل (إذا كان جدول notifications موجوداً)
            $request_details = $pdo->prepare("SELECT * FROM airbag_requests WHERE id = ?");
            $request_details->execute([$request_id]);
            $request = $request_details->fetch(PDO::FETCH_ASSOC);
            
            if ($request) {
                // التحقق من وجود جدول notifications قبل الإدراج
                try {
                    $notification_stmt = $pdo->prepare("
                        INSERT INTO notifications (user_email, title, message, type) 
                        VALUES (?, ?, ?, ?)
                    ");
                    
                    $status_text = [
                        'pending' => 'قيد الانتظار',
                        'processing' => 'جاري المعالجة',
                        'completed' => 'مكتمل',
                        'failed' => 'فشل'
                    ];
                    
                    $notification_stmt->execute([
                        $request['user_email'],
                        'تحديث حالة طلب الإيرباق',
                        "تم تحديث حالة طلبك رقم #{$request_id} إلى: {$status_text[$status]}",
                        'airbag_update'
                    ]);
                } catch (PDOException $e) {
                    // تجاهل خطأ notifications إذا لم يكن الجدول موجوداً
                    error_log("Notification error: " . $e->getMessage());
                }
            }
            
            $message = 'تم تحديث حالة الطلب بنجاح';
        }
        
        if ($_POST['action'] === 'delete_request') {
            $request_id = (int)$_POST['request_id'];
            
            // جلب معلومات الطلب لحذف الملف
            $stmt = $pdo->prepare("SELECT file_path FROM airbag_requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $file_path = $stmt->fetchColumn();
            
            // حذف الطلب
            $stmt = $pdo->prepare("DELETE FROM airbag_requests WHERE id = ?");
            $stmt->execute([$request_id]);
            
            // حذف الملف المرفق
            if ($file_path && file_exists($file_path)) {
                unlink($file_path);
            }
            
            $message = 'تم حذف الطلب بنجاح';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// جلب الطلبات مع الفلترة
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$where_conditions = [];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "ar.status = ?";  // مهم: إضافة ar. prefix
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// عدد الطلبات
$count_sql = "SELECT COUNT(*) FROM airbag_requests ar $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_requests = $stmt->fetchColumn();

// جلب الطلبات - مع تحسين الـ SQL
$sql = "
    SELECT ar.id, ar.user_email, ar.username, ar.brand, ar.model, ar.ecu_number, 
           ar.original_filename, ar.file_path, ar.file_size, ar.status, 
           ar.is_manual, ar.notes, ar.created_at, ar.processed_at,
           ae.eeprom_type
    FROM airbag_requests ar
    LEFT JOIN airbag_ecus ae ON (ar.brand = ae.brand AND ar.model = ae.model AND ar.ecu_number = ae.ecu_number)
    $where_clause 
    ORDER BY ar.created_at DESC 
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_pages = ceil($total_requests / $per_page);

// إحصائيات
$stats = [
    'pending' => $pdo->query("SELECT COUNT(*) FROM airbag_requests WHERE status = 'pending'")->fetchColumn(),
    'processing' => $pdo->query("SELECT COUNT(*) FROM airbag_requests WHERE status = 'processing'")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM airbag_requests WHERE status = 'completed'")->fetchColumn(),
    'failed' => $pdo->query("SELECT COUNT(*) FROM airbag_requests WHERE status = 'failed'")->fetchColumn(),
];

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
  backdrop-filter: blur(5px);
}

.stat-card.pending { border-left: 4px solid #ffc107; }
.stat-card.processing { border-left: 4px solid #17a2b8; }
.stat-card.completed { border-left: 4px solid #28a745; }
.stat-card.failed { border-left: 4px solid #dc3545; }

.stat-number {
  font-size: 2em;
  font-weight: bold;
  color: #00d4ff;
}

.stat-label {
  color: #a8d8ff;
  margin-top: 5px;
}

.filter-bar {
  display: flex;
  justify-content: center;
  gap: 10px;
  margin: 20px 0;
  flex-wrap: wrap;
}

.filter-btn {
  padding: 8px 16px;
  border: 1px solid rgba(66, 135, 245, 0.3);
  border-radius: 6px;
  background: rgba(255, 255, 255, 0.1);
  color: white;
  text-decoration: none;
  transition: all 0.3s ease;
}

.filter-btn:hover, .filter-btn.active {
  background: linear-gradient(145deg, #1e90ff, #0070cc);
  border-color: #00d4ff;
}

.table-container {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 10px;
  overflow: hidden;
  margin: 20px 0;
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 800px;
}

.data-table th,
.data-table td {
  padding: 12px;
  text-align: right;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  vertical-align: top;
}

.data-table th {
  background: rgba(0, 0, 0, 0.3);
  color: #00d4ff;
  font-weight: bold;
  white-space: nowrap;
}

.data-table td {
  color: #a8d8ff;
}

.data-table tr:hover {
  background: rgba(255, 255, 255, 0.05);
}

.status-badge {
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: bold;
  text-align: center;
  display: inline-block;
  min-width: 80px;
  white-space: nowrap;
}

.status-pending { background: #ffc107; color: #000; }
.status-processing { background: #17a2b8; color: white; }
.status-completed { background: #28a745; color: white; }
.status-failed { background: #dc3545; color: white; }

.btn {
  padding: 6px 12px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: bold;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-block;
  font-size: 12px;
  margin: 2px;
  white-space: nowrap;
}

.btn-primary {
  background: linear-gradient(145deg, #1e90ff, #0070cc);
  color: white;
}

.btn-primary:hover {
  background: linear-gradient(145deg, #2eaaff, #0088ff);
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

.btn-download {
  background: linear-gradient(145deg, #6f42c1, #5a3299);
  color: white;
}

.btn-download:hover {
  background: linear-gradient(145deg, #8a5bd6, #6f42c1);
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
  background: rgba(0, 0, 0, 0.95);
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

.request-details {
  background: rgba(255, 255, 255, 0.05);
  padding: 15px;
  border-radius: 8px;
  margin: 10px 0;
  text-align: right;
  direction: rtl;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  margin: 8px 0;
  padding: 5px 0;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.detail-label {
  font-weight: bold;
  color: #00d4ff;
}

.detail-value {
  color: #a8d8ff;
}

.pagination {
  display: flex;
  justify-content: center;
  gap: 10px;
  margin: 20px 0;
  flex-wrap: wrap;
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

/* Responsive Design */
@media (max-width: 768px) {
  .container {
    width: 98%;
    padding: 20px;
  }
  
  .stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  }
  
  .filter-bar {
    justify-content: center;
  }
  
  .btn {
    font-size: 11px;
    padding: 5px 8px;
  }
}
CSS;

// تعريف محتوى الصفحة
ob_start();
?>
<div class="container">
  <h1>🎫 إدارة طلبات الإيرباق</h1>

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
    <div class="stat-card pending">
      <div class="stat-number"><?= $stats['pending'] ?></div>
      <div class="stat-label">قيد الانتظار</div>
    </div>
    <div class="stat-card processing">
      <div class="stat-number"><?= $stats['processing'] ?></div>
      <div class="stat-label">جاري المعالجة</div>
    </div>
    <div class="stat-card completed">
      <div class="stat-number"><?= $stats['completed'] ?></div>
      <div class="stat-label">مكتملة</div>
    </div>
    <div class="stat-card failed">
      <div class="stat-number"><?= $stats['failed'] ?></div>
      <div class="stat-label">فاشلة</div>
    </div>
  </div>

  <!-- فلتر الحالة -->
  <div class="filter-bar">
    <a href="admin_airbag_requests.php" class="filter-btn <?= empty($status_filter) ? 'active' : '' ?>">
      الكل
    </a>
    <a href="?status=pending" class="filter-btn <?= $status_filter === 'pending' ? 'active' : '' ?>">
      قيد الانتظار
    </a>
    <a href="?status=processing" class="filter-btn <?= $status_filter === 'processing' ? 'active' : '' ?>">
      جاري المعالجة
    </a>
    <a href="?status=completed" class="filter-btn <?= $status_filter === 'completed' ? 'active' : '' ?>">
      مكتملة
    </a>
    <a href="?status=failed" class="filter-btn <?= $status_filter === 'failed' ? 'active' : '' ?>">
      فاشلة
    </a>
  </div>

  <!-- جدول الطلبات -->
  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th>الإجراءات</th>
          <th>الحالة</th>
          <th>الملاحظات</th>
          <th>حجم الملف</th>
          <th>رقم الكمبيوتر</th>
          <th>الموديل</th>
          <th>العلامة</th>
          <th>العميل</th>
          <th>تاريخ الطلب</th>
          <th>رقم الطلب</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($requests)): ?>
          <tr>
            <td colspan="10" style="text-align: center; color: #a8d8ff; padding: 30px;">
              لا توجد طلبات حالياً
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($requests as $request): ?>
            <tr>
              <td>
                <button class="btn btn-primary" onclick="openUpdateModal(<?= htmlspecialchars(json_encode($request)) ?>)">
                  ✏️ تحديث
                </button>
                <a href="<?= htmlspecialchars($request['file_path']) ?>" class="btn btn-download" download>
                  📥 تحميل
                </a>
                <form method="POST" style="display: inline;" 
                      onsubmit="return confirm('هل أنت متأكد من حذف هذا الطلب؟')">
                  <input type="hidden" name="action" value="delete_request">
                  <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                  <button type="submit" class="btn btn-danger">
                    🗑️ حذف
                  </button>
                </form>
              </td>
              <td>
                <span class="status-badge status-<?= $request['status'] ?>">
                  <?php
                  $status_text = [
                      'pending' => 'قيد الانتظار',
                      'processing' => 'جاري المعالجة',
                      'completed' => 'مكتمل',
                      'failed' => 'فشل'
                  ];
                  echo $status_text[$request['status']] ?? $request['status'];
                  ?>
                </span>
              </td>
              <td><?= htmlspecialchars(substr($request['notes'] ?? '', 0, 50)) ?><?= strlen($request['notes'] ?? '') > 50 ? '...' : '' ?></td>
              <td><?= round($request['file_size'] / 1024, 1) ?> KB</td>
              <td><?= htmlspecialchars($request['ecu_number']) ?></td>
              <td><?= htmlspecialchars($request['model']) ?></td>
              <td><?= htmlspecialchars($request['brand']) ?></td>
              <td><?= htmlspecialchars($request['username']) ?></td>
              <td><?= date('Y/m/d H:i', strtotime($request['created_at'])) ?></td>
              <td>#<?= $request['id'] ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ترقيم الصفحات -->
  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($status_filter) ?>">السابق</a>
      <?php endif; ?>

      <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
        <?php if ($i == $page): ?>
          <span class="current"><?= $i ?></span>
        <?php else: ?>
          <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($status_filter) ?>">التالي</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- زر العودة -->
  <a href="home.php" class="back-link">
    ↩️ العودة إلى الصفحة الرئيسية
  </a>
</div>

<!-- مودال تحديث الطلب -->
<div id="updateModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeUpdateModal()">&times;</span>
    <h2>تحديث طلب الإيرباق</h2>
    
    <div id="requestDetails" class="request-details"></div>
    
    <form method="POST" class="modal-form">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="request_id" id="update_request_id">
      
      <div class="form-group">
        <label for="update_status">الحالة</label>
        <select name="status" id="update_status" class="form-control" required>
          <option value="pending">قيد الانتظار</option>
          <option value="processing">جاري المعالجة</option>
          <option value="completed">مكتمل</option>
          <option value="failed">فشل</option>
        </select>
      </div>

      <div class="form-group">
        <label for="update_notes">الملاحظات</label>
        <textarea name="notes" id="update_notes" class="form-control" rows="4" 
                  placeholder="أدخل أي ملاحظات حول الطلب"></textarea>
      </div>

      <button type="submit" class="btn btn-success">✅ حفظ التحديث</button>
      <button type="button" class="btn btn-danger" onclick="closeUpdateModal()">❌ إلغاء</button>
    </form>
  </div>
</div>

<script>
function openUpdateModal(request) {
  document.getElementById('update_request_id').value = request.id;
  document.getElementById('update_status').value = request.status;
  document.getElementById('update_notes').value = request.notes || '';
  
  // عرض تفاصيل الطلب
  const details = document.getElementById('requestDetails');
  details.innerHTML = `
    <h3>تفاصيل الطلب</h3>
    <div class="detail-row">
      <span class="detail-label">رقم الطلب:</span>
      <span class="detail-value">#${request.id}</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">العميل:</span>
      <span class="detail-value">${request.username}</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">العلامة التجارية:</span>
      <span class="detail-value">${request.brand}</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">الموديل:</span>
      <span class="detail-value">${request.model}</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">رقم الكمبيوتر:</span>
      <span class="detail-value">${request.ecu_number}</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">اسم الملف:</span>
      <span class="detail-value">${request.original_filename}</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">حجم الملف:</span>
      <span class="detail-value">${(request.file_size / 1024).toFixed(1)} KB</span>
    </div>
  `;
  
  document.getElementById('updateModal').style.display = 'block';
}

function closeUpdateModal() {
  document.getElementById('updateModal').style.display = 'none';
}

// إغلاق المودال عند النقر خارجه
window.onclick = function(event) {
  const modal = document.getElementById('updateModal');
  if (event.target === modal) {
    closeUpdateModal();
  }
}
</script>

<?php
$page_content = ob_get_clean();

// إدراج القالب
include __DIR__ . '/includes/layout.php';
?>