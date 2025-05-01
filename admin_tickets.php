<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// وظيفة لتنظيف المدخلات
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// التحقق من صلاحية الأدمن
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'admin') {
    // تسجيل محاولة الوصول غير المصرح به
    error_log("محاولة وصول غير مصرح به إلى صفحة الإدارة: " . ($_SESSION['email'] ?? 'غير مسجل دخول') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    
    $_SESSION['error_message'] = "غير مسموح لك بالوصول إلى هذه الصفحة.";
    header("Location: login.php");
    exit;
}

// إنشاء رمز CSRF إذا لم يكن موجودًا
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// وظيفة للتحقق من الإدخال العددي
function validate_int($id) {
    $id = filter_var($id, FILTER_VALIDATE_INT);
    return ($id !== false && $id > 0) ? $id : false;
}

// تهيئة متغير للرسائل
$messages = [];

try {
    // التحقق من تنفيذ mark_seen
    if (isset($_GET['mark_seen'])) {
        // التحقق من CSRF token
        if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf'])) {
            throw new Exception("🚫 طلب غير صالح (رمز الحماية CSRF مفقود أو غير مطابق).");
        }

        $id = validate_int($_GET['mark_seen']);
        if (!$id) {
            throw new Exception("معرف التذكرة غير صالح.");
        }

        // التحقق من وجود التذكرة قبل التحديث
        $check_stmt = $pdo->prepare("SELECT id FROM tickets WHERE id = :id");
        $check_stmt->execute(['id' => $id]);
        if (!$check_stmt->fetch()) {
            throw new Exception("التذكرة غير موجودة.");
        }

        $stmt = $pdo->prepare("UPDATE tickets SET is_seen = TRUE, updated_at = NOW(), updated_by = :admin WHERE id = :id");
        $stmt->execute([
            'id' => $id, 
            'admin' => $_SESSION['username'] ?? 'admin'
        ]);
        
        $_SESSION['success_message'] = "تم تحديث حالة التذكرة إلى 'تمت المراجعة'.";
        header("Location: admin_tickets.php");
        exit;
    }

    // التحقق من تنفيذ cancel_ticket
    if (isset($_GET['cancel_ticket'])) {
        // التحقق من CSRF token
        if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf'])) {
            throw new Exception("🚫 لا يمكن تنفيذ العملية. رمز الحماية CSRF غير صحيح.");
        }

        $id = validate_int($_GET['cancel_ticket']);
        if (!$id) {
            throw new Exception("معرف التذكرة غير صالح.");
        }

        // التحقق من وجود التذكرة قبل التحديث
        $check_stmt = $pdo->prepare("SELECT id FROM tickets WHERE id = :id");
        $check_stmt->execute(['id' => $id]);
        if (!$check_stmt->fetch()) {
            throw new Exception("التذكرة غير موجودة.");
        }

        $stmt = $pdo->prepare("UPDATE tickets SET status = 'cancelled', updated_at = NOW(), updated_by = :admin WHERE id = :id");
        $stmt->execute([
            'id' => $id, 
            'admin' => $_SESSION['username'] ?? 'admin'
        ]);
        
        $_SESSION['success_message'] = "تم إلغاء التذكرة بنجاح.";
        header("Location: admin_tickets.php");
        exit;
    }

    // استرجاع رسائل النجاح/الخطأ من الجلسة
    if (isset($_SESSION['success_message'])) {
        $messages['success'] = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
    }

    if (isset($_SESSION['error_message'])) {
        $messages['error'] = $_SESSION['error_message'];
        unset($_SESSION['error_message']);
    }

    // جلب التذاكر (مع إضافة حماية من حقن SQL)
    $stmt = $pdo->prepare("
        SELECT t.*, u.email as user_email 
        FROM tickets t 
        LEFT JOIN users u ON t.username = u.username 
        ORDER BY 
            CASE 
                WHEN t.is_seen IS NULL OR t.is_seen = FALSE THEN 0 
                ELSE 1 
            END, 
            t.created_at DESC
    ");
    $stmt->execute();
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // إحصائيات التذاكر
    $total = count($tickets);
    $seen = count(array_filter($tickets, fn($t) => isset($t['is_seen']) && $t['is_seen']));
    $pending = $total - $seen;
    $cancelled = count(array_filter($tickets, fn($t) => isset($t['status']) && $t['status'] === 'cancelled'));

} catch (Exception $e) {
    // تسجيل الخطأ وعرضه للمستخدم
    error_log("Admin Tickets Error: " . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: admin_tickets.php");
    exit;
}

$page_title = "إدارة التذاكر";
$page_css = <<<CSS
.container {
  background: rgba(15, 23, 42, 0.8);
  border-radius: 10px;
  padding: 25px;
  margin-bottom: 30px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}
.alert {
  padding: 12px 15px;
  border-radius: 8px;
  margin-bottom: 20px;
  color: #fff;
  display: flex;
  align-items: center;
  gap: 10px;
}
.alert i {
  font-size: 1.2rem;
}
.alert-success {
  background: rgba(0, 200, 83, 0.2);
  border: 1px solid rgba(0, 200, 83, 0.3);
}
.alert-danger {
  background: rgba(255, 107, 107, 0.2);
  border: 1px solid rgba(255, 107, 107, 0.3);
}
.ticket-stats {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-around;
  background: rgba(30, 41, 59, 0.7);
  padding: 15px;
  border-radius: 8px;
  margin: 20px 0;
  font-size: 16px;
}
.stat-item {
  padding: 8px 15px;
  text-align: center;
  margin: 5px;
  border-radius: 6px;
  transition: all 0.3s ease;
}
.stat-item:hover {
  background: rgba(59, 130, 246, 0.1);
  transform: translateY(-2px);
}
.stat-value {
  font-size: 1.3rem;
  font-weight: bold;
  color: #00d9ff;
  display: block;
  margin-top: 5px;
}
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 25px;
  background: rgba(15, 23, 42, 0.5);
  border-radius: 8px;
  overflow: hidden;
}
thead tr {
  background-color: #1e293b;
  color: #f8fafc;
  text-align: right;
}
th, td {
  padding: 12px 15px;
  text-align: right;
}
tbody tr {
  border-bottom: 1px solid #3b3b3b;
  transition: background-color 0.3s;
}
tbody tr:hover {
  background-color: rgba(59, 130, 246, 0.1);
}
.ticket-new {
  background-color: rgba(255, 193, 7, 0.05);
}
.ticket-seen {
  background-color: rgba(0, 255, 136, 0.05);
}
.ticket-cancelled {
  background-color: rgba(255, 107, 107, 0.05);
}
.action-btn {
  display: inline-block;
  padding: 6px 12px;
  margin: 3px;
  border-radius: 5px;
  text-decoration: none;
  color: white;
  background: #1e90ff;
  cursor: pointer;
  border: none;
  font-size: 14px;
  transition: all 0.2s;
}
.action-btn:hover {
  background: #0078e7;
  transform: translateY(-2px);
}
.btn-danger {
  background: #ff6b6b;
}
.btn-danger:hover {
  background: #e74c3c;
}
.btn-disabled {
  background: #64748b;
  cursor: not-allowed;
}
.ticket-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}
.ticket-id {
  font-weight: bold;
  color: #00d9ff;
}
.status-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 10px;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: bold;
}
.status-pending {
  background-color: rgba(255, 193, 7, 0.1);
  color: #ffc107;
  border: 1px solid rgba(255, 193, 7, 0.3);
}
.status-reviewed {
  background-color: rgba(0, 255, 136, 0.1);
  color: #00ff88;
  border: 1px solid rgba(0, 255, 136, 0.3);
}
.status-cancelled {
  background-color: rgba(255, 107, 107, 0.1);
  color: #ff6b6b;
  border: 1px solid rgba(255, 107, 107, 0.3);
}
.search-bar {
  display: flex;
  margin: 15px 0;
  gap: 10px;
}
.search-input {
  flex: 1;
  padding: 10px 15px;
  background: rgba(30, 41, 59, 0.7);
  border: 1px solid rgba(66, 135, 245, 0.2);
  border-radius: 30px;
  color: #fff;
  font-size: 16px;
}
.search-input:focus {
  outline: none;
  border-color: #00d9ff;
  box-shadow: 0 0 0 2px rgba(0, 217, 255, 0.3);
}
.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin: 15px 0;
}
.filter-btn {
  padding: 8px 15px;
  border-radius: 20px;
  border: 1px solid rgba(66, 135, 245, 0.3);
  background: transparent;
  color: #cbd5e1;
  cursor: pointer;
  transition: all 0.3s ease;
}
.filter-btn:hover, .filter-btn.active {
  background: rgba(0, 217, 255, 0.1);
  color: #00d9ff;
  border-color: #00d9ff;
}
.timestamp {
  font-size: 0.85rem;
  color: #64748b;
  display: block;
  margin-top: 3px;
}
CSS;

$page_js = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    // وظيفة البحث في الجدول
    const searchInput = document.getElementById('searchTable');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            // تحديث العداد
            updateVisibleCount();
        });
    }
    
    // وظيفة تصفية الجدول
    const filterButtons = document.querySelectorAll('.filter-btn');
    if (filterButtons.length) {
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // إزالة الفلتر النشط السابق
                document.querySelector('.filter-btn.active')?.classList.remove('active');
                
                // تنشيط الفلتر الحالي
                this.classList.add('active');
                
                const filterValue = this.dataset.filter;
                const rows = document.querySelectorAll('table tbody tr');
                
                rows.forEach(row => {
                    if (filterValue === 'all') {
                        row.style.display = '';
                    } else if (filterValue === 'pending' && row.classList.contains('ticket-new')) {
                        row.style.display = '';
                    } else if (filterValue === 'reviewed' && row.classList.contains('ticket-seen')) {
                        row.style.display = '';
                    } else if (filterValue === 'cancelled' && row.classList.contains('ticket-cancelled')) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // تحديث العداد
                updateVisibleCount();
            });
        });
    }
    
    // تحديث عداد التذاكر المرئية
    function updateVisibleCount() {
        const visibleRows = document.querySelectorAll('table tbody tr[style=""]').length;
        const totalCount = document.querySelector('#totalCount');
        if (totalCount) {
            totalCount.textContent = visibleRows;
        }
    }
    
    // تأكيد حذف التذكرة
    const cancelButtons = document.querySelectorAll('.btn-danger');
    cancelButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('هل أنت متأكد من إلغاء هذه التذكرة؟')) {
                e.preventDefault();
            }
        });
    });
    
    // إخفاء رسائل التنبيه تلقائياً بعد 5 ثوانٍ
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length) {
        setTimeout(() => {
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
    }
});
JS;

ob_start();
?>
<div class="container">
  <?php if (isset($messages['success'])): ?>
    <div class="alert alert-success">
      <i class="fas fa-check-circle"></i> <?= sanitize_input($messages['success']) ?>
    </div>
  <?php endif; ?>
  
  <?php if (isset($messages['error'])): ?>
    <div class="alert alert-danger">
      <i class="fas fa-exclamation-circle"></i> <?= sanitize_input($messages['error']) ?>
    </div>
  <?php endif; ?>

  <div class="ticket-stats">
    <div class="stat-item">
      <span>إجمالي التذاكر</span>
      <span class="stat-value" id="totalCount"><?= $total ?></span>
    </div>
    <div class="stat-item">
      <span>تمت المراجعة</span>
      <span class="stat-value"><?= $seen ?></span>
    </div>
    <div class="stat-item">
      <span>بانتظار المراجعة</span>
      <span class="stat-value"><?= $pending ?></span>
    </div>
    <div class="stat-item">
      <span>ملغية</span>
      <span class="stat-value"><?= $cancelled ?></span>
    </div>
  </div>
  
  <div class="search-bar">
    <input type="text" id="searchTable" class="search-input" placeholder="بحث عن تذكرة...">
  </div>
  
  <div class="filters">
    <button class="filter-btn active" data-filter="all">جميع التذاكر</button>
    <button class="filter-btn" data-filter="pending">قيد الانتظار</button>
    <button class="filter-btn" data-filter="reviewed">تمت المراجعة</button>
    <button class="filter-btn" data-filter="cancelled">ملغية</button>
  </div>

  <table>
    <thead>
      <tr>
        <th>رقم</th>
        <th>العميل</th>
        <th>معلومات التواصل</th>
        <th>السيارة</th>
        <th>الشاصي</th>
        <th>الخدمة</th>
        <th>الحالة</th>
        <th>الإجراء</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($tickets)): ?>
      <tr>
        <td colspan="8" style="text-align:center; padding:30px;">لا توجد تذاكر حالياً</td>
      </tr>
    <?php else: ?>
      <?php foreach ($tickets as $row): 
        $rowClass = '';
        if (isset($row['status']) && $row['status'] === 'cancelled') {
            $rowClass = 'ticket-cancelled';
        } elseif (isset($row['is_seen']) && $row['is_seen']) {
            $rowClass = 'ticket-seen';
        } else {
            $rowClass = 'ticket-new';
        }
      ?>
        <tr class="<?= $rowClass ?>">
          <td>
            <span class="ticket-id">FLEX-<?= str_pad(htmlspecialchars($row['id']), 5, '0', STR_PAD_LEFT) ?></span>
            <span class="timestamp"><?= date('Y/m/d H:i', strtotime($row['created_at'])) ?></span>
          </td>
          <td><?= htmlspecialchars($row['username']) ?></td>
          <td>
            <?= htmlspecialchars($row['phone']) ?>
            <?php if (!empty($row['user_email'])): ?>
              <span class="timestamp"><?= htmlspecialchars($row['user_email']) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?= htmlspecialchars($row['car_type']) ?>
            <?php if (!empty($row['year'])): ?>
              <span class="timestamp"><?= htmlspecialchars($row['year']) ?></span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($row['chassis']) ?></td>
          <td><?= htmlspecialchars($row['service_type']) ?></td>
          <td>
            <?php if (isset($row['status']) && $row['status'] === 'cancelled'): ?>
              <span class="status-tag status-cancelled"><i class="fas fa-ban"></i> ملغية</span>
            <?php elseif (isset($row['is_seen']) && $row['is_seen']): ?>
              <span class="status-tag status-reviewed"><i class="fas fa-check-circle"></i> تمت المراجعة</span>
            <?php else: ?>
              <span class="status-tag status-pending"><i class="fas fa-clock"></i> قيد الانتظار</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="ticket-actions">
              <?php if ((!isset($row['is_seen']) || !$row['is_seen']) && (!isset($row['status']) || $row['status'] !== 'cancelled')): ?>
                <a href="?mark_seen=<?= $row['id'] ?>&csrf=<?= $csrf_token ?>" class="action-btn">
                  <i class="fas fa-check"></i> مراجعة
                </a>
              <?php elseif ((!isset($row['status']) || $row['status'] !== 'cancelled')): ?>
                <button class="action-btn btn-disabled">
                  <i class="fas fa-check-circle"></i> تم
                </button>
              <?php endif; ?>

              <?php if (!isset($row['status']) || $row['status'] !== 'cancelled'): ?>
                <a href="?cancel_ticket=<?= $row['id'] ?>&csrf=<?= $csrf_token ?>" class="action-btn btn-danger">
                  <i class="fas fa-ban"></i> إلغاء
                </a>
              <?php else: ?>
                <button class="action-btn btn-disabled">
                  <i class="fas fa-ban"></i> ملغية
                </button>
              <?php endif; ?>
              
              <a href="view_ticket.php?id=<?= $row['id'] ?>&csrf=<?= $csrf_token ?>" class="action-btn">
                <i class="fas fa-eye"></i> عرض
              </a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php
$page_content = ob_get_clean();
include __DIR__ . '/includes/layout.php';
?>