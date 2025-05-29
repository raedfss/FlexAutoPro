<?php
session_start();
if (empty($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

// إعدادات الصفحة
$page_title = "إدارة سجل الإصدارات";
$hide_title = false;

// تضمين اتصال قاعدة البيانات
require_once __DIR__ . '/includes/db_connection.php';

// التعامل مع طلبات الإضافة أو التحديث أو الحذف
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // عملية إضافة إصدار جديد
        if ($_POST['action'] === 'add') {
            $version_number = mysqli_real_escape_string($conn, $_POST['version_number']);
            $release_date = mysqli_real_escape_string($conn, $_POST['release_date']);
            $version_type = mysqli_real_escape_string($conn, $_POST['version_type']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            $summary = mysqli_real_escape_string($conn, $_POST['summary']);
            $details = mysqli_real_escape_string($conn, $_POST['details']);
            $files_changed = mysqli_real_escape_string($conn, $_POST['files_changed']);
            $git_commands = mysqli_real_escape_string($conn, $_POST['git_commands']);
            
            $sql = "INSERT INTO versions (version_number, release_date, version_type, status, summary, details, files_changed, git_commands) 
                    VALUES ('$version_number', '$release_date', '$version_type', '$status', '$summary', '$details', '$files_changed', '$git_commands')";
            
            if (mysqli_query($conn, $sql)) {
                $_SESSION['success_message'] = "تم إضافة الإصدار بنجاح";
            } else {
                $_SESSION['error_message'] = "حدث خطأ أثناء إضافة الإصدار: " . mysqli_error($conn);
            }
        }
        // عملية تحديث إصدار موجود
        else if ($_POST['action'] === 'update') {
            $version_id = mysqli_real_escape_string($conn, $_POST['version_id']);
            $version_number = mysqli_real_escape_string($conn, $_POST['version_number']);
            $release_date = mysqli_real_escape_string($conn, $_POST['release_date']);
            $version_type = mysqli_real_escape_string($conn, $_POST['version_type']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            $summary = mysqli_real_escape_string($conn, $_POST['summary']);
            $details = mysqli_real_escape_string($conn, $_POST['details']);
            $files_changed = mysqli_real_escape_string($conn, $_POST['files_changed']);
            $git_commands = mysqli_real_escape_string($conn, $_POST['git_commands']);
            
            $sql = "UPDATE versions SET 
                    version_number = '$version_number', 
                    release_date = '$release_date', 
                    version_type = '$version_type', 
                    status = '$status', 
                    summary = '$summary', 
                    details = '$details', 
                    files_changed = '$files_changed', 
                    git_commands = '$git_commands' 
                    WHERE id = $version_id";
            
            if (mysqli_query($conn, $sql)) {
                $_SESSION['success_message'] = "تم تحديث الإصدار بنجاح";
            } else {
                $_SESSION['error_message'] = "حدث خطأ أثناء تحديث الإصدار: " . mysqli_error($conn);
            }
        }
        // عملية حذف إصدار
        else if ($_POST['action'] === 'delete') {
            $version_id = mysqli_real_escape_string($conn, $_POST['version_id']);
            
            $sql = "DELETE FROM versions WHERE id = $version_id";
            
            if (mysqli_query($conn, $sql)) {
                $_SESSION['success_message'] = "تم حذف الإصدار بنجاح";
            } else {
                $_SESSION['error_message'] = "حدث خطأ أثناء حذف الإصدار: " . mysqli_error($conn);
            }
        }
        
        // إعادة توجيه لتفادي إعادة الإرسال عند تحديث الصفحة
        header("Location: admin_versions.php");
        exit;
    }
}

// جلب جميع الإصدارات
$sql = "SELECT * FROM versions ORDER BY CAST(version_number AS DECIMAL(10,2)) DESC";
$result = mysqli_query($conn, $sql);
$versions = [];

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $versions[] = $row;
    }
}

// تحديد التنسيقات الخاصة بالصفحة
$page_css = '
<style>
    .versions-admin-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .admin-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding-bottom: 15px;
    }
    
    .admin-header h1 {
        color: #00d9ff;
        font-size: 24px;
        margin: 0;
    }
    
    .btn-add-version {
        background-color: #00d9ff;
        color: #0f172a;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-add-version:hover {
        background-color: #00c2e6;
        transform: translateY(-2px);
    }
    
    .versions-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background-color: rgba(30, 41, 59, 0.5);
        border-radius: 8px;
        overflow: hidden;
    }
    
    .versions-table th, .versions-table td {
        padding: 12px 15px;
        text-align: right;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .versions-table th {
        background-color: rgba(15, 23, 42, 0.8);
        color: #00d9ff;
        font-weight: 600;
    }
    
    .versions-table tr:last-child td {
        border-bottom: none;
    }
    
    .versions-table tr:hover {
        background-color: rgba(15, 23, 42, 0.5);
    }
    
    .badge {
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .badge-major {
        background-color: rgba(0, 217, 255, 0.15);
        color: #00d9ff;
    }
    
    .badge-minor {
        background-color: rgba(255, 204, 0, 0.15);
        color: #ffcc00;
    }
    
    .badge-patch {
        background-color: rgba(148, 82, 255, 0.15);
        color: #9452ff;
    }
    
    .badge-stable {
        background-color: rgba(0, 255, 136, 0.15);
        color: #00ff88;
    }
    
    .badge-beta {
        background-color: rgba(255, 107, 107, 0.15);
        color: #ff6b6b;
    }
    
    .badge-alpha {
        background-color: rgba(148, 82, 255, 0.15);
        color: #9452ff;
    }
    
    .badge-latest {
        background-color: rgba(255, 204, 0, 0.15);
        color: #ffcc00;
    }
    
    .actions-cell {
        display: flex;
        gap: 8px;
        justify-content: center;
    }
    
    .btn-edit, .btn-delete {
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        border: none;
        transition: all 0.2s;
    }
    
    .btn-edit {
        background-color: rgba(0, 217, 255, 0.15);
        color: #00d9ff;
        border: 1px solid rgba(0, 217, 255, 0.3);
    }
    
    .btn-delete {
        background-color: rgba(255, 107, 107, 0.15);
        color: #ff6b6b;
        border: 1px solid rgba(255, 107, 107, 0.3);
    }
    
    .btn-edit:hover, .btn-delete:hover {
        transform: translateY(-2px);
    }
    
    /* تنسيق النموذج المنبثق */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.7);
    }
    
    .modal-content {
        background-color: #1e293b;
        margin: 5% auto;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        width: 80%;
        max-width: 800px;
        max-height: 85vh;
        overflow-y: auto;
        direction: rtl;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding-bottom: 15px;
    }
    
    .modal-header h2 {
        color: #00d9ff;
        margin: 0;
        font-size: 20px;
    }
    
    .close {
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .close:hover {
        color: #00d9ff;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        color: #e2e8f0;
        font-weight: 500;
    }
    
    .form-control {
        width: 100%;
        padding: 10px;
        border-radius: 4px;
        background-color: #0f172a;
        border: 1px solid #2d3748;
        color: #e2e8f0;
        font-family: inherit;
        font-size: 14px;
    }
    
    .form-control:focus {
        border-color: #00d9ff;
        outline: none;
        box-shadow: 0 0 0 2px rgba(0, 217, 255, 0.2);
    }
    
    textarea.form-control {
        min-height: 100px;
        resize: vertical;
    }
    
    .full-width {
        grid-column: 1 / -1;
    }
    
    .btn-submit {
        background-color: #00d9ff;
        color: #0f172a;
        border: none;
        padding: 12px 20px;
        border-radius: 4px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        width: 100%;
        margin-top: 20px;
    }
    
    .btn-submit:hover {
        background-color: #00c2e6;
    }
    
    .alert {
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .alert-success {
        background-color: rgba(0, 255, 136, 0.15);
        color: #00ff88;
        border: 1px solid rgba(0, 255, 136, 0.3);
    }
    
    .alert-error {
        background-color: rgba(255, 107, 107, 0.15);
        color: #ff6b6b;
        border: 1px solid rgba(255, 107, 107, 0.3);
    }
    
    .confirm-delete-modal {
        max-width: 400px;
        text-align: center;
    }
    
    .confirm-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin-top: 20px;
    }
    
    .btn-cancel {
        background-color: #334155;
        color: #e2e8f0;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-confirm-delete {
        background-color: #ff6b6b;
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-cancel:hover, .btn-confirm-delete:hover {
        opacity: 0.9;
    }
    
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .modal-content {
            width: 95%;
            margin: 10% auto;
        }
        
        .versions-table {
            font-size: 14px;
        }
        
        .versions-table th, .versions-table td {
            padding: 8px 10px;
        }
        
        .actions-cell {
            flex-direction: column;
            gap: 5px;
        }
    }
</style>';

// محتوى الصفحة
ob_start();
?>

<div class="versions-admin-container">
    <div class="admin-header">
        <h1>إدارة سجل الإصدارات - مشروع فلكس أوتو</h1>
        <button class="btn-add-version" id="btnAddVersion">إضافة إصدار جديد</button>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success_message']; 
            unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <?php 
            echo $_SESSION['error_message']; 
            unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <table class="versions-table">
        <thead>
            <tr>
                <th>رقم الإصدار</th>
                <th>تاريخ الإصدار</th>
                <th>النوع</th>
                <th>الحالة</th>
                <th>ملخص</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($versions)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">لا توجد إصدارات لعرضها</td>
                </tr>
            <?php else: ?>
                <?php foreach ($versions as $version): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($version['version_number']); ?></td>
                        <td><?php echo htmlspecialchars($version['release_date']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo strtolower($version['version_type']); ?>">
                                <?php echo htmlspecialchars($version['version_type']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo strtolower($version['status']); ?>">
                                <?php echo htmlspecialchars($version['status']); ?>
                            </span>
                        </td>
                        <td><?php echo mb_substr(htmlspecialchars($version['summary']), 0, 50) . (mb_strlen($version['summary']) > 50 ? '...' : ''); ?></td>
                        <td class="actions-cell">
                            <button class="btn-edit" onclick="editVersion(<?php echo $version['id']; ?>)">تعديل</button>
                            <button class="btn-delete" onclick="confirmDelete(<?php echo $version['id']; ?>, '<?php echo htmlspecialchars($version['version_number']); ?>')">حذف</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- نموذج إضافة/تعديل إصدار -->
    <div id="versionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">إضافة إصدار جديد</h2>
                <span class="close">&times;</span>
            </div>
            
            <form id="versionForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="version_id" id="versionId" value="">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="version_number">رقم الإصدار</label>
                        <input type="text" class="form-control" id="version_number" name="version_number" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="release_date">تاريخ الإصدار</label>
                        <input type="date" class="form-control" id="release_date" name="release_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="version_type">نوع الإصدار</label>
                        <select class="form-control" id="version_type" name="version_type" required>
                            <option value="Major">إصدار رئيسي (Major)</option>
                            <option value="Minor">إصدار ثانوي (Minor)</option>
                            <option value="Patch">تحديث (Patch)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">حالة الإصدار</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="Stable">مستقر (Stable)</option>
                            <option value="Latest">أحدث إصدار (Latest)</option>
                            <option value="Beta">تجريبي (Beta)</option>
                            <option value="Alpha">نموذج أولي (Alpha)</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="summary">ملخص الإصدار</label>
                        <textarea class="form-control" id="summary" name="summary" required></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="details">تفاصيل الإصدار (استخدم * للعناصر)</label>
                        <textarea class="form-control" id="details" name="details" rows="5" required></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="files_changed">الملفات المتغيرة</label>
                        <textarea class="form-control" id="files_changed" name="files_changed"></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="git_commands">أوامر Git (اختياري)</label>
                        <textarea class="form-control" id="git_commands" name="git_commands"></textarea>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit" id="btnSubmit">حفظ الإصدار</button>
            </form>
        </div>
    </div>
    
    <!-- نموذج تأكيد الحذف -->
    <div id="deleteModal" class="modal">
        <div class="modal-content confirm-delete-modal">
            <div class="modal-header">
                <h2>تأكيد الحذف</h2>
                <span class="close">&times;</span>
            </div>
            
            <p>هل أنت متأكد من رغبتك في حذف الإصدار <span id="deleteVersionNumber"></span>؟</p>
            <p>لا يمكن التراجع عن هذا الإجراء.</p>
            
            <form id="deleteForm" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="version_id" id="deleteVersionId" value="">
                
                <div class="confirm-actions">
                    <button type="button" class="btn-cancel" id="btnCancelDelete">إلغاء</button>
                    <button type="submit" class="btn-confirm-delete">تأكيد الحذف</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // استدعاء النموذج عند النقر على زر الإضافة
    document.getElementById('btnAddVersion').addEventListener('click', function() {
        document.getElementById('modalTitle').textContent = 'إضافة إصدار جديد';
        document.getElementById('formAction').value = 'add';
        document.getElementById('versionForm').reset();
        document.getElementById('versionId').value = '';
        
        // تعيين تاريخ اليوم كقيمة افتراضية
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('release_date').value = today;
        
        document.getElementById('versionModal').style.display = 'block';
    });
    
    // إغلاق النماذج المنبثقة
    const closeButtons = document.querySelectorAll('.close');
    closeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('versionModal').style.display = 'none';
            document.getElementById('deleteModal').style.display = 'none';
        });
    });
    
    // إغلاق النموذج عند النقر خارجه
    window.addEventListener('click', function(event) {
        if (event.target === document.getElementById('versionModal')) {
            document.getElementById('versionModal').style.display = 'none';
        }
        if (event.target === document.getElementById('deleteModal')) {
            document.getElementById('deleteModal').style.display = 'none';
        }
    });
    
    // تحديد بيانات الإصدار للتعديل
    function editVersion(versionId) {
        // طلب AJAX لجلب بيانات الإصدار
        fetch('get_version.php?id=' + versionId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const version = data.version;
                    
                    document.getElementById('modalTitle').textContent = 'تعديل الإصدار ' + version.version_number;
                    document.getElementById('formAction').value = 'update';
                    document.getElementById('versionId').value = version.id;
                    document.getElementById('version_number').value = version.version_number;
                    document.getElementById('release_date').value = version.release_date;
                    document.getElementById('version_type').value = version.version_type;
                    document.getElementById('status').value = version.status;
                    document.getElementById('summary').value = version.summary;
                    document.getElementById('details').value = version.details;
                    document.getElementById('files_changed').value = version.files_changed;
                    document.getElementById('git_commands').value = version.git_commands;
                    
                    document.getElementById('versionModal').style.display = 'block';
                } else {
                    alert('حدث خطأ أثناء جلب بيانات الإصدار');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ في الاتصال بالخادم');
            });
    }
    
    // تأكيد حذف الإصدار
    function confirmDelete(versionId, versionNumber) {
        document.getElementById('deleteVersionId').value = versionId;
        document.getElementById('deleteVersionNumber').textContent = versionNumber;
        document.getElementById('deleteModal').style.display = 'block';
    }
    
    // إلغاء الحذف
    document.getElementById('btnCancelDelete').addEventListener('click', function() {
        document.getElementById('deleteModal').style.display = 'none';
    });
    
    // التحقق من الإدخالات قبل الإرسال
    document.getElementById('versionForm').addEventListener('submit', function(event) {
        const version_number = document.getElementById('version_number').value;
        const summary = document.getElementById('summary').value;
        const details = document.getElementById('details').value;
        
        if (!version_number || !summary || !details) {
            event.preventDefault();
            alert('يرجى ملء جميع الحقول المطلوبة');
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/includes/layout.php';
?>