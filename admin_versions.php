<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$page_title = "إدارة سجل الإصدارات";
$hide_title = false;

$success = "";
$error = "";

// حفظ البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $version_code = $_POST['version_code'] ?? '';
    $version_type = $_POST['version_type'] ?? 'stable';
    $release_date = $_POST['release_date'] ?? '';
    $summary = $_POST['summary'] ?? '';
    $details = $_POST['details'] ?? '';
    $files = $_POST['files'] ?? '';
    $git_commands = $_POST['git_commands'] ?? '';

    try {
        $stmt = $pdo->prepare("INSERT INTO versions 
            (version_code, version_type, release_date, summary, details, files, git_commands) 
            VALUES (:version_code, :version_type, :release_date, :summary, :details, :files, :git_commands)");
        $stmt->execute([
            'version_code' => $version_code,
            'version_type' => $version_type,
            'release_date' => $release_date,
            'summary' => $summary,
            'details' => $details,
            'files' => $files,
            'git_commands' => $git_commands,
        ]);
        $success = "✅ تم حفظ الإصدار بنجاح.";
    } catch (PDOException $e) {
        $error = "❌ حدث خطأ أثناء الحفظ: " . $e->getMessage();
    }
}

// جلب جميع الإصدارات
$stmt = $pdo->query("SELECT * FROM versions ORDER BY release_date DESC, id DESC");
$versions = $stmt->fetchAll();

ob_start();
?>

<div class="form-card">
    <h2>إضافة إصدار جديد</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label>رقم الإصدار</label>
            <input type="text" name="version_code" class="form-control" placeholder="مثل: v1.1.0" required>
        </div>

        <div class="form-group">
            <label>نوع الإصدار</label>
            <select name="version_type" class="form-select">
                <option value="stable">مستقر</option>
                <option value="latest">أحدث إصدار</option>
                <option value="beta">نسخة تجريبية</option>
                <option value="alpha">نموذج أولي</option>
                <option value="patch">تصحيح فرعي</option>
            </select>
        </div>

        <div class="form-group">
            <label>تاريخ الإصدار</label>
            <input type="date" name="release_date" class="form-control" required>
        </div>

        <div class="form-group">
            <label>الملخص العام</label>
            <textarea name="summary" class="form-control" rows="3" placeholder="ملخص سريع للإصدار..." required></textarea>
        </div>

        <div class="form-group">
            <label>تفاصيل الإصدار (قائمة نقاط)</label>
            <textarea name="details" class="form-control" rows="5" placeholder="- تحسين تصميم الصفحة\n- إصلاح مشاكل في النموذج" required></textarea>
        </div>

        <div class="form-group">
            <label>الملفات المرتبطة</label>
            <input type="text" name="files" class="form-control" placeholder="مثال: ecu-tuning.php, layout.php">
        </div>

        <div class="form-group">
            <label>أوامر Git (اختياري)</label>
            <textarea name="git_commands" class="form-control" rows="3" placeholder="git add .&#10;git commit -m &quot;v1.1.0: تحديثات جديدة&quot;"></textarea>
        </div>

        <button type="submit" class="submit-btn">📌 حفظ الإصدار</button>
    </form>
</div>

<hr>

<div class="form-card">
    <h2>جميع الإصدارات</h2>
    <table class="table">
        <thead>
            <tr>
                <th>الرقم</th>
                <th>النسخة</th>
                <th>النوع</th>
                <th>التاريخ</th>
                <th>الوصف</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($versions as $v): ?>
                <tr>
                    <td>#<?= $v['id'] ?></td>
                    <td><?= htmlspecialchars($v['version_code']) ?></td>
                    <td><?= $v['version_type'] ?></td>
                    <td><?= $v['release_date'] ?></td>
                    <td><?= nl2br(htmlspecialchars($v['summary'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
$page_content = ob_get_clean();
$page_css = ''; // يمكن لاحقًا تخصيص CSS إضافي
require_once __DIR__ . '/includes/layout.php';
?>
