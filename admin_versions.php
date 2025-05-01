<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// التحقق من تسجيل الدخول وصلاحيات المدير
if (!isset($_SESSION['email']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

// إعدادات الصفحة
$page_title = 'إدارة سجل الإصدارات';
$hide_title = false;

// معالجة الإضافة أو التعديل أو الحذف
$success_message = '';
$error_message = '';

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            // إضافة إصدار جديد
            if ($_POST['action'] === 'add') {
                $stmt = $pdo->prepare("INSERT INTO versions (
                    version_number, release_date, version_type, status, summary, details, affected_files, git_commands
                ) VALUES (
                    :version_number, :release_date, :version_type, :status, :summary, :details, :affected_files, :git_commands
                )");
                
                $stmt->execute([
                    'version_number' => $_POST['version_number'],
                    'release_date' => $_POST['release_date'],
                    'version_type' => $_POST['version_type'],
                    'status' => $_POST['status'],
                    'summary' => $_POST['summary'],
                    'details' => $_POST['details'],
                    'affected_files' => $_POST['affected_files'],
                    'git_commands' => $_POST['git_commands']
                ]);
                
                $success_message = "تمت إضافة الإصدار بنجاح";
            }
            
            // تعديل إصدار موجود
            else if ($_POST['action'] === 'edit' && isset($_POST['id'])) {
                $stmt = $pdo->prepare("UPDATE versions SET
                    version_number = :version_number,
                    release_date = :release_date,
                    version_type = :version_type,
                    status = :status,
                    summary = :summary,
                    details = :details,
                    affected_files = :affected_files,
                    git_commands = :git_commands,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id");
                
                $stmt->execute([
                    'id' => $_POST['id'],
                    'version_number' => $_POST['version_number'],
                    'release_date' => $_POST['release_date'],
                    'version_type' => $_POST['version_type'],
                    'status' => $_POST['status'],
                    'summary' => $_POST['summary'],
                    'details' => $_POST['details'],
                    'affected_files' => $_POST['affected_files'],
                    'git_commands' => $_POST['git_commands']
                ]);
                
                $success_message = "تم تعديل الإصدار بنجاح";
            }
            
            // حذف إصدار
            else if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
                $stmt = $pdo->prepare("DELETE FROM versions WHERE id = :id");
                $stmt->execute(['id' => $_POST['id']]);
                
                $success_message = "تم حذف الإصدار بنجاح";
            }
        } catch (PDOException $e) {
            $error_message = "حدث خطأ أثناء معالجة الطلب: " . $e->getMessage();
        }
    }
}

// استعلام عن الإصدارات المخزنة
try {
    $stmt = $pdo->query("SELECT * FROM versions ORDER BY release_date DESC, version_number DESC");
    $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // إذا كان الخطأ بسبب عدم وجود الجدول، قم بإنشائه
    if (strpos($e->getMessage(), 'relation "versions" does not exist') !== false) {
        try {
            // إنشاء جدول الإصدارات
            $pdo->exec("CREATE TABLE versions (
                id SERIAL PRIMARY KEY,
                version_number VARCHAR(20) NOT NULL,
                release_date DATE NOT NULL,
                version_type VARCHAR(20) NOT NULL DEFAULT 'major',
                status VARCHAR(20) NOT NULL DEFAULT 'stable',
                summary TEXT NOT NULL,
                details TEXT NOT NULL,
                affected_files TEXT,
                git_commands TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            // إضافة بعض البيانات الافتراضية
            $stmt = $pdo->prepare("INSERT INTO versions 
                (version_number, release_date, version_type, status, summary, details) 
                VALUES 
                ('v1.1.0', CURRENT_DATE, 'major', 'stable', 'نسخة مستقرة جديدة مع تحسينات شاملة', 'تنظيم الكود وتحسين نماذج التذاكر، صفحة ECU الجديدة، التحقق من إدخال البيانات'),
                ('v1.0.2', CURRENT_DATE - INTERVAL '7 days', 'minor', 'latest', 'تحديث صفحة key-code.php بالكامل', 'إعادة تنظيم الكود، تحسين التصميم والرسائل الظاهرة')");
            $stmt->execute();
            
            $success_message = "تم إنشاء جدول الإصدارات بنجاح وإضافة بيانات افتراضية.";
            
            // محاولة استرداد البيانات مرة أخرى
            $stmt = $pdo->query("SELECT * FROM versions ORDER BY release_date DESC, version_number DESC");
            $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) {
            $error_message = "حدث خطأ أثناء إنشاء الجدول: " . $e2->getMessage();
            $versions = [];
        }
    } else {
        $error_message = "حدث خطأ أثناء استرجاع البيانات: " . $e->getMessage();
        $versions = [];
    }
}

// دالة لعرض أسماء أنواع الإصدارات بالعربية
function getVersionTypeName($type) {
    $types = [
        'major' => 'رئيسي',
        'minor' => 'ثانوي',
        'patch' => 'ترقيع'
    ];
    return $types[$type] ?? $type;
}

// دالة لعرض أسماء حالات الإصدارات بالعربية
function getStatusName($status) {
    $statuses = [
        'stable' => 'مستقر',
        'latest' => 'أحدث',
        'beta' => 'تجريبي',
        'alpha' => 'مبدئي'
    ];
    return $statuses[$status] ?? $status;
}

// تحديد CSS الخاص بالصفحة
$page_css = '
<style>
    .admin-versions-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .admin-versions-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .admin-versions-header h1 {
        margin: 0;
        color: #00d9ff;
    }
    
    .add-version-btn {
        background: linear-gradient(145deg, #00d9ff, #0056b3);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: bold;
        transition: all 0.3s;
    }
    
    .add-version-btn:hover {
        background: linear-gradient(145deg, #00d9ff, #003c80);
        transform: translateY(-2px);
    }
    
    .versions-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .versions-table th, .versions-table td {
        padding: 12px 15px;
        text-align: right;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .versions-table th {
        background-color: #1a2234;
        color: #00d9ff;
        font-weight: bold;
    }
    
    .versions-table tr:hover {
        background-color: rgba(0, 217, 255, 0.05);
    }
    
    .versions-table .status {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .status-stable {
        background-color: rgba(0, 255, 136, 0.15);
        color: #00ff88;
    }
    
    .status-latest {
        background-color: rgba(255, 204, 0, 0.15);
        color: #ffcc00;
    }
    
    .status-beta {
        background-color: rgba(255, 107, 107, 0.15);
        color: #ff6b6b;
    }
    
    .status-alpha {
        background-color: rgba(148, 82, 255, 0.15);
        color: #9452ff;
    }
    
    .type-major {
        background-color: rgba(0, 217, 255, 0.15);
        color: #00d9ff;
    }
    
    .type-minor {
        background-color: rgba(255, 204, 0, 0.15);
        color: #ffcc00;
    }
    
    .type-patch {
        background-color: rgba(148, 82, 255, 0.15);
        color: #9452ff;
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
    }
    
    .edit-btn, .delete-btn {
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.3s;
    }
    
    .edit-btn {
        background-color: rgba(0, 217, 255, 0.2);
        color: #00d9ff;
    }
    
    .delete-btn {
        background-color: rgba(255, 107, 107, 0.2);
        color: #ff6b6b;
    }
    
    .edit-btn:hover {
        background-color: rgba(0, 217, 255, 0.4);
    }
    
    .delete-btn:hover {
        background-color: rgba(255, 107, 107, 0.4);
    }
    
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 5px;
    }
    
    .alert-success {
        background-color: rgba(0, 255, 136, 0.1);
        color: #00ff88;
        border-right: 4px solid #00ff88;
    }
    
    .alert-error {
        background-color: rgba(255, 107, 107, 0.1);
        color: #ff6b6b;
        border-right: 4px solid #ff6b6b;
    }
    
    /* نموذج الإضافة/التعديل */
    .version-form-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.8);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }
    
    .version-form {
        background-color: #1e293b;
        padding: 25px;
        border-radius: 10px;
        width: 90%;
        max-width: 800px;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .version-form h2 {
        color: #00d9ff;
        margin-top: 0;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: #f8fafc;
    }
    
    .form-control {
        width: 100%;
        padding: 12px;
        background-color: #0f172a;
        border: 1px solid #2d3748;
        border-radius: 5px;
        color: #f8fafc;
        font-size: 16px;
    }
    
    textarea.form-control {
        min-height: 100px;
        resize: vertical;
    }
    
    .form-row {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .form-col {
        flex: 1;
    }
    
    .form-actions {
        display: flex;
        justify-content: space-between;
        margin-top: 30px;
    }
    
    .form-actions button {
        padding: 12px 25px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: bold;
        transition: all 0.3s;
    }
    
    .save-btn {
        background: linear-gradient(145deg, #00d9ff, #0056b3);
        color: white;
    }
    
    .cancel-btn {
        background-color: #2d3748;
        color: #f8fafc;
    }
    
    .save-btn:hover {
        background: linear-gradient(145deg, #00d9ff, #003c80);
    }
    
    .cancel-btn:hover {
        background-color: #3e4c6a;
    }
    
    .back-link {
        margin-top: 30px;
        text-align: center;
    }
    
    .back-link a {
        display: inline-block;
        background-color: #1e293b;
        color: #f8fafc;
        padding: 10px 20px;
        border-radius: 5px;
        transition: all 0.3s;
        text-decoration: none;
        border: 1px solid #2d3748;
    }
    
    .back-link a:hover {
        background-color: #2d3748;
        transform: translateY(-2px);
    }
</style>';

// محتوى الصفحة
ob_start();
?>

<div class="admin-versions-container">
    <div class="admin-versions-header">
        <h1>إدارة سجل الإصدارات</h1>
        <button class="add-version-btn" onclick="showVersionForm()">+ إضافة إصدار جديد</button>
    </div>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?= $success_message ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-error"><?= $error_message ?></div>
    <?php endif; ?>
    
    <table class="versions-table">
        <thead>
            <tr>
                <th>رقم الإصدار</th>
                <th>تاريخ الإصدار</th>
                <th>النوع</th>
                <th>الحالة</th>
                <th>ملخص</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($versions)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">لا توجد إصدارات مسجلة بعد</td>
                </tr>
            <?php else: ?>
                <?php foreach ($versions as $version): ?>
                    <tr data-id="<?= $version['id'] ?>">
                        <td><?= htmlspecialchars($version['version_number']) ?></td>
                        <td><?= date('Y-m-d', strtotime($version['release_date'])) ?></td>
                        <td><span class="status type-<?= $version['version_type'] ?>"><?= getVersionTypeName($version['version_type']) ?></span></td>
                        <td><span class="status status-<?= $version['status'] ?>"><?= getStatusName($version['status']) ?></span></td>
                        <td><?= htmlspecialchars(mb_substr($version['summary'], 0, 50)) . (mb_strlen($version['summary']) > 50 ? '...' : '') ?></td>
                        <td class="action-buttons">
                            <button class="edit-btn" onclick="editVersion(<?= $version['id'] ?>)">تعديل</button>
                            <button class="delete-btn" onclick="deleteVersion(<?= $version['id'] ?>, '<?= htmlspecialchars($version['version_number']) ?>')">حذف</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="back-link">
        <a href="home.php">العودة للصفحة الرئيسية</a>
    </div>
</div>

<!-- نموذج إضافة/تعديل الإصدار -->
<div class="version-form-overlay" id="versionFormOverlay">
    <div class="version-form">
        <h2 id="formTitle">إضافة إصدار جديد</h2>
        <form id="versionForm" method="POST" action="">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="versionId" value="">
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="version_number">رقم الإصدار</label>
                        <input type="text" class="form-control" id="version_number" name="version_number" placeholder="مثال: v1.2.0" required>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="release_date">تاريخ الإصدار</label>
                        <input type="date" class="form-control" id="release_date" name="release_date" required>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="version_type">نوع الإصدار</label>
                        <select class="form-control" id="version_type" name="version_type" required>
                            <option value="major">رئيسي (Major)</option>
                            <option value="minor">ثانوي (Minor)</option>
                            <option value="patch">ترقيع (Patch)</option>
                        </select>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="status">حالة الإصدار</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="stable">مستقر</option>
                            <option value="latest">أحدث إصدار</option>
                            <option value="beta">تجريبي (بيتا)</option>
                            <option value="alpha">مبدئي (ألفا)</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="summary">ملخص الإصدار</label>
                <input type="text" class="form-control" id="summary" name="summary" placeholder="وصف مختصر للإصدار" required>
            </div>
            
            <div class="form-group">
                <label for="details">تفاصيل الإصدار</label>
                <textarea class="form-control" id="details" name="details" placeholder="تفاصيل التغييرات والإضافات في هذا الإصدار" rows="5" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="affected_files">الملفات المتأثرة</label>
                <textarea class="form-control" id="affected_files" name="affected_files" placeholder="قائمة بالملفات التي تم تعديلها أو إضافتها" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label for="git_commands">أوامر Git</label>
                <textarea class="form-control" id="git_commands" name="git_commands" placeholder="أوامر Git المستخدمة لهذا الإصدار" rows="4"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="cancel-btn" onclick="hideVersionForm()">إلغاء</button>
                <button type="submit" class="save-btn" id="saveButton">حفظ الإصدار</button>
            </div>
        </form>
    </div>
</div>

<!-- نموذج حذف الإصدار -->
<form id="deleteForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId" value="">
</form>

<script>
    // تعيين تاريخ اليوم كقيمة افتراضية لحقل التاريخ
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('release_date').value = today;
    });
    
    // عرض نموذج إضافة إصدار جديد
    function showVersionForm() {
        document.getElementById('versionFormOverlay').style.display = 'flex';
        document.getElementById('formTitle').textContent = 'إضافة إصدار جديد';
        document.getElementById('formAction').value = 'add';
        document.getElementById('versionForm').reset();
        
        // تعيين تاريخ اليوم
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('release_date').value = today;
    }
    
    // إخفاء النموذج
    function hideVersionForm() {
        document.getElementById('versionFormOverlay').style.display = 'none';
    }
    
    // عرض نموذج تعديل إصدار
    function editVersion(id) {
        // بيانات الصف في الجدول
        const row = document.querySelector(`tr[data-id="${id}"]`);
        if (!row) {
            alert('حدث خطأ أثناء استرجاع بيانات الإصدار');
            return;
        }
        
        // تعبئة النموذج من البيانات الموجودة في الصف
        document.getElementById('versionId').value = id;
        document.getElementById('version_number').value = row.querySelector('td:nth-child(1)').textContent.trim();
        document.getElementById('release_date').value = row.querySelector('td:nth-child(2)').textContent.trim();
        
        // استخلاص نوع الإصدار من الكلاس
        const typeSpan = row.querySelector('td:nth-child(3) .status');
        const typeClass = typeSpan.className.match(/type-(\w+)/)[1];
        document.getElementById('version_type').value = typeClass;
        
        // استخلاص حالة الإصدار من الكلاس
        const statusSpan = row.querySelector('td:nth-child(4) .status');
        const statusClass = statusSpan.className.match(/status-(\w+)/)[1];
        document.getElementById('status').value = statusClass;
        
        // محاولة الحصول على البيانات الإضافية عبر AJAX
        fetch(`get_version.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error:', data.error);
                    // لا نعمل شيء، سنستخدم البيانات المتاحة بالفعل
                } else {
                    // تعبئة البيانات الإضافية
                    document.getElementById('summary').value = data.summary || '';
                    document.getElementById('details').value = data.details || '';
                    document.getElementById('affected_files').value = data.affected_files || '';
                    document.getElementById('git_commands').value = data.git_commands || '';
                }
            })
            .catch(error => {
                console.error('Error fetching version details:', error);
                // في حالة فشل الحصول على البيانات، نستخدم البيانات المتاحة
                document.getElementById('summary').value = row.querySelector('td:nth-child(5)').textContent.trim();
            });
        
        // عنوان النموذج وإجراء النموذج
        document.getElementById('formTitle').textContent = 'تعديل الإصدار';
        document.getElementById('formAction').value = 'edit';
        
        // عرض النموذج
        document.getElementById('versionFormOverlay').style.display = 'flex';
    }
    
    // حذف إصدار
    function deleteVersion(id, versionNumber) {
        if (confirm(`هل أنت متأكد من حذف الإصدار ${versionNumber}؟`)) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteForm').submit();
        }
    }
    
    // إغلاق النموذج عند النقر خارجه
    document.getElementById('versionFormOverlay').addEventListener('click', function(e) {
        if (e.target === this) {
            hideVersionForm();
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/includes/layout.php';
?>