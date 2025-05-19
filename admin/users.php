<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// التحقق من صلاحيات المشرف
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// حذف مستخدم إذا تم الضغط على زر الحذف
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = $_GET['delete'];

    // لا تسمح بحذف المستخدم نفسه
    if ($delete_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$delete_id]);
        $success = "تم حذف المستخدم بنجاح.";
    } else {
        $error = "لا يمكنك حذف نفسك.";
    }
}

// جلب المستخدمين
$stmt = $pdo->prepare("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>إدارة المستخدمين</h2>

<?php
if (!empty($success)) showMessage("success", $success);
if (!empty($error)) showMessage("danger", $error);
?>

<table border="1" cellpadding="10" cellspacing="0" width="100%">
    <tr>
        <th>الرقم</th>
        <th>الاسم</th>
        <th>البريد الإلكتروني</th>
        <th>الصلاحية</th>
        <th>تاريخ الإنشاء</th>
        <th>الإجراء</th>
    </tr>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?= $user['id']; ?></td>
            <td><?= htmlspecialchars($user['username']); ?></td>
            <td><?= htmlspecialchars($user['email']); ?></td>
            <td><?= $user['role']; ?></td>
            <td><?= formatDate($user['created_at']); ?></td>
            <td>
                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                    <a href="users.php?delete=<?= $user['id']; ?>" onclick="return confirm('هل أنت متأكد أنك تريد حذف هذا المستخدم؟');">🗑️ حذف</a>
                <?php else: ?>
                    <span style="color: gray;">لا يمكن الحذف</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php require_once '../includes/footer.php'; ?>
