<?php
// ابدأ الجلسة بأعلى الملف
session_start();

// تضمين ملف الاتصال بقاعدة البيانات
require_once __DIR__ . '/includes/db.php';
// تضمين دوال مساعدة (formatDate() وغيره)
require_once __DIR__ . '/includes/functions.php';
// تضمين الهيدر العام (القائمة العلوية، الروابط…)
require_once __DIR__ . '/includes/header.php';

// ------------------------------------------------------------------
//    التحقق من صلاحيات المشرف
// ------------------------------------------------------------------
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // اذا لم يكن مسجل دخول أو ليس أدمين، أعد التوجيه لصفحة تسجيل الدخول
    header("Location: login.php");
    exit;
}

// ------------------------------------------------------------------
//    حذف مستخدم (عند الطلب عبر الرابط ?delete=ID)
// ------------------------------------------------------------------
$success = '';
$error   = '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int) $_GET['delete'];

    // لا تسمح بحذف نفسك
    if ($delete_id !== (int) $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$delete_id]);
        $success = "✅ تم حذف المستخدم بنجاح.";
    } else {
        $error = "⚠️ لا يمكنك حذف نفسك.";
    }
}

// ------------------------------------------------------------------
//    جلب قائمة المستخدمين للعرض
// ------------------------------------------------------------------
$stmt = $pdo->query("
    SELECT id, username, email, role, created_at
    FROM users
    ORDER BY created_at DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <h2>إدارة المستخدمين</h2>

    <?php
    // عرض رسائل النجاح أو الخطأ
    if ($success) {
        echo "<div class=\"alert alert-success\">$success</div>";
    }
    if ($error) {
        echo "<div class=\"alert alert-danger\">$error</div>";
    }
    ?>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>#</th>
                <th>الاسم</th>
                <th>البريد الإلكتروني</th>
                <th>الصلاحية</th>
                <th>تاريخ الإنشاء</th>
                <th>إجراء</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['username'], ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($u['email'], ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($u['role'], ENT_QUOTES) ?></td>
                <td><?= formatDate($u['created_at']) ?></td>
                <td>
                    <?php if ($u['id'] !== (int) $_SESSION['user_id']): ?>
                        <a href="index.php?delete=<?= $u['id'] ?>"
                           onclick="return confirm('هل أنت متأكد من حذف هذا المستخدم؟');"
                           class="btn btn-sm btn-danger">
                           حذف
                        </a>
                    <?php else: ?>
                        <span class="text-muted">لا يمكن</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
// تضمين الفوتر العام
require_once __DIR__ . '/includes/footer.php';
?>
