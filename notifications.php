<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$user_id = $_SESSION['user_id'];

// جلب إشعارات المستخدم
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>الإشعارات</h2>

<?php if (count($notifications) === 0): ?>
    <p>لا توجد إشعارات حتى الآن.</p>
<?php else: ?>
    <ul class="notifications">
        <?php foreach ($notifications as $note): ?>
            <li style="margin-bottom: 15px;">
                <strong><?php echo htmlspecialchars($note['message']); ?></strong><br>
                <small><?php echo formatDate($note['created_at']); ?></small>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
