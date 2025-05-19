<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// إرسال رسالة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($subject) || empty($message)) {
        $error = "العنوان والرسالة مطلوبان.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO messages (user_id, sender, subject, message) VALUES (?, 'user', ?, ?)");
        $stmt->execute([$user_id, $subject, $message]);
        $success = "تم إرسال رسالتك بنجاح.";
    }
}

// جلب جميع الرسائل الخاصة بالمستخدم
$stmt = $pdo->prepare("SELECT * FROM messages WHERE user_id = ? ORDER BY created_at ASC");
$stmt->execute([$user_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>الرسائل</h2>

<?php
if (!empty($error)) showMessage("danger", $error);
if (!empty($success)) showMessage("success", $success);
?>

<div class="messages-box" style="max-height: 300px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; margin-bottom: 20px;">
    <?php if (count($messages) === 0): ?>
        <p>لا توجد رسائل حتى الآن.</p>
    <?php else: ?>
        <?php foreach ($messages as $msg): ?>
            <div style="margin-bottom: 10px; background-color: <?= $msg['sender'] === 'admin' ? '#f0f8ff' : '#e9ffe9' ?>; padding: 8px; border-radius: 6px;">
                <strong><?= $msg['sender'] === 'admin' ? 'الإدارة' : 'أنت' ?> - <?= htmlspecialchars($msg['subject']) ?></strong>
                <p style="margin: 5px 0;"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                <small><?= formatDate($msg['created_at']) ?></small>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<form method="POST" action="messages.php">
    <label>العنوان:</label><br>
    <input type="text" name="subject" required><br><br>

    <label>الرسالة:</label><br>
    <textarea name="message" rows="4" required></textarea><br><br>

    <button type="submit">إرسال</button>
</form>

<?php require_once 'includes/footer.php'; ?>
