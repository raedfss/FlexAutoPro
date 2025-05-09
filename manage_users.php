<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// التحقق من تسجيل الدخول وصلاحيات المدير
if (!isset($_SESSION['email']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit;
}

$username = $_SESSION['username'];
$email = $_SESSION['email'];

// إعداد عنوان الصفحة
$page_title = 'إدارة المستخدمين';
$display_title = 'إدارة المستخدمين';

// معالجة النموذج لإضافة موظف جديد
if (isset($_POST['add_staff'])) {
    $staff_name = $_POST['staff_name'] ?? '';
    $staff_email = $_POST['staff_email'] ?? '';
    $staff_password = $_POST['staff_password'] ?? '';
    
    if (!empty($staff_name) && !empty($staff_email) && !empty($staff_password)) {
        // التحقق من عدم وجود البريد الإلكتروني مسبقاً
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$staff_email]);
        
        if ($stmt->fetchColumn() == 0) {
            // إنشاء كلمة مرور مشفرة
            $hashed_password = password_hash($staff_password, PASSWORD_DEFAULT);
            
            // إضافة الموظف الجديد
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, user_role, is_active) VALUES (?, ?, ?, 'staff', 1)");
                $stmt->execute([$staff_name, $staff_email, $hashed_password]);
                $success_message = "تمت إضافة الموظف بنجاح";
            } catch (PDOException $e) {
                $error_message = "حدث خطأ أثناء إضافة الموظف: " . $e->getMessage();
            }
        } else {
            $error_message = "البريد الإلكتروني مستخدم بالفعل";
        }
    } else {
        $error_message = "يرجى ملء جميع الحقول المطلوبة";
    }
}

// معالجة تغيير كلمة المرور
if (isset($_POST['change_password'])) {
    $user_id = $_POST['user_id'] ?? 0;
    $new_password = $_POST['new_password'] ?? '';
    
    if (!empty($user_id) && !empty($new_password)) {
        // التحقق من عدم تغيير كلمة مرور المدير الحالي
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_email = $stmt->fetchColumn();
        
        if ($user_email && $user_email !== $email) {
            // تحديث كلمة المرور
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                $success_message = "تم تغيير كلمة المرور بنجاح";
            } catch (PDOException $e) {
                $error_message = "حدث خطأ أثناء تغيير كلمة المرور: " . $e->getMessage();
            }
        } else {
            $error_message = "لا يمكنك تغيير كلمة مرور حسابك من هنا";
        }
    } else {
        $error_message = "بيانات غير صالحة";
    }
}

// معالجة تغيير حالة المستخدم (تفعيل/تعطيل)
if (isset($_POST['toggle_status'])) {
    $user_id = $_POST['user_id'] ?? 0;
    $new_status = $_POST['new_status'] ?? 0;
    
    if (!empty($user_id)) {
        // التحقق من عدم تغيير حالة المدير الحالي
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_email = $stmt->fetchColumn();
        
        if ($user_email && $user_email !== $email) {
            // تحديث الحالة
            try {
                $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                $stmt->execute([$new_status, $user_id]);
                $success_message = "تم تحديث حالة المستخدم بنجاح";
            } catch (PDOException $e) {
                $error_message = "حدث خطأ أثناء تحديث حالة المستخدم: " . $e->getMessage();
            }
        } else {
            $error_message = "لا يمكنك تغيير حالة حسابك من هنا";
        }
    } else {
        $error_message = "بيانات غير صالحة";
    }
}

// معالجة حذف المستخدم
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'] ?? 0;
    
    if (!empty($user_id)) {
        // التحقق من عدم حذف المدير الحالي
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_email = $stmt->fetchColumn();
        
        if ($user_email && $user_email !== $email) {
            // التحقق من عدم وجود نشاط للمستخدم
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_email = ?");
            $stmt->execute([$user_email]);
            $has_activity = $stmt->fetchColumn() > 0;
            
            if (!$has_activity) {
                // حذف المستخدم
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $success_message = "تم حذف المستخدم بنجاح";
                } catch (PDOException $e) {
                    $error_message = "حدث خطأ أثناء حذف المستخدم: " . $e->getMessage();
                }
            } else {
                $error_message = "لا يمكن حذف هذا المستخدم لأنه لديه نشاط في النظام";
            }
        } else {
            $error_message = "لا يمكنك حذف حسابك الخاص";
        }
    } else {
        $error_message = "بيانات غير صالحة";
    }
}

// معالجة إرسال تنبيه لاستكمال الملف الشخصي
if (isset($_POST['send_profile_notification'])) {
    $user_id = $_POST['user_id'] ?? 0;
    
    if (!empty($user_id)) {
        // الحصول على بريد المستخدم
        $stmt = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // إنشاء إشعار جديد
            try {
                $notification_message = "يرجى استكمال ملفك الشخصي والوثائق المطلوبة.";
                $stmt = $pdo->prepare("INSERT INTO notifications (user_email, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
                $stmt->execute([$user['email'], $notification_message]);
                
                // يمكن إضافة إرسال بريد إلكتروني هنا إذا كان مطلوباً
                
                $success_message = "تم إرسال التنبيه بنجاح إلى " . $user['username'];
            } catch (PDOException $e) {
                $error_message = "حدث خطأ أثناء إرسال التنبيه: " . $e->getMessage();
            }
        } else {
            $error_message = "المستخدم غير موجود";
        }
    } else {
        $error_message = "بيانات غير صالحة";
    }
}

// جلب قائمة الموظفين
try {
    $stmt = $pdo->prepare("SELECT id, username, email, is_active, created_at FROM users WHERE user_role = 'staff'");
    $stmt->execute();
    $staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "حدث خطأ أثناء جلب قائمة الموظفين: " . $e->getMessage();
    $staff_list = [];
}

// جلب قائمة المستخدمين (العملاء)
try {
    $stmt = $pdo->prepare("SELECT id, username, email, is_active, created_at FROM users WHERE user_role = 'user'");
    $stmt->execute();
    $users_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إضافة معلومات إضافية لكل مستخدم
    foreach ($users_list as &$user) {
        // حساب نسبة اكتمال الملف الشخصي - هذا مثال فقط، يجب تعديله حسب هيكل البيانات الفعلي
        try {
            $stmt = $pdo->prepare("SELECT 
                (CASE WHEN phone IS NOT NULL THEN 20 ELSE 0 END) +
                (CASE WHEN address IS NOT NULL THEN 20 ELSE 0 END) +
                (CASE WHEN city IS NOT NULL THEN 20 ELSE 0 END) +
                (CASE WHEN has_documents = 1 THEN 40 ELSE 0 END) AS completion_percentage
                FROM user_profiles WHERE user_email = ?");
            $stmt->execute([$user['email']]);
            $profile_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $user['profile_completion'] = $profile_data ? ($profile_data['completion_percentage'] ?? 0) : 0;
            
            // حالة الوثائق - مثال فقط
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_documents WHERE user_email = ? AND status = 'verified'");
            $stmt->execute([$user['email']]);
            $verified_docs = $stmt->fetchColumn();
            
            $user['docs_complete'] = $verified_docs >= 2; // افتراض أن هناك حاجة لمستندين على الأقل
            
        } catch (PDOException $e) {
            error_log("Error getting user profile data: " . $e->getMessage());
            $user['profile_completion'] = 0;
            $user['docs_complete'] = false;
        }
    }
} catch (PDOException $e) {
    $error_message = "حدث خطأ أثناء جلب قائمة المستخدمين: " . $e->getMessage();
    $users_list = [];
}

// CSS مخصص للصفحة
$page_css = <<<CSS
.container {
  background: rgba(0, 0, 0, 0.7);
  padding: 35px;
  width: 95%;
  max-width: 1200px;
  border-radius: 16px;
  text-align: right;
  margin: 30px auto;
  box-shadow: 0 0 40px rgba(0, 200, 255, 0.15);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(66, 135, 245, 0.25);
}
.page-title {
  text-align: center;
  margin-bottom: 30px;
  color: #1e90ff;
  font-size: 24px;
}
.tab-buttons {
  display: flex;
  justify-content: center;
  gap: 15px;
  margin-bottom: 25px;
}
.tab-button {
  padding: 12px 25px;
  background: linear-gradient(145deg, #1e90ff, #0070cc);
  color: white;
  border: none;
  border-radius: 10px;
  font-weight: bold;
  cursor: pointer;
  transition: 0.3s ease;
  min-width: 150px;
  font-size: 16px;
}
.tab-button:hover {
  background: linear-gradient(145deg, #2eaaff, #0088ff);
  transform: translateY(-3px);
}
.tab-button.active {
  background: linear-gradient(145deg, #ff7300, #cc4e00);
}
.tab-content {
  display: none;
}
.tab-content.active {
  display: block;
}
.card {
  background: rgba(30, 30, 50, 0.7);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 20px;
  border: 1px solid rgba(66, 135, 245, 0.15);
}
.card-title {
  color: #1e90ff;
  font-size: 18px;
  margin-bottom: 15px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  padding-bottom: 10px;
}
.form-group {
  margin-bottom: 15px;
}
.form-group label {
  display: block;
  margin-bottom: 5px;
  color: #a8d8ff;
}
.form-control {
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(30, 144, 255, 0.3);
  border-radius: 8px;
  padding: 10px;
  color: white;
  width: 100%;
  transition: 0.3s;
}
.form-control:focus {
  border-color: #1e90ff;
  outline: none;
  box-shadow: 0 0 0 3px rgba(30, 144, 255, 0.3);
}
.btn {
  padding: 10px 20px;
  border-radius: 8px;
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
.btn-danger {
  background: linear-gradient(145deg, #ff3b30, #cc0000);
  color: white;
}
.btn-danger:hover {
  background: linear-gradient(145deg, #ff524a, #e60000);
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
.btn-action {
  padding: 6px 12px;
  font-size: 14px;
  margin-right: 5px;
}
.table-responsive {
  overflow-x: auto;
  margin-bottom: 20px;
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
  padding: 5px 10px;
  border-radius: 50px;
  font-size: 12px;
  display: inline-block;
}
.badge-success {
  background: rgba(52, 199, 89, 0.3);
  color: #34c759;
  border: 1px solid rgba(52, 199, 89, 0.5);
}
.badge-danger {
  background: rgba(255, 59, 48, 0.3);
  color: #ff3b30;
  border: 1px solid rgba(255, 59, 48, 0.5);
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
  max-width: 500px;
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
.modal-body {
  margin-bottom: 20px;
}
.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
.progress-bar-container {
  height: 8px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
  overflow: hidden;
  width: 100%;
}
.progress-bar {
  height: 100%;
  background: linear-gradient(90deg, #1e90ff, #00c8ff);
  border-radius: 4px;
  transition: width 0.3s ease;
}
.alert {
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
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
.actions {
  white-space: nowrap;
}
CSS;

// JavaScript للصفحة
$page_js = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    // التبديل بين علامات التبويب
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // إزالة الفئة النشطة من جميع الأزرار والمحتويات
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // إضافة الفئة النشطة إلى الزر والمحتوى المحدد
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // تفعيل التبويب الافتراضي
    document.querySelector('.tab-button').click();
    
    // تنشيط الجداول للبحث والترتيب
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.DataTable !== 'undefined') {
        jQuery('#staff-table').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Arabic.json"
            },
            "order": [[3, "desc"]]
        });
        
        jQuery('#users-table').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Arabic.json"
            },
            "order": [[3, "desc"]]
        });
    }
    
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
                
                // تعبئة بيانات المستخدم في النموذج إذا كان متاحاً
                const userId = this.getAttribute('data-user-id');
                const userEmail = this.getAttribute('data-user-email');
                const userName = this.getAttribute('data-user-name');
                
                if (userId) {
                    const userIdInputs = modal.querySelectorAll('.user-id-input');
                    userIdInputs.forEach(input => input.value = userId);
                }
                
                if (userName) {
                    const userNameElements = modal.querySelectorAll('.user-name');
                    userNameElements.forEach(element => element.textContent = userName);
                }
                
                if (userEmail) {
                    const userEmailElements = modal.querySelectorAll('.user-email');
                    userEmailElements.forEach(element => element.textContent = userEmail);
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
    
    // تأكيد حذف المستخدم
    const deleteButtons = document.querySelectorAll('.delete-user-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            if (!confirm('هل أنت متأكد من رغبتك في حذف هذا المستخدم؟ هذا الإجراء لا يمكن التراجع عنه.')) {
                event.preventDefault();
            }
        });
    });
    
    // إغلاق التنبيهات بعد 5 ثوانٍ
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        setTimeout(function() {
            alerts.forEach(alert => {
                alert.style.opacity = '0';
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
    <h1 class="page-title"><?= $display_title ?></h1>
    
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
    
    <div class="tab-buttons">
        <button class="tab-button active" data-tab="staff-tab">👨‍🔧 الموظفين</button>
        <button class="tab-button" data-tab="users-tab">👥 المستخدمين</button>
    </div>
    
    <!-- قسم الموظفين -->
    <div id="staff-tab" class="tab-content active">
        <div class="card">
            <h2 class="card-title">🛠️ إضافة موظف جديد</h2>
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="staff_name">اسم الموظف</label>
                            <input type="text" id="staff_name" name="staff_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="staff_email">البريد الإلكتروني</label>
                            <input type="email" id="staff_email" name="staff_email" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="staff_password">كلمة المرور</label>
                            <input type="password" id="staff_password" name="staff_password" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="form-group" style="text-align: left;">
                    <button type="submit" name="add_staff" class="btn btn-primary">✅ إضافة موظف</button>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h2 class="card-title">👨‍💼 قائمة الموظفين</h2>
            <div class="table-responsive">
                <table id="staff-table" class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الموظف</th>
                            <th>البريد الإلكتروني</th>
                            <th>تاريخ الإنشاء</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_list as $index => $staff): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($staff['username']) ?></td>
                            <td><?= htmlspecialchars($staff['email']) ?></td>
                            <td><?= htmlspecialchars($staff['created_at']) ?></td>
                            <td>
                                <?php if ($staff['is_active']): ?>
                                    <span class="badge badge-success">مفعل</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">معطل</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <?php if ($staff['email'] !== $email): ?>
                                    <button class="btn btn-primary btn-action" 
                                            data-modal="change-password-modal"
                                            data-user-id="<?= $staff['id'] ?>"
                                            data-user-name="<?= htmlspecialchars($staff['username']) ?>"
                                            data-user-email="<?= htmlspecialchars($staff['email']) ?>">
                                        🔑 تغيير كلمة المرور
                                    </button>
                                    
                                    <form method="POST" action="" style="display: inline-block;">
                                        <input type="hidden" name="user_id" value="<?= $staff['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $staff['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" name="toggle_status" class="btn <?= $staff['is_active'] ? 'btn-danger' : 'btn-success' ?> btn-action">
                                            <?= $staff['is_active'] ? '🚫 تعطيل' : '✅ تفعيل' ?>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" action="" style="display: inline-block;">
                                        <input type="hidden" name="user_id" value="<?= $staff['id'] ?>">
                                        <button type="submit" name="delete_user" class="btn btn-danger btn-action delete-user-btn">
                                            ❌ حذف
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge badge-warning">الحساب الحالي</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($staff_list)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">لا يوجد موظفين حالياً</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- قسم المستخدمين (العملاء) -->
    <div id="users-tab" class="tab-content">
        <div class="card">
            <h2 class="card-title">👥 قائمة المستخدمين (العملاء)</h2>
            <div class="table-responsive">
                <table id="users-table" class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم المستخدم</th>
                            <th>البريد الإلكتروني</th>
                            <th>تاريخ التسجيل</th>
                            <th>اكتمال الملف</th>
                            <th>الوثائق</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users_list as $index => $user): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['created_at']) ?></td>
                            <td>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: <?= $user['profile_completion'] ?>%;"></div>
                                </div>
                                <small><?= $user['profile_completion'] ?>%</small>
                            </td>
                            <td>
                                <?php if ($user['docs_complete']): ?>
                                    <span class="badge badge-success">✅ مكتملة</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">❌ ناقصة</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="badge badge-success">مفعل</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">معطل</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <form method="POST" action="" style="display: inline-block;">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= $user['is_active'] ? 0 : 1 ?>">
                                    <button type="submit" name="toggle_status" class="btn <?= $user['is_active'] ? 'btn-danger' : 'btn-success' ?> btn-action">
                                        <?= $user['is_active'] ? '🚫 تعطيل' : '✅ تفعيل' ?>
                                    </button>
                                </form>
                                
                                <button class="btn btn-primary btn-action" 
                                        data-modal="change-password-modal"
                                        data-user-id="<?= $user['id'] ?>"
                                        data-user-name="<?= htmlspecialchars($user['username']) ?>"
                                        data-user-email="<?= htmlspecialchars($user['email']) ?>">
                                    🔑 تغيير كلمة المرور
                                </button>
                                
                                <form method="POST" action="" style="display: inline-block;">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" name="send_profile_notification" class="btn btn-warning btn-action">
                                        📧 تنبيه لإكمال الملف
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users_list)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">لا يوجد مستخدمين حالياً</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- النوافذ المنبثقة -->
    <div id="change-password-modal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">تغيير كلمة المرور</h3>
            <div class="modal-body">
                <p>تغيير كلمة المرور للمستخدم: <strong class="user-name"></strong> (<span class="user-email"></span>)</p>
                <form method="POST" action="" id="change-password-form">
                    <input type="hidden" name="user_id" class="user-id-input">
                    <div class="form-group">
                        <label for="new_password">كلمة المرور الجديدة</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-danger close-modal">إلغاء</button>
                        <button type="submit" name="change_password" class="btn btn-primary">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$page_content = ob_get_clean();

// تضمين كود JavaScript للصفحة
$extra_js = $page_js;

// إدراج القالب
include __DIR__ . '/includes/layout.php';
?>