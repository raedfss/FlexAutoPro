<?php
// 1) بدء الجلسة والتأكد من تسجيل الدخول
require_once __DIR__ . '/includes/auth.php';

// 2) تضمين الاتصال بقاعدة البيانات
require_once __DIR__ . '/includes/db.php';

// 3) تضمين الدوال المساعدة
require_once __DIR__ . '/includes/functions.php';

// التحقق من صلاحيات المستخدم - المدراء والموظفين فقط
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    // إعادة التوجيه للصفحة الرئيسية إذا كان المستخدم عادي
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'user') {
        header("Location: index.php");
        exit;
    }
}

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
            
            // التحقق من أن المستخدم يمكنه تحديث هذه التذكرة
            $can_update = false;
            if ($_SESSION['user_role'] === 'admin') {
                $can_update = true; // المدير يمكنه تحديث أي تذكرة
            } elseif ($_SESSION['user_role'] === 'staff' && $ticket['assigned_to'] === $_SESSION['email']) {
                $can_update = true; // الموظف يمكنه تحديث التذاكر المسندة إليه فقط
            }
            
            if ($can_update) {
                $pdo->beginTransaction();
                
                // تحديث حالة التذكرة
                $stmt = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$new_status, $ticket_id]);
                
                // إضافة تعليق تلقائي بالتغيير
                $comment = "تم تغيير حالة التذكرة إلى: " . get_status_name($new_status);
                $stmt = $pdo->prepare("INSERT INTO ticket_comments (ticket_id, user_email, comment) VALUES (?, ?, ?)");
                $stmt->execute([$ticket_id, $_SESSION['email'], $comment]);
                
                // إضافة إشعار للمستخدم
                $stmt = $pdo->prepare("INSERT INTO notifications (user_email, message, is_read) VALUES (?, ?, 0)");
                $stmt->execute([$ticket['user_email'], "تم تحديث حالة التذكرة #{$ticket['ticket_number']} إلى " . get_status_name($new_status)]);
                
                $pdo->commit();
                
                // تسجيل النشاط إذا كانت الدالة متاحة
                if (function_exists('log_activity')) {
                    log_activity($pdo, $_SESSION['email'], 'update_ticket_status', "تم تغيير حالة التذكرة #{$ticket['ticket_number']} إلى " . get_status_name($new_status));
                }
                
                $success_message = "تم تحديث حالة التذكرة بنجاح";
            } else {
                $error_message = "ليس لديك صلاحية لتحديث هذه التذكرة";
            }
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = "حدث خطأ أثناء تحديث حالة التذكرة";
            error_log("Ticket status update error: " . $e->getMessage());
        }
    } else {
        $error_message = "بيانات غير صالحة";
    }
}

// معالجة تعيين موظف للتذكرة
if (isset($_POST['assign_ticket']) && $_SESSION['user_role'] === 'admin') {
    $ticket_id = $_POST['ticket_id'] ?? 0;
    $staff_email = $_POST['staff_email'] ?? '';
    
    if (!empty($ticket_id) && !empty($staff_email)) {
        try {
            $pdo->beginTransaction();
            
            // الحصول على معلومات التذكرة الحالية
            $stmt = $pdo->prepare("SELECT ticket_number, user_email FROM tickets WHERE id = ?");
            $stmt->execute([$ticket_id]);
            $ticket = $stmt->fetch();
            
            // الحصول على اسم الموظف
            $stmt = $pdo->prepare("SELECT username FROM users WHERE email = ?");
            $stmt->execute([$staff_email]);
            $staff_name = $stmt->fetchColumn();
            
            // تحديث التذكرة بتعيين الموظف
            $stmt = $pdo->prepare("UPDATE tickets SET assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$staff_email, $ticket_id]);
            
            // إضافة تعليق بالتعيين
            $comment = "تم تعيين {$staff_name} للعمل على هذه التذكرة";
            $stmt = $pdo->prepare("INSERT INTO ticket_comments (ticket_id, user_email, comment) VALUES (?, ?, ?)");
            $stmt->execute([$ticket_id, $_SESSION['email'], $comment]);
            
            // إشعار للموظف
            $stmt = $pdo->prepare("INSERT INTO notifications (user_email, message, is_read) VALUES (?, ?, 0)");
            $stmt->execute([$staff_email, "تم تعيينك للعمل على التذكرة #{$ticket['ticket_number']}"]); 
            
            $pdo->commit();
            
            // تسجيل النشاط إذا كانت الدالة متاحة
            if (function_exists('log_activity')) {
                log_activity($pdo, $_SESSION['email'], 'assign_ticket', "تم تعيين {$staff_name} للتذكرة #{$ticket['ticket_number']}");
            }
            
            $success_message = "تم تعيين الموظف للتذكرة بنجاح";
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = "حدث خطأ أثناء تعيين الموظف";
            error_log("Ticket assignment error: " . $e->getMessage());
        }
    } else {
        $error_message = "بيانات غير صالحة";
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

// وظيفة للحصول على لون الحالة
function get_status_color($status_code) {
    $status_colors = [
        'open' => 'red',
        'in_progress' => 'yellow',
        'completed' => 'green',
        'cancelled' => 'gray',
        'rejected' => 'gray',
        'pending' => 'orange'
    ];
    
    return $status_colors[$status_code] ?? 'blue';
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

// الحصول على معايير البحث والفلترة
$search_term = $_GET['search'] ?? '';
$filter_service = $_GET['service'] ?? '';
$filter_status = $_GET['status'] ?? '';

// بناء استعلام قائمة التذاكر - استخدام استعلام بسيط لتجنب المشاكل
$tickets = [];

// فقط إذا كان المستخدم مدير أو موظف
if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'staff'])) {
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
        
        // إضافة شروط الفلترة
        if (!empty($search_term)) {
            $query .= " AND (t.ticket_number LIKE ? OR t.vin_number LIKE ? OR u.username LIKE ?)";
            $search_param = "%{$search_term}%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if (!empty($filter_service)) {
            $query .= " AND t.service_type = ?";
            $params[] = $filter_service;
        }
        
        if (!empty($filter_status)) {
            $query .= " AND t.status = ?";
            $params[] = $filter_status;
        }
        
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
        
        // إضافة معلومات التحقق من الوثائق لكل مستخدم
        foreach ($tickets as &$ticket) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_documents WHERE user_email = ? AND status = 'verified'");
                $stmt->execute([$ticket['user_email']]);
                $ticket['verified_docs'] = $stmt->fetchColumn();
            } catch (PDOException $e) {
                $ticket['verified_docs'] = 0;
                error_log("User documents error: " . $e->getMessage());
            }
        }
    } catch (PDOException $e) {
        $error_message = "حدث خطأ أثناء جلب قائمة التذاكر";
        error_log("Tickets fetch error: " . $e->getMessage());
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
            error_log("Staff list error: " . $e->getMessage());
        }
    }
}

// 4) تضمين الهيدر العام (يحتوي <head> وفتح <body>)
require_once __DIR__ . '/includes/header.php';
?>

<main class="container">
    <h2>لوحة تنفيذ المهام 👨‍💻</h2>
    
    <?php if (isset($success_message)): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($success_message) ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'staff'])): ?>
        <!-- قسم البحث والفلترة -->
        <div class="search-box">
            <form method="GET" action="" class="search-form">
                <div class="search-row">
                    <div class="search-input">
                        <input type="text" name="search" placeholder="البحث برقم التذكرة، رقم الشاصي، أو اسم العميل..." value="<?= htmlspecialchars($search_term) ?>">
                    </div>
                    
                    <div class="filter-select">
                        <select name="service">
                            <option value="">-- جميع الخدمات --</option>
                            <option value="key_code" <?= $filter_service === 'key_code' ? 'selected' : '' ?>>طلب كود برمجة</option>
                            <option value="ecu_tuning" <?= $filter_service === 'ecu_tuning' ? 'selected' : '' ?>>تعديل برمجة ECU</option>
                            <option value="airbag_reset" <?= $filter_service === 'airbag_reset' ? 'selected' : '' ?>>مسح بيانات Airbag</option>
                            <option value="remote_programming" <?= $filter_service === 'remote_programming' ? 'selected' : '' ?>>برمجة عن بُعد</option>
                            <option value="other" <?= $filter_service === 'other' ? 'selected' : '' ?>>خدمة أخرى</option>
                        </select>
                    </div>
                    
                    <div class="filter-select">
                        <select name="status">
                            <option value="">-- جميع الحالات --</option>
                            <option value="open" <?= $filter_status === 'open' ? 'selected' : '' ?>>جديدة</option>
                            <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>قيد التنفيذ</option>
                            <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>مكتملة</option>
                            <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>ملغاة</option>
                            <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>مرفوضة</option>
                            <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>معلقة</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        🔍 بحث
                    </button>
                    
                    <?php if (!empty($search_term) || !empty($filter_service) || !empty($filter_status)): ?>
                        <a href="dashboard.php" class="btn btn-danger">
                            ❌ إلغاء الفلترة
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- قائمة التذاكر -->
        <div class="tickets-section">
            <h3>
                📋 قائمة التذاكر 
                <span class="tickets-count"><?= count($tickets) ?> تذكرة</span>
            </h3>
            
            <?php if (empty($tickets)): ?>
                <div class="empty-tickets">
                    <p>لا توجد تذاكر متاحة حالياً</p>
                </div>
            <?php else: ?>
                <div class="tickets-table">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
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
                            <?php foreach ($tickets as $index => $ticket): ?>
                                <?php 
                                    $status_color = get_status_color($ticket['status']);
                                    $status_class = "status-{$status_color}";
                                ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($ticket['ticket_number']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($ticket['user_name']) ?>
                                        <?php if (isset($ticket['verified_docs']) && $ticket['verified_docs'] < 2): ?>
                                            <div class="warning-tag">⚠️ ملف غير مكتمل</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= get_service_name($ticket['service_type']) ?></td>
                                    <td><?= htmlspecialchars($ticket['vin_number'] ?? 'غير محدد') ?></td>
                                    <td>
                                        <span class="status-indicator <?= $status_class ?>"></span>
                                        <span class="status-text">
                                            <?= get_status_name($ticket['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($ticket['assigned_to']): ?>
                                            <?php 
                                                // الحصول على اسم المسؤول
                                                try {
                                                    $stmt = $pdo->prepare("SELECT username FROM users WHERE email = ?");
                                                    $stmt->execute([$ticket['assigned_to']]);
                                                    $staff_name = $stmt->fetchColumn();
                                                    echo htmlspecialchars($staff_name ?? $ticket['assigned_to']);
                                                } catch (PDOException $e) {
                                                    echo htmlspecialchars($ticket['assigned_to']);
                                                }
                                            ?>
                                        <?php else: ?>
                                            <span class="not-assigned">غير معين</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('Y-m-d', strtotime($ticket['created_at'])) ?></td>
                                    <td class="actions">
                                        <!-- زر عرض التفاصيل -->
                                        <button class="btn-action view-details" 
                                                data-modal="ticket-details-modal"
                                                data-ticket-id="<?= $ticket['id'] ?>"
                                                data-ticket-details="true"
                                                data-ticket-number="<?= htmlspecialchars($ticket['ticket_number']) ?>"
                                                data-ticket-subject="<?= htmlspecialchars($ticket['subject']) ?>"
                                                data-ticket-status="<?= get_status_name($ticket['status']) ?>"
                                                data-ticket-priority="<?= htmlspecialchars($ticket['priority']) ?>"
                                                data-ticket-service-type="<?= get_service_name($ticket['service_type']) ?>"
                                                data-ticket-vin="<?= htmlspecialchars($ticket['vin_number'] ?? 'غير محدد') ?>"
                                                data-ticket-created="<?= date('Y-m-d H:i', strtotime($ticket['created_at'])) ?>"
                                                data-ticket-user-name="<?= htmlspecialchars($ticket['user_name']) ?>"
                                                data-ticket-user-email="<?= htmlspecialchars($ticket['user_email']) ?>"
                                                data-ticket-assigned-to="<?= htmlspecialchars($ticket['assigned_to'] ?? 'غير معين') ?>"
                                                data-ticket-description="<?= htmlspecialchars($ticket['description']) ?>">
                                            🔍 عرض التفاصيل
                                        </button>
                                        
                                        <!-- زر تحديث الحالة -->
                                        <button class="btn-action update-status" 
                                                data-modal="update-status-modal"
                                                data-ticket-id="<?= $ticket['id'] ?>">
                                            🔄 تحديث الحالة
                                        </button>
                                        
                                        <?php if ($_SESSION['user_role'] === 'admin' && !$ticket['assigned_to']): ?>
                                            <!-- زر تعيين موظف (للمدير فقط) -->
                                            <button class="btn-action assign-staff" 
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
                <h3 class="modal-title">تفاصيل التذكرة <span class="ticket-number"></span></h3>
                <div class="modal-body">
                    <div class="ticket-detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">الموضوع:</div>
                            <div class="detail-value ticket-subject"></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">الحالة:</div>
                            <div class="detail-value ticket-status"></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">الأولوية:</div>
                            <div class="detail-value ticket-priority"></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">نوع الخدمة:</div>
                            <div class="detail-value ticket-service-type"></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">رقم الشاصي (VIN):</div>
                            <div class="detail-value ticket-vin"></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">تاريخ الإنشاء:</div>
                            <div class="detail-value ticket-created"></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">العميل:</div>
                            <div class="detail-value ticket-user-name"></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">البريد الإلكتروني:</div>
                            <div class="detail-value">
                                <span class="ticket-user-email"></span>
                                <button class="copy-button copy-email-btn" data-copy="">نسخ</button>
                                <a href="mailto:" class="email-link">📧</a>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">المسؤول:</div>
                            <div class="detail-value ticket-assigned-to"></div>
                        </div>
                    </div>
                    
                    <h4 class="description-title">وصف المشكلة:</h4>
                    <div class="ticket-description"></div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-close close-modal">إغلاق</button>
                        
                        <a href="ticket_details.php?id=" class="btn-view ticket-link">
                            📝 الانتقال إلى صفحة التذكرة الكاملة
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- نافذة تحديث حالة التذكرة -->
        <div id="update-status-modal" class="modal">
            <div class="modal-content">
                <h3 class="modal-title">تحديث حالة التذكرة</h3>
                <div class="modal-body">
                    <form method="POST" action="" class="status-form">
                        <input type="hidden" name="ticket_id" class="ticket-id-input" value="">
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
                            <button type="button" class="btn-close close-modal">إلغاء</button>
                            <button type="submit" name="update_ticket_status" class="btn-submit">حفظ التغييرات</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <?php if ($_SESSION['user_role'] === 'admin'): ?>
        <!-- نافذة تعيين موظف للتذكرة (للمدير فقط) -->
        <div id="assign-staff-modal" class="modal">
            <div class="modal-content">
                <h3 class="modal-title">تعيين موظف للتذكرة</h3>
                <div class="modal-body">
                    <form method="POST" action="" class="assign-form">
                        <input type="hidden" name="ticket_id" class="ticket-id-input" value="">
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
                            <button type="button" class="btn-close close-modal">إلغاء</button>
                            <button type="submit" name="assign_ticket" class="btn-submit">تعيين الموظف</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- القائمة الافتراضية للمستخدمين العاديين -->
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
    <?php endif; ?>
</main>

<style>
    /* ستايل نظام التذاكر */
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    h2 {
        color: #1e90ff;
        margin-bottom: 20px;
        text-align: center;
    }
    .search-box {
        background: rgba(30, 30, 50, 0.7);
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid rgba(66, 135, 245, 0.15);
    }
    .search-form {
        width: 100%;
    }
    .search-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }
    .search-input {
        flex: 1;
        min-width: 200px;
    }
    .search-input input {
        width: 100%;
        padding: 8px 12px;
        border-radius: 6px;
        border: 1px solid #2a80b9;
        background-color: rgba(0, 0, 0, 0.3);
        color: #fff;
    }
    .filter-select {
        min-width: 150px;
    }
    .filter-select select {
        width: 100%;
        padding: 8px 12px;
        border-radius: 6px;
        border: 1px solid #2a80b9;
        background-color: rgba(0, 0, 0, 0.3);
        color: #fff;
    }
    .btn {
        padding: 8px 15px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-weight: bold;
    }
    .btn-primary {
        background-color: #1e90ff;
        color: white;
    }
    .btn-danger {
        background-color: #e74c3c;
        color: white;
    }
    .tickets-section {
        background: rgba(30, 30, 50, 0.7);
        border-radius: 12px;
        padding: 20px;
        border: 1px solid rgba(66, 135, 245, 0.15);
    }
    .tickets-section h3 {
        color: #1e90ff;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .tickets-count {
        background: rgba(30, 144, 255, 0.2);
        color: #1e90ff;
        padding: 3px 8px;
        border-radius: 50px;
        font-size: 14px;
        margin-right: 10px;
    }
    .tickets-table {
        overflow-x: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        color: white;
    }
    table th, table td {
        padding: 10px;
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
    .status-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-left: 5px;
    }
    .status-red {
        background: #ff3b30;
        box-shadow: 0 0 5px #ff3b30;
    }
    .status-yellow {
        background: #ff9500;
        box-shadow: 0 0 5px #ff9500;
    }
    .status-green {
        background: #34c759;
        box-shadow: 0 0 5px #34c759;
    }
    .status-gray {
        background: #8e8e93;
        box-shadow: 0 0 5px #8e8e93;
    }
    .status-orange {
        background: #ff9500;
        box-shadow: 0 0 5px #ff9500;
    }
    .warning-tag {
        font-size: 12px;
        color: #ff3b30;
        margin-top: 3px;
    }
    .not-assigned {
        color: #8e8e93;
        font-style: italic;
    }
    .btn-action {
        padding: 5px 10px;
        border-radius: 4px;
        border: none;
        margin-right: 5px;
        margin-bottom: 5px;
        cursor: pointer;
        font-size: 12px;
        color: white;
        display: inline-block;
    }
    .view-details {
        background-color: #1e90ff;
    }
    .update-status {
        background-color: #ff9500;
    }
    .assign-staff {
        background-color: #34c759;
    }
    .actions {
        white-space: nowrap;
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
        border-radius: 12px;
        width: 90%;
        max-width: 700px;
        max-height: 90vh;
        overflow-y: auto;
        padding: 20px;
        border: 1px solid rgba(66, 135, 245, 0.25);
    }
    .modal-title {
        color: #1e90ff;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .ticket-detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
        margin-bottom: 15px;
    }
    .detail-item {
        background: rgba(0, 0, 0, 0.2);
        padding: 8px;
        border-radius: 6px;
    }
    .detail-label {
        color: #a8d8ff;
        font-size: 0.9em;
        margin-bottom: 3px;
    }
    .description-title {
        color: #a8d8ff;
        margin-top: 15px;
        margin-bottom: 5px;
    }
    .ticket-description {
        background: rgba(0, 0, 0, 0.2);
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 15px;
        white-space: pre-line;
    }
    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 15px;
    }
    .btn-close {
        background-color: #e74c3c;
        color: white;
        padding: 8px 15px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
    }
    .btn-view, .btn-submit {
        background-color: #1e90ff;
        color: white;
        padding: 8px 15px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        text-decoration: none;
    }
    .copy-button {
        background: rgba(30, 144, 255, 0.2);
        color: #1e90ff;
        border: 1px solid rgba(30, 144, 255, 0.3);
        border-radius: 4px;
        padding: 2px 6px;
        font-size: 11px;
        cursor: pointer;
        margin-right: 3px;
    }
    .email-link {
        color: #1e90ff;
        text-decoration: none;
        margin-right: 3px;
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
        padding: 8px;
        border-radius: 6px;
        border: 1px solid rgba(30, 144, 255, 0.3);
        background: rgba(0, 0, 0, 0.3);
        color: white;
    }
    .alert {
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 15px;
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
    .empty-tickets {
        text-align: center;
        padding: 20px;
        color: #8e8e93;
    }
    
    /* ستايل الروابط الافتراضية في الصفحة القديمة */
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // التعامل مع النوافذ المنبثقة
    const modals = document.querySelectorAll('.modal');
    const modalTriggers = document.querySelectorAll('[data-modal]');
    const closeModalButtons = document.querySelectorAll('.close-modal');
    
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                
                // تحديث معرف التذكرة في النماذج داخل النافذة
                const ticketId = this.getAttribute('data-ticket-id');
                if (ticketId) {
                    const ticketIdInputs = modal.querySelectorAll('.ticket-id-input');
                    ticketIdInputs.forEach(input => input.value = ticketId);
                    
                    // تحديث رابط صفحة التذكرة
                    const ticketLink = modal.querySelector('.ticket-link');
                    if (ticketLink) {
                        ticketLink.href = 'ticket_details.php?id=' + ticketId;
                    }
                }
                
                // تحديث بيانات التذكرة في تفاصيل النافذة
                const ticketDetails = this.getAttribute('data-ticket-details');
                if (ticketDetails && ticketDetails === 'true') {
                    // بيانات التذكرة
                    const ticketNumber = this.getAttribute('data-ticket-number');
                    const ticketSubject = this.getAttribute('data-ticket-subject');
                    const ticketStatus = this.getAttribute('data-ticket-status');
                    const ticketPriority = this.getAttribute('data-ticket-priority');
                    const ticketServiceType = this.getAttribute('data-ticket-service-type');
                    const ticketVin = this.getAttribute('data-ticket-vin');
                    const ticketCreated = this.getAttribute('data-ticket-created');
                    const ticketUserName = this.getAttribute('data-ticket-user-name');
                    const ticketUserEmail = this.getAttribute('data-ticket-user-email');
                    const ticketAssignedTo = this.getAttribute('data-ticket-assigned-to');
                    const ticketDescription = this.getAttribute('data-ticket-description');
                    
                    // تحديث العناصر في النافذة
                    if (ticketNumber) {
                        const elements = modal.querySelectorAll('.ticket-number');
                        elements.forEach(el => el.textContent = ticketNumber);
                    }
                    if (ticketSubject) modal.querySelector('.ticket-subject').textContent = ticketSubject;
                    if (ticketStatus) modal.querySelector('.ticket-status').textContent = ticketStatus;
                    if (ticketPriority) modal.querySelector('.ticket-priority').textContent = ticketPriority;
                    if (ticketServiceType) modal.querySelector('.ticket-service-type').textContent = ticketServiceType;
                    if (ticketVin) modal.querySelector('.ticket-vin').textContent = ticketVin;
                    if (ticketCreated) modal.querySelector('.ticket-created').textContent = ticketCreated;
                    if (ticketUserName) modal.querySelector('.ticket-user-name').textContent = ticketUserName;
                    if (ticketUserEmail) {
                        modal.querySelector('.ticket-user-email').textContent = ticketUserEmail;
                        
                        // تحديث زر نسخ البريد
                        const copyEmailBtn = modal.querySelector('.copy-email-btn');
                        if (copyEmailBtn) {
                            copyEmailBtn.setAttribute('data-copy', ticketUserEmail);
                        }
                        
                        // تحديث رابط البريد
                        const emailLink = modal.querySelector('.email-link');
                        if (emailLink) {
                            emailLink.href = 'mailto:' + ticketUserEmail;
                        }
                    }
                    if (ticketAssignedTo) modal.querySelector('.ticket-assigned-to').textContent = ticketAssignedTo;
                    if (ticketDescription) modal.querySelector('.ticket-description').textContent = ticketDescription;
                }
            }
        });
    });
    
    closeModalButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.classList.remove('active');
            }
        });
    });
    
    // إغلاق النافذة المنبثقة عند النقر خارجها
    modals.forEach(modal => {
        modal.addEventListener('click', function(event) {
            if (event.target === this) {
                this.classList.remove('active');
            }
        });
    });
    
    // تأكيد تغيير الحالة
    const statusForms = document.querySelectorAll('.status-form');
    statusForms.forEach(form => {
        form.addEventListener('submit', function(event) {
            const newStatus = this.querySelector('select[name="new_status"]').value;
            const statusNames = {
                'open': 'جديدة',
                'in_progress': 'قيد التنفيذ',
                'completed': 'مكتملة',
                'cancelled': 'ملغاة',
                'rejected': 'مرفوضة',
                'pending': 'معلقة'
            };
            const statusName = statusNames[newStatus] || newStatus;
            
            if (!confirm("هل أنت متأكد من تغيير حالة التذكرة إلى " + statusName + "؟")) {
                event.preventDefault();
            }
        });
    });
    
    // تأكيد تعيين موظف
    const assignForms = document.querySelectorAll('.assign-form');
    assignForms.forEach(form => {
        form.addEventListener('submit', function(event) {
            const staffSelect = this.querySelector('select[name="staff_email"]');
            if (staffSelect.selectedIndex > 0) {
                const staffName = staffSelect.options[staffSelect.selectedIndex].text;
                
                if (!confirm("هل أنت متأكد من تعيين " + staffName + " لهذه التذكرة؟")) {
                    event.preventDefault();
                }
            } else {
                alert("يرجى اختيار موظف");
                event.preventDefault();
            }
        });
    });
    
    // وظيفة لنسخ النص إلى الحافظة
    const copyButtons = document.querySelectorAll('.copy-button');
    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const textToCopy = this.getAttribute('data-copy');
            if (textToCopy) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    // تغيير نص الزر مؤقتًا
                    const originalText = this.textContent;
                    this.textContent = '✓ تم النسخ';
                    setTimeout(() => {
                        this.textContent = originalText;
                    }, 1500);
                }).catch(err => {
                    console.error('خطأ في نسخ النص: ', err);
                });
            }
        });
    });
    
    // إغلاق التنبيهات بعد 5 ثوانٍ
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
</script>

<?php
// 5) تضمين الفوتر العام (يغلق </body></html>)
require_once __DIR__ . '/includes/footer.php';
?>