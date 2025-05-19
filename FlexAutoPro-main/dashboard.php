<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// التحقق من تسجيل الدخول وصلاحيات المستخدم
if (!isset($_SESSION['email']) || !in_array($_SESSION['user_role'] ?? '', ['admin', 'staff'])) {
    header("Location: index.php");
    exit;
}

$username = $_SESSION['username'];
$user_type = $_SESSION['user_role'] ?? '';
$email = $_SESSION['email'] ?? '';

// إعداد عنوان الصفحة
$page_title = 'لوحة تنفيذ المهام';
$display_title = 'لوحة تنفيذ المهام';

// الحصول على قائمة التذاكر النشطة
$tickets = [];
try {
    // استعلام لجلب التذاكر حسب دور المستخدم
    $query = "
        SELECT t.*, u.username as client_name 
        FROM tickets t 
        JOIN users u ON t.user_email = u.email 
        WHERE t.status IN ('open', 'in_progress', 'pending')
    ";
    
    // إذا كان الموظف، اعرض فقط التذاكر المخصصة له
    if ($user_type === 'staff') {
        $query .= " AND (t.assigned_to = ? OR t.assigned_to IS NULL)";
        $params = [$email];
    } else {
        $params = [];
    }
    
    $query .= " ORDER BY 
        CASE 
            WHEN t.status = 'open' THEN 1
            WHEN t.status = 'in_progress' THEN 2
            ELSE 3
        END,
        t.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();
    
    // إضافة معلومات الوثائق لكل مستخدم
    foreach ($tickets as &$ticket) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_documents WHERE user_email = ? AND status = 'verified'");
        $stmt->execute([$ticket['user_email']]);
        $ticket['verified_docs'] = $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Error fetching tickets: " . $e->getMessage());
}

// الحصول على قائمة الموظفين (للمدير فقط)
$staff_list = [];
if ($user_type === 'admin') {
    try {
        $stmt = $pdo->prepare("SELECT email, username FROM users WHERE user_role = 'staff' AND is_active = TRUE ORDER BY username");
        $stmt->execute();
        $staff_list = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching staff list: " . $e->getMessage());
    }
}

// معالجة تحديث حالة التذكرة
if (isset($_POST['update_ticket_status'])) {
    $ticket_id = $_POST['ticket_id'] ?? 0;
    $new_status = $_POST['new_status'] ?? '';
    
    if (!empty($ticket_id) && !empty($new_status)) {
        try {
            // تحديث حالة التذكرة
            $stmt = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$new_status, $ticket_id]);
            
            // إضافة تعليق تلقائي حول التغيير
            $comment = "تم تغيير حالة التذكرة إلى: " . get_status_name($new_status);
            $stmt = $pdo->prepare("INSERT INTO ticket_comments (ticket_id, user_email, comment, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$ticket_id, $email, $comment]);
            
            // إضافة إشعار للمستخدم
            $stmt = $pdo->prepare("SELECT user_email, ticket_number FROM tickets WHERE id = ?");
            $stmt->execute([$ticket_id]);
            $ticket_info = $stmt->fetch();
            
            if ($ticket_info) {
                $notification_message = "تم تحديث حالة التذكرة #{$ticket_info['ticket_number']} إلى " . get_status_name($new_status);
                $stmt = $pdo->prepare("INSERT INTO notifications (user_email, message, is_read, created_at) VALUES (?, ?, 0, CURRENT_TIMESTAMP)");
                $stmt->execute([$ticket_info['user_email'], $notification_message]);
            }
            
            // تسجيل النشاط
            $log_message = "تم تحديث حالة التذكرة #{$ticket_info['ticket_number']} إلى " . get_status_name($new_status);
            $stmt = $pdo->prepare("INSERT INTO system_logs (user_email, action, details, created_at) VALUES (?, 'update_ticket_status', ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$email, $log_message]);
            
            $success_message = "تم تحديث حالة التذكرة بنجاح";
            
            // إعادة التوجيه لتحديث الصفحة
            header("Location: dashboard.php?success=" . urlencode($success_message));
            exit;
        } catch (PDOException $e) {
            error_log("Error updating ticket status: " . $e->getMessage());
            $error_message = "حدث خطأ أثناء تحديث حالة التذكرة";
        }
    }
}

// معالجة تعيين موظف للتذكرة (للمدير فقط)
if (isset($_POST['assign_ticket']) && $user_type === 'admin') {
    $ticket_id = $_POST['ticket_id'] ?? 0;
    $staff_email = $_POST['staff_email'] ?? '';
    
    if (!empty($ticket_id) && !empty($staff_email)) {
        try {
            // تحديث التذكرة بتعيين الموظف
            $stmt = $pdo->prepare("UPDATE tickets SET assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$staff_email, $ticket_id]);
            
            // الحصول على معلومات التذكرة والموظف
            $stmt = $pdo->prepare("SELECT t.ticket_number, t.user_email, u.username as staff_name FROM tickets t, users u WHERE t.id = ? AND u.email = ?");
            $stmt->execute([$ticket_id, $staff_email]);
            $info = $stmt->fetch();
            
            if ($info) {
                // إضافة تعليق تلقائي
                $comment = "تم تعيين الموظف {$info['staff_name']} للعمل على هذه التذكرة";
                $stmt = $pdo->prepare("INSERT INTO ticket_comments (ticket_id, user_email, comment, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$ticket_id, $email, $comment]);
                
                // إضافة إشعار للموظف
                $staff_notification = "تم تعيينك للعمل على التذكرة #{$info['ticket_number']}";
                $stmt = $pdo->prepare("INSERT INTO notifications (user_email, message, is_read, created_at) VALUES (?, ?, 0, CURRENT_TIMESTAMP)");
                $stmt->execute([$staff_email, $staff_notification]);
                
                // تسجيل النشاط
                $log_message = "تم تعيين الموظف {$info['staff_name']} للتذكرة #{$info['ticket_number']}";
                $stmt = $pdo->prepare("INSERT INTO system_logs (user_email, action, details, created_at) VALUES (?, 'assign_ticket', ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$email, $log_message]);
            }
            
            $success_message = "تم تعيين الموظف للتذكرة بنجاح";
            
            // إعادة التوجيه لتحديث الصفحة
            header("Location: dashboard.php?success=" . urlencode($success_message));
            exit;
        } catch (PDOException $e) {
            error_log("Error assigning ticket: " . $e->getMessage());
            $error_message = "حدث خطأ أثناء تعيين الموظف للتذكرة";
        }
    }
}

// وظيفة للحصول على اسم الحالة بالعربية
function get_status_name($status_code) {
    $status_names = [
        'open' => 'جديدة',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتملة',
        'cancelled' => 'ملغاة',
        'rejected' => 'مرفوضة',
        'pending' => 'معلقة'
    ];
    
    return $status_names[$status_code] ?? $status_code;
}

// وظيفة للحصول على اسم نوع الخدمة
function get_service_name($service_type) {
    $service_names = [
        'key_code' => 'طلب كود برمجة',
        'ecu_tuning' => 'تعديل برمجة ECU',
        'airbag_reset' => 'مسح بيانات Airbag',
        'remote_programming' => 'برمجة عن بُعد',
        'other' => 'خدمة أخرى'
    ];
    
    return $service_names[$service_type] ?? $service_type;
}

// استخراج رسالة النجاح من URL إذا وجدت
$success_message = $_GET['success'] ?? '';

// CSS مخصص للصفحة
$page_css = <<<CSS
.container {
  background: rgba(0, 0, 0, 0.7);
  padding: 35px;
  width: 90%;
  max-width: 1100px;
  border-radius: 16px;
  text-align: right;
  margin: 30px auto;
  box-shadow: 0 0 40px rgba(0, 200, 255, 0.15);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(66, 135, 245, 0.25);
}
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
  padding-bottom: 15px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.page-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 24px;
  color: #1e90ff;
}
.page-title i {
  font-size: 28px;
}
.search-box {
  display: flex;
  gap: 10px;
}
.search-box input {
  padding: 8px 12px;
  border-radius: 6px;
  border: 1px solid rgba(30, 144, 255, 0.3);
  background: rgba(0, 0, 0, 0.3);
  color: white;
  width: 250px;
}
.btn {
  padding: 8px 15px;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  transition: 0.3s;
  font-weight: bold;
}
.btn-primary {
  background: linear-gradient(145deg, #1e90ff, #0070cc);
  color: white;
}
.btn-primary:hover {
  background: linear-gradient(145deg, #2eaaff, #0088ff);
}
.btn-warning {
  background: linear-gradient(145deg, #ff9500, #cc7a00);
  color: white;
}
.btn-warning:hover {
  background: linear-gradient(145deg, #ffaa33, #e68a00);
}
.btn-success {
  background: linear-gradient(145deg, #34c759, #28a745);
  color: white;
}
.btn-success:hover {
  background: linear-gradient(145deg, #4cd964, #2dbc4e);
}
.btn-danger {
  background: linear-gradient(145deg, #ff3b30, #cc0000);
  color: white;
}
.btn-danger:hover {
  background: linear-gradient(145deg, #ff524a, #e60000);
}
.tickets-section {
  margin-bottom: 30px;
}
.section-title {
  color: #1e90ff;
  font-size: 18px;
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.section-title .count {
  background: rgba(30, 144, 255, 0.2);
  color: #1e90ff;
  font-size: 14px;
  padding: 2px 8px;
  border-radius: 50px;
}
.table-responsive {
  overflow-x: auto;
}
table {
  width: 100%;
  border-collapse: collapse;
  color: white;
}
table th, table td {
  padding: 12px 15px;
  text-align: right;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
table th {
  background: rgba(30, 144, 255, 0.2);
  color: #1e90ff;
  font-weight: bold;
}
table tr:hover {
  background: rgba(30, 144, 255, 0.1);
}
.badge {
  padding: 4px 10px;
  border-radius: 50px;
  font-size: 12px;
  display: inline-block;
}
.badge-success {
  background: rgba(52, 199, 89, 0.3);
  color: #34c759;
  border: 1px solid rgba(52, 199, 89, 0.3);
}
.badge-danger {
  background: rgba(255, 59, 48, 0.3);
  color: #ff3b30;
  border: 1px solid rgba(255, 59, 48, 0.3);
}
.badge-warning {
  background: rgba(255, 149, 0, 0.3);
  color: #ff9500;
  border: 1px solid rgba(255, 149, 0, 0.3);
}
.actions {
  display: flex;
  gap: 5px;
  flex-wrap: wrap;
}
.action-btn {
  padding: 6px 10px;
  border-radius: 4px;
  font-size: 12px;
  color: white;
  cursor: pointer;
  border: none;
  transition: 0.2s;
  white-space: nowrap;
}
.action-btn-view {
  background: #1e90ff;
}
.action-btn-view:hover {
  background: #0077ea;
}
.action-btn-status {
  background: #ff9500;
}
.action-btn-status:hover {
  background: #e68600;
}
.action-btn-assign {
  background: #34c759;
}
.action-btn-assign:hover {
  background: #28a745;
}
.warning-icon {
  color: #ff9500;
  font-size: 16px;
  margin-right: 5px;
}
.empty-message {
  text-align: center;
  padding: 25px;
  color: #8e8e93;
  font-style: italic;
}
.modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.7);
  z-index: 1000;
  align-items: center;
  justify-content: center;
}
.modal.active {
  display: flex;
}
.modal-content {
  background: rgba(20, 20, 40, 0.95);
  border-radius: 16px;
  width: 90%;
  max-width: 550px;
  max-height: 90vh;
  overflow-y: auto;
  padding: 25px;
  border: 1px solid rgba(66, 135, 245, 0.25);
  box-shadow: 0 0 40px rgba(0, 0, 0, 0.5);
}
.modal-title {
  color: #1e90ff;
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.form-group {
  margin-bottom: 15px;
}
.form-group label {
  display: block;
  margin-bottom: 5px;
  color: #a8d8ff;
}
.form-group select, .form-group input {
  width: 100%;
  padding: 10px;
  border-radius: 6px;
  border: 1px solid rgba(30, 144, 255, 0.3);
  background: rgba(0, 0, 0, 0.3);
  color: white;
}
.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 20px;
}
.close-btn {
  position: absolute;
  top: 15px;
  left: 20px;
  font-size: 24px;
  color: #8e8e93;
  cursor: pointer;
  border: none;
  background: none;
}
.close-btn:hover {
  color: #ff3b30;
}
.ticket-details {
  margin-bottom: 20px;
}
.detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 15px;
  margin-bottom: 20px;
}
.detail-item {
  background: rgba(0, 0, 0, 0.2);
  padding: 10px;
  border-radius: 6px;
}
.detail-label {
  color: #a8d8ff;
  font-size: 0.9em;
  margin-bottom: 5px;
}
.detail-value {
  font-weight: bold;
}
.description-section {
  margin-top: 20px;
}
.description-section h4 {
  color: #a8d8ff;
  margin-bottom: 10px;
}
.description-content {
  background: rgba(0, 0, 0, 0.2);
  padding: 15px;
  border-radius: 6px;
  margin-bottom: 20px;
  white-space: pre-line;
}
.alert {
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
  text-align: center;
}
.alert-success {
  background: rgba(52, 199, 89, 0.2);
  border: 1px solid rgba(52, 199, 89, 0.5);
  color: #34c759;
}
.alert-danger {
  background: rgba(255, 59, 48, 0.2);
  border: 1px solid rgba(255, 59, 48, 0.5);
  color: #ff3b30;
}
.filters {
  display: flex;
  gap: 15px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.filter-item {
  display: flex;
  align-items: center;
  gap: 8px;
}
.filter-item label {
  color: #a8d8ff;
}
.filter-item select {
  padding: 6px 10px;
  border-radius: 6px;
  border: 1px solid rgba(30, 144, 255, 0.3);
  background: rgba(0, 0, 0, 0.3);
  color: white;
}
.stats-row {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  margin-bottom: 20px;
}
.stat-card {
  background: rgba(30, 30, 50, 0.5);
  border-radius: 10px;
  padding: 15px;
  flex: 1;
  min-width: 150px;
  text-align: center;
  border: 1px solid rgba(66, 135, 245, 0.15);
}
.stat-number {
  font-size: 28px;
  font-weight: bold;
  margin-bottom: 5px;
}
.stat-open {
  color: #ff3b30;
}
.stat-progress {
  color: #ff9500;
}
.stat-completed {
  color: #34c759;
}
.stat-label {
  color: #a8d8ff;
  font-size: 14px;
}
@media (max-width: 768px) {
  .container {
    width: 95%;
    padding: 20px;
  }
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }
  .search-box {
    width: 100%;
  }
  .search-box input {
    width: 100%;
  }
  .detail-grid {
    grid-template-columns: 1fr;
  }
}
CSS;

// JavaScript للصفحة
$page_js = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    // تفعيل المرشحات (فلترة)
    const statusFilter = document.getElementById('status-filter');
    const serviceFilter = document.getElementById('service-filter');
    
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            filterTickets();
        });
    }
    
    if (serviceFilter) {
        serviceFilter.addEventListener('change', function() {
            filterTickets();
        });
    }
    
    function filterTickets() {
        const statusValue = statusFilter ? statusFilter.value : '';
        const serviceValue = serviceFilter ? serviceFilter.value : '';
        
        const rows = document.querySelectorAll('#tickets-table tbody tr');
        
        rows.forEach(row => {
            const statusCell = row.querySelector('[data-status]');
            const serviceCell = row.querySelector('[data-service]');
            
            const statusMatch = !statusValue || (statusCell && statusCell.getAttribute('data-status') === statusValue);
            const serviceMatch = !serviceValue || (serviceCell && serviceCell.getAttribute('data-service') === serviceValue);
            
            if (statusMatch && serviceMatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    // التعامل مع البحث
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#tickets-table tbody tr');
            
            rows.forEach(row => {
                const ticketNumber = row.querySelector('.ticket-number').textContent.toLowerCase();
                const clientName = row.querySelector('.client-name').textContent.toLowerCase();
                const vin = row.querySelector('.vin-number') ? row.querySelector('.vin-number').textContent.toLowerCase() : '';
                
                if (ticketNumber.includes(searchValue) || clientName.includes(searchValue) || vin.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // التعامل مع النوافذ المنبثقة
    const modals = document.querySelectorAll('.modal');
    const modalTriggers = document.querySelectorAll('[data-modal]');
    const closeButtons = document.querySelectorAll('.close-btn, .cancel-btn');
    
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                
                // تحديث معرف التذكرة في النماذج
                const ticketId = this.getAttribute('data-ticket-id');
                if (ticketId) {
                    const ticketIdInputs = modal.querySelectorAll('[name="ticket_id"]');
                    ticketIdInputs.forEach(input => input.value = ticketId);
                }
                
                // تحديث بيانات تفاصيل التذكرة إذا كان ذلك مطلوبًا
                if (this.hasAttribute('data-ticket-details')) {
                    const ticketNumber = this.getAttribute('data-ticket-number');
                    const ticketSubject = this.getAttribute('data-ticket-subject');
                    const ticketStatus = this.getAttribute('data-ticket-status');
                    const ticketService = this.getAttribute('data-ticket-service');
                    const ticketClient = this.getAttribute('data-ticket-client');
                    const ticketVin = this.getAttribute('data-ticket-vin');
                    const ticketCreated = this.getAttribute('data-ticket-created');
                    const ticketDescription = this.getAttribute('data-ticket-description');
                    
                    if (ticketNumber) document.getElementById('detail-ticket-number').textContent = ticketNumber;
                    if (ticketSubject) document.getElementById('detail-subject').textContent = ticketSubject;
                    if (ticketStatus) document.getElementById('detail-status').textContent = ticketStatus;
                    if (ticketService) document.getElementById('detail-service').textContent = ticketService;
                    if (ticketClient) document.getElementById('detail-client').textContent = ticketClient;
                    if (ticketVin) document.getElementById('detail-vin').textContent = ticketVin;
                    if (ticketCreated) document.getElementById('detail-created').textContent = ticketCreated;
                    if (ticketDescription) document.getElementById('detail-description').textContent = ticketDescription;
                }
            }
        });
    });
    
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.classList.remove('active');
            }
        });
    });
    
    // إغلاق النافذة عند النقر خارجها
    modals.forEach(modal => {
        modal.addEventListener('click', function(event) {
            if (event.target === this) {
                this.classList.remove('active');
            }
        });
    });
    
    // إغلاق التنبيهات تلقائيًا بعد 5 ثوانٍ
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        setTimeout(function() {
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
    }
});
JS;

// تعريف محتوى الصفحة
ob_start();
?>
<div class="container">
    <div class="page-header">
        <div class="page-title">
            <i>📋</i>
            <h1><?= $display_title ?></h1>
        </div>
        <div class="search-box">
            <input type="text" id="search-input" placeholder="بحث برقم التذكرة، اسم العميل، أو رقم الشاصي...">
            <button class="btn btn-primary">بحث</button>
        </div>
    </div>
    
    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($success_message) ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>
    
    <!-- إحصائيات سريعة -->
    <div class="stats-row">
        <?php
            $open_count = 0;
            $progress_count = 0;
            $completed_count = 0;
            
            foreach ($tickets as $ticket) {
                if ($ticket['status'] === 'open') $open_count++;
                if ($ticket['status'] === 'in_progress') $progress_count++;
                if ($ticket['status'] === 'completed') $completed_count++;
            }
        ?>
        <div class="stat-card">
            <div class="stat-number stat-open"><?= $open_count ?></div>
            <div class="stat-label">تذاكر جديدة</div>
        </div>
        <div class="stat-card">
            <div class="stat-number stat-progress"><?= $progress_count ?></div>
            <div class="stat-label">قيد التنفيذ</div>
        </div>
        <div class="stat-card">
            <div class="stat-number stat-completed"><?= $completed_count ?></div>
            <div class="stat-label">مكتملة</div>
        </div>
    </div>
    
    <!-- فلاتر البحث -->
    <div class="filters">
        <div class="filter-item">
            <label for="status-filter">الحالة:</label>
            <select id="status-filter">
                <option value="">الكل</option>
                <option value="open">جديدة</option>
                <option value="in_progress">قيد التنفيذ</option>
                <option value="pending">معلقة</option>
            </select>
        </div>
        <div class="filter-item">
            <label for="service-filter">نوع الخدمة:</label>
            <select id="service-filter">
                <option value="">الكل</option>
                <option value="key_code">طلب كود برمجة</option>
                <option value="ecu_tuning">تعديل برمجة ECU</option>
                <option value="airbag_reset">مسح بيانات Airbag</option>
                <option value="remote_programming">برمجة عن بُعد</option>
            </select>
        </div>
    </div>
    
    <!-- قائمة التذاكر -->
    <div class="tickets-section">
        <div class="section-title">
            <i>📋</i> قائمة التذاكر النشطة
            <span class="count"><?= count($tickets) ?></span>
        </div>
        
        <?php if (empty($tickets)): ?>
            <div class="empty-message">لا توجد تذاكر نشطة حالياً</div>
        <?php else: ?>
            <div class="table-responsive">
                <table id="tickets-table">
                    <thead>
                        <tr>
                            <th>رقم التذكرة</th>
                            <th>العميل</th>
                            <th>نوع الخدمة</th>
                            <th>رقم الشاصي</th>
                            <th>الحالة</th>
                            <th>المسؤول</th>
                            <th>التاريخ</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <?php 
                                // تحديد نوع الشارة حسب الحالة
                                $badge_class = 'badge-warning';
                                if ($ticket['status'] === 'open') $badge_class = 'badge-danger';
                                if ($ticket['status'] === 'completed') $badge_class = 'badge-success';
                                
                                // الحصول على اسم المسؤول
                                $responsible_name = 'غير محدد';
                                if (!empty($ticket['assigned_to'])) {
                                    try {
                                        $stmt = $pdo->prepare("SELECT username FROM users WHERE email = ?");
                                        $stmt->execute([$ticket['assigned_to']]);
                                        $responsible_name = $stmt->fetchColumn() ?: $ticket['assigned_to'];
                                    } catch (PDOException $e) {
                                        // تجاهل الخطأ
                                    }
                                }
                            ?>
                            <tr>
                                <td class="ticket-number"><?= htmlspecialchars($ticket['ticket_number']) ?></td>
                                <td class="client-name">
                                    <?= htmlspecialchars($ticket['client_name']) ?>
                                    <?php if ($ticket['verified_docs'] < 2): ?>
                                        <span class="warning-icon" title="ملف العميل غير مكتمل">⚠️</span>
                                    <?php endif; ?>
                                </td>
                                <td data-service="<?= htmlspecialchars($ticket['service_type']) ?>">
                                    <?= get_service_name($ticket['service_type']) ?>
                                </td>
                                <td class="vin-number"><?= htmlspecialchars($ticket['vin_number'] ?? 'غير محدد') ?></td>
                                <td data-status="<?= htmlspecialchars($ticket['status']) ?>">
                                    <span class="badge <?= $badge_class ?>">
                                        <?= get_status_name($ticket['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($responsible_name) ?></td>
                                <td><?= date('Y-m-d', strtotime($ticket['created_at'])) ?></td>
                                <td class="actions">
                                    <!-- زر عرض التفاصيل -->
                                    <button class="action-btn action-btn-view" 
                                            data-modal="ticket-details-modal"
                                            data-ticket-details="true"
                                            data-ticket-id="<?= $ticket['id'] ?>"
                                            data-ticket-number="<?= htmlspecialchars($ticket['ticket_number']) ?>"
                                            data-ticket-subject="<?= htmlspecialchars($ticket['subject']) ?>"
                                            data-ticket-status="<?= get_status_name($ticket['status']) ?>"
                                            data-ticket-service="<?= get_service_name($ticket['service_type']) ?>"
                                            data-ticket-client="<?= htmlspecialchars($ticket['client_name']) ?>"
                                            data-ticket-vin="<?= htmlspecialchars($ticket['vin_number'] ?? 'غير محدد') ?>"
                                            data-ticket-created="<?= date('Y-m-d H:i', strtotime($ticket['created_at'])) ?>"
                                            data-ticket-description="<?= htmlspecialchars($ticket['description']) ?>">
                                        🔍 عرض
                                    </button>
                                    
                                    <!-- زر تحديث الحالة -->
                                    <button class="action-btn action-btn-status" 
                                            data-modal="update-status-modal"
                                            data-ticket-id="<?= $ticket['id'] ?>">
                                        🔄 تحديث الحالة
                                    </button>
                                    
                                    <?php if ($user_type === 'admin' && empty($ticket['assigned_to'])): ?>
                                        <!-- زر تعيين موظف (للمدير فقط) -->
                                        <button class="action-btn action-btn-assign" 
                                                data-modal="assign-staff-modal"
                                                data-ticket-id="<?= $ticket['id'] ?>">
                                            👤 تعيين موظف
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- النوافذ المنبثقة -->
    
    <!-- نافذة تفاصيل التذكرة -->
    <div id="ticket-details-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn">&times;</button>
            <h3 class="modal-title">تفاصيل التذكرة #<span id="detail-ticket-number"></span></h3>
            
            <div class="ticket-details">
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">الموضوع</div>
                        <div class="detail-value" id="detail-subject"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">الحالة</div>
                        <div class="detail-value" id="detail-status"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">نوع الخدمة</div>
                        <div class="detail-value" id="detail-service"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">العميل</div>
                        <div class="detail-value" id="detail-client"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">رقم الشاصي</div>
                        <div class="detail-value" id="detail-vin"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">تاريخ الإنشاء</div>
                        <div class="detail-value" id="detail-created"></div>
                    </div>
                </div>
                
                <div class="description-section">
                    <h4>وصف المشكلة:</h4>
                    <div class="description-content" id="detail-description"></div>
                </div>
                
                <div class="modal-actions">
                    <button class="btn btn-danger cancel-btn">إغلاق</button>
                    <a href="ticket_detail.php?id=" class="btn btn-primary" id="full-ticket-link">عرض صفحة التذكرة الكاملة</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- نافذة تحديث حالة التذكرة -->
    <div id="update-status-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn">&times;</button>
            <h3 class="modal-title">تحديث حالة التذكرة</h3>
            
            <form method="POST" action="">
                <input type="hidden" name="ticket_id" value="">
                
                <div class="form-group">
                    <label for="new_status">اختر الحالة الجديدة:</label>
                    <select name="new_status" id="new_status" required>
                        <option value="open">جديدة</option>
                        <option value="in_progress">قيد التنفيذ</option>
                        <option value="completed">مكتملة</option>
                        <option value="pending">معلقة</option>
                        <option value="rejected">مرفوضة</option>
                        <option value="cancelled">ملغاة</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-danger cancel-btn">إلغاء</button>
                    <button type="submit" name="update_ticket_status" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($user_type === 'admin'): ?>
    <!-- نافذة تعيين موظف للتذكرة (للمدير فقط) -->
    <div id="assign-staff-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn">&times;</button>
            <h3 class="modal-title">تعيين موظف للتذكرة</h3>
            
            <form method="POST" action="">
                <input type="hidden" name="ticket_id" value="">
                
                <div class="form-group">
                    <label for="staff_email">اختر الموظف:</label>
                    <select name="staff_email" id="staff_email" required>
                        <option value="">-- اختر موظف --</option>
                        <?php foreach ($staff_list as $staff): ?>
                            <option value="<?= htmlspecialchars($staff['email']) ?>">
                                <?= htmlspecialchars($staff['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-danger cancel-btn">إلغاء</button>
                    <button type="submit" name="assign_ticket" class="btn btn-primary">تعيين الموظف</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php
$page_content = ob_get_clean();

// إدراج القالب
include __DIR__ . '/includes/layout.php';
?>