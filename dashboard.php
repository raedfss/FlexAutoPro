<?php
// 1) بدء الجلسة والتأكد من تسجيل الدخول
require_once __DIR__ . '/includes/auth.php';

// 2) تضمين الاتصال بقاعدة البيانات
require_once __DIR__ . '/includes/db.php';

// 3) تضمين الدوال المساعدة
require_once __DIR__ . '/includes/functions.php';

// 4) تضمين الهيدر العام (يحتوي <head> وفتح <body>)
require_once __DIR__ . '/includes/header.php';

// التحقق من وجود رسائل النجاح أو الخطأ من العمليات
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;

// مسح رسائل الجلسة بعد عرضها
if (isset($_SESSION['success_message'])) {
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    unset($_SESSION['error_message']);
}

// وظائف مساعدة للتعامل مع التذاكر
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

// تحميل التذاكر فقط إذا كان المستخدم له صلاحيات (admin أو staff)
$is_admin_or_staff = isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'staff']);
$tickets = [];

if ($is_admin_or_staff) {
    // معالجة تحديث حالة التذكرة
    if (isset($_POST['update_ticket_status'])) {
        $ticket_id = $_POST['ticket_id'] ?? 0;
        $new_status = $_POST['new_status'] ?? '';
        
        if (!empty($ticket_id) && !empty($new_status)) {
            try {
                // التحقق من صلاحية الوصول للتذكرة
                $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
                $stmt->execute([$ticket_id]);
                $ticket = $stmt->fetch();
                
                // تحديث حالة التذكرة
                $stmt = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$new_status, $ticket_id]);
                
                // إضافة تعليق تلقائي بالتغيير
                $comment = "تم تغيير حالة التذكرة إلى: " . get_status_name($new_status);
                $stmt = $pdo->prepare("INSERT INTO ticket_comments (ticket_id, user_email, comment) VALUES (?, ?, ?)");
                $stmt->execute([$ticket_id, $_SESSION['email'], $comment]);
                
                $success_message = "تم تحديث حالة التذكرة بنجاح";
                $_SESSION['success_message'] = $success_message;
                
                // إعادة تحميل الصفحة لتحديث البيانات
                header("Location: dashboard.php");
                exit;
            } catch (PDOException $e) {
                $error_message = "حدث خطأ أثناء تحديث حالة التذكرة";
                $_SESSION['error_message'] = $error_message;
            }
        }
    }
    
    // معالجة تعيين موظف للتذكرة (للمدير فقط)
    if (isset($_POST['assign_ticket']) && $_SESSION['user_role'] === 'admin') {
        $ticket_id = $_POST['ticket_id'] ?? 0;
        $staff_email = $_POST['staff_email'] ?? '';
        
        if (!empty($ticket_id) && !empty($staff_email)) {
            try {
                // تحديث التذكرة بتعيين الموظف
                $stmt = $pdo->prepare("UPDATE tickets SET assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$staff_email, $ticket_id]);
                
                $success_message = "تم تعيين الموظف للتذكرة بنجاح";
                $_SESSION['success_message'] = $success_message;
                
                // إعادة تحميل الصفحة لتحديث البيانات
                header("Location: dashboard.php");
                exit;
            } catch (PDOException $e) {
                $error_message = "حدث خطأ أثناء تعيين الموظف";
                $_SESSION['error_message'] = $error_message;
            }
        }
    }
    
    // استعلام التذاكر
    try {
        $query = "
            SELECT 
                t.id, 
                t.ticket_number, 
                t.user_email, 
                t.subject, 
                t.description, 
                t.status, 
                t.priority,
                t.service_type,
                t.vin_number, 
                t.assigned_to, 
                t.created_at, 
                t.updated_at,
                u.username as user_name
            FROM 
                tickets t
            JOIN 
                users u ON t.user_email = u.email
            WHERE 1=1
        ";
        
        $params = [];
        
        // إذا كان الموظف، فاعرض فقط التذاكر المسندة إليه
        if ($_SESSION['user_role'] === 'staff') {
            $query .= " AND (t.assigned_to = ? OR t.assigned_to IS NULL)";
            $params[] = $_SESSION['email'];
        }
        
        // ترتيب النتائج
        $query .= " ORDER BY 
            CASE 
                WHEN t.status = 'open' THEN 1
                WHEN t.status = 'in_progress' THEN 2
                ELSE 3
            END,
            t.created_at DESC
        ";
        
        // تنفيذ الاستعلام
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll();
        
        // إضافة معلومات الوثائق لكل مستخدم
        foreach ($tickets as &$ticket) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_documents WHERE user_email = ? AND status = 'verified'");
                $stmt->execute([$ticket['user_email']]);
                $ticket['verified_docs'] = $stmt->fetchColumn();
            } catch (PDOException $e) {
                $ticket['verified_docs'] = 0;
            }
        }
    } catch (PDOException $e) {
        // تجاهل الخطأ واستمر في العرض
        $tickets = [];
    }
    
    // الحصول على قائمة الموظفين (للمدير فقط)
    $staff_list = [];
    if ($_SESSION['user_role'] === 'admin') {
        try {
            $stmt = $pdo->prepare("SELECT email, username FROM users WHERE user_role = 'staff' AND is_active = TRUE ORDER BY username");
            $stmt->execute();
            $staff_list = $stmt->fetchAll();
        } catch (PDOException $e) {
            // تجاهل الخطأ
        }
    }
}
?>

<main class="container">
    <h2>مرحبًا، <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES) ?> 👋</h2>
    
    <?php if ($success_message): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($success_message) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>
    
    <p>أهلاً بك في لوحة التحكم الخاصة بك على منصة <strong>FlexAuto</strong>.</p>

    <?php if ($is_admin_or_staff): ?>
        <!-- قسم مخصص للمدراء والموظفين -->
        <div class="ticket-dashboard">
            <h3>📋 قائمة التذاكر (<?= count($tickets) ?>)</h3>
            
            <?php if (empty($tickets)): ?>
                <p class="empty-message">لا توجد تذاكر متاحة حالياً</p>
            <?php else: ?>
                <div class="tickets-table-container">
                    <table class="tickets-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>رقم التذكرة</th>
                                <th>العميل</th>
                                <th>نوع الخدمة</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $index => $ticket): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($ticket['ticket_number']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($ticket['user_name']) ?>
                                        <?php if ($ticket['verified_docs'] < 2): ?>
                                            <span class="docs-warning">⚠️</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= get_service_name($ticket['service_type']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $ticket['status'] ?>">
                                            <?= get_status_name($ticket['status']) ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <button class="btn btn-view" 
                                                onclick="showTicketDetails(<?= $ticket['id'] ?>, '<?= htmlspecialchars($ticket['ticket_number'], ENT_QUOTES) ?>', '<?= htmlspecialchars($ticket['subject'], ENT_QUOTES) ?>', '<?= htmlspecialchars($ticket['description'], ENT_QUOTES) ?>', '<?= $ticket['status'] ?>', '<?= htmlspecialchars($ticket['user_name'], ENT_QUOTES) ?>')">
                                            🔍 عرض
                                        </button>
                                        
                                        <button class="btn btn-status" 
                                                onclick="showUpdateStatus(<?= $ticket['id'] ?>)">
                                            🔄 تحديث الحالة
                                        </button>
                                        
                                        <?php if ($_SESSION['user_role'] === 'admin' && empty($ticket['assigned_to'])): ?>
                                            <button class="btn btn-assign" 
                                                    onclick="showAssignStaff(<?= $ticket['id'] ?>)">
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
        <div id="ticketDetailsModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3>تفاصيل التذكرة <span id="ticketNumberDisplay"></span></h3>
                <div class="ticket-details">
                    <div class="detail-row">
                        <strong>الموضوع:</strong>
                        <span id="ticketSubjectDisplay"></span>
                    </div>
                    <div class="detail-row">
                        <strong>العميل:</strong>
                        <span id="ticketUserDisplay"></span>
                    </div>
                    <div class="detail-row">
                        <strong>الحالة:</strong>
                        <span id="ticketStatusDisplay"></span>
                    </div>
                    <div class="detail-description">
                        <strong>وصف المشكلة:</strong>
                        <p id="ticketDescriptionDisplay"></p>
                    </div>
                </div>
            </div>
        </div>

        <div id="updateStatusModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3>تحديث حالة التذكرة</h3>
                <form method="POST" action="" id="statusForm">
                    <input type="hidden" name="ticket_id" id="statusTicketId">
                    <div class="form-group">
                        <label for="new_status">الحالة الجديدة:</label>
                        <select name="new_status" id="new_status" required>
                            <option value="open">جديدة</option>
                            <option value="in_progress">قيد التنفيذ</option>
                            <option value="completed">مكتملة</option>
                            <option value="pending">معلقة</option>
                            <option value="rejected">مرفوضة</option>
                            <option value="cancelled">ملغاة</option>
                        </select>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn btn-cancel close-modal">إلغاء</button>
                        <button type="submit" name="update_ticket_status" class="btn btn-save">حفظ</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($_SESSION['user_role'] === 'admin'): ?>
        <div id="assignStaffModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3>تعيين موظف للتذكرة</h3>
                <form method="POST" action="" id="assignForm">
                    <input type="hidden" name="ticket_id" id="assignTicketId">
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
                    <div class="form-buttons">
                        <button type="button" class="btn btn-cancel close-modal">إلغاء</button>
                        <button type="submit" name="assign_ticket" class="btn btn-save">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- القائمة الرئيسية للجميع -->
    <div class="dashboard-links">
        <ul>
            <li><a href="request_code.php">🔐 طلب كود برمجي</a></li>
            <li><a href="airbag_reset.php">💥 مسح بيانات الحوادث</a></li>
            <li><a href="ecu_tuning.php">⚙️ تعديل برمجة ECU</a></li>
            <li><a href="notifications.php">🔔 عرض الإشعارات</a></li>
            <li><a href="messages.php">📩 الرسائل</a></li>
            <li><a href="profile.php">👤 إدارة الملف الشخصي</a></li>
        </ul>
    </div>
</main>

<style>
    /* تخصيص روابط لوحة التحكم */
    .dashboard-links ul {
        list-style: none;
        margin: 20px 0;
        padding: 0;
    }
    .dashboard-links ul li {
        margin: 8px 0;
    }
    .dashboard-links ul li a {
        display: inline-block;
        padding: 10px 18px;
        background-color: #004080;
        color: #fff;
        text-decoration: none;
        border-radius: 6px;
        transition: background 0.2s;
    }
    .dashboard-links ul li a:hover {
        background-color: #0066cc;
    }
    
    /* ستايل قسم التذاكر */
    .ticket-dashboard {
        background: rgba(30, 30, 50, 0.7);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid rgba(66, 135, 245, 0.15);
    }
    .ticket-dashboard h3 {
        color: #1e90ff;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .tickets-table-container {
        overflow-x: auto;
    }
    .tickets-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    .tickets-table th, 
    .tickets-table td {
        padding: 12px;
        text-align: right;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .tickets-table th {
        background: rgba(30, 144, 255, 0.2);
        color: #1e90ff;
    }
    .tickets-table tr:hover {
        background: rgba(30, 144, 255, 0.1);
    }
    .docs-warning {
        color: #ff9500;
        margin-right: 5px;
    }
    .status-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 12px;
    }
    .status-open {
        background: rgba(255, 59, 48, 0.2);
        color: #ff3b30;
        border: 1px solid rgba(255, 59, 48, 0.4);
    }
    .status-in_progress {
        background: rgba(255, 149, 0, 0.2);
        color: #ff9500;
        border: 1px solid rgba(255, 149, 0, 0.4);
    }
    .status-completed {
        background: rgba(52, 199, 89, 0.2);
        color: #34c759;
        border: 1px solid rgba(52, 199, 89, 0.4);
    }
    .status-cancelled, .status-rejected {
        background: rgba(142, 142, 147, 0.2);
        color: #8e8e93;
        border: 1px solid rgba(142, 142, 147, 0.4);
    }
    .status-pending {
        background: rgba(90, 200, 250, 0.2);
        color: #5ac8fa;
        border: 1px solid rgba(90, 200, 250, 0.4);
    }
    .actions {
        white-space: nowrap;
    }
    .btn {
        padding: 5px 10px;
        margin: 0 2px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        color: white;
    }
    .btn-view {
        background-color: #1e90ff;
    }
    .btn-status {
        background-color: #ff9500;
    }
    .btn-assign {
        background-color: #34c759;
    }
    .btn-cancel {
        background-color: #ff3b30;
    }
    .btn-save {
        background-color: #1e90ff;
    }
    .empty-message {
        text-align: center;
        padding: 20px;
        color: #8e8e93;
    }
    .alert {
        padding: 10px 15px;
        margin-bottom: 15px;
        border-radius: 6px;
    }
    .alert-success {
        background: rgba(52, 199, 89, 0.2);
        border: 1px solid rgba(52, 199, 89, 0.4);
        color: #34c759;
    }
    .alert-danger {
        background: rgba(255, 59, 48, 0.2);
        border: 1px solid rgba(255, 59, 48, 0.4);
        color: #ff3b30;
    }
    
    /* النوافذ المنبثقة */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        overflow: auto;
    }
    .modal-content {
        background-color: rgba(20, 20, 40, 0.95);
        margin: 10% auto;
        padding: 20px;
        border: 1px solid rgba(66, 135, 245, 0.25);
        width: 80%;
        max-width: 600px;
        border-radius: 12px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
    }
    .close {
        color: #aaa;
        float: left;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    .close:hover {
        color: #fff;
    }
    .ticket-details {
        margin-top: 15px;
    }
    .detail-row {
        margin-bottom: 10px;
    }
    .detail-row strong {
        color: #1e90ff;
        margin-left: 10px;
    }
    .detail-description {
        margin-top: 15px;
    }
    .detail-description strong {
        color: #1e90ff;
        display: block;
        margin-bottom: 5px;
    }
    .detail-description p {
        background: rgba(0, 0, 0, 0.2);
        padding: 10px;
        border-radius: 6px;
        white-space: pre-line;
    }
    .form-group {
        margin-bottom: 15px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        color: #1e90ff;
    }
    .form-group select {
        width: 100%;
        padding: 8px;
        border-radius: 4px;
        background-color: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(30, 144, 255, 0.4);
        color: white;
    }
    .form-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }
</style>

<script>
// عندما يتم تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    // الحصول على النوافذ المنبثقة
    var detailsModal = document.getElementById('ticketDetailsModal');
    var statusModal = document.getElementById('updateStatusModal');
    var assignModal = document.getElementById('assignStaffModal');
    
    // الحصول على أزرار الإغلاق
    var closeButtons = document.getElementsByClassName('close');
    for (var i = 0; i < closeButtons.length; i++) {
        closeButtons[i].onclick = function() {
            detailsModal.style.display = "none";
            if (statusModal) statusModal.style.display = "none";
            if (assignModal) assignModal.style.display = "none";
        }
    }
    
    // أزرار إغلاق إضافية
    var cancelButtons = document.getElementsByClassName('close-modal');
    for (var i = 0; i < cancelButtons.length; i++) {
        cancelButtons[i].onclick = function() {
            detailsModal.style.display = "none";
            if (statusModal) statusModal.style.display = "none";
            if (assignModal) assignModal.style.display = "none";
        }
    }
    
    // إغلاق النافذة عند النقر خارجها
    window.onclick = function(event) {
        if (event.target == detailsModal) {
            detailsModal.style.display = "none";
        }
        if (event.target == statusModal) {
            statusModal.style.display = "none";
        }
        if (assignModal && event.target == assignModal) {
            assignModal.style.display = "none";
        }
    }
    
    // إغلاق التنبيهات تلقائياً بعد 5 ثوانٍ
    var alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        setTimeout(function() {
            alerts.forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 5000);
    }
});

// وظيفة لعرض تفاصيل التذكرة
function showTicketDetails(id, number, subject, description, status, userName) {
    var modal = document.getElementById('ticketDetailsModal');
    document.getElementById('ticketNumberDisplay').textContent = number;
    document.getElementById('ticketSubjectDisplay').textContent = subject;
    document.getElementById('ticketDescriptionDisplay').textContent = description;
    document.getElementById('ticketUserDisplay').textContent = userName;
    
    var statusDisplay = document.getElementById('ticketStatusDisplay');
    statusDisplay.textContent = getStatusName(status);
    statusDisplay.className = 'status-badge status-' + status;
    
    modal.style.display = "block";
}

// وظيفة لعرض نافذة تحديث الحالة
function showUpdateStatus(id) {
    var modal = document.getElementById('updateStatusModal');
    document.getElementById('statusTicketId').value = id;
    modal.style.display = "block";
}

// وظيفة لعرض نافذة تعيين موظف
function showAssignStaff(id) {
    var modal = document.getElementById('assignStaffModal');
    if (modal) {
        document.getElementById('assignTicketId').value = id;
        modal.style.display = "block";
    }
}

// وظيفة للحصول على اسم الحالة بالعربية
function getStatusName(statusCode) {
    var statusNames = {
        'open': 'جديدة',
        'in_progress': 'قيد التنفيذ',
        'completed': 'مكتملة',
        'cancelled': 'ملغاة',
        'rejected': 'مرفوضة',
        'pending': 'معلقة'
    };
    
    return statusNames[statusCode] || statusCode;
}
</script>

<?php
// 5) تضمين الفوتر العام (يغلق </body></html>)
require_once __DIR__ . '/includes/footer.php';
?>