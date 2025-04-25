<?php
session_start();

// 1) تضمين الاتصال بقاعدة البيانات (PDO)
require_once __DIR__ . '/includes/db.php';

// 2) تضمين الدوال المساعدة (showMessage)
require_once __DIR__ . '/includes/functions.php';

// 3) تضمين الهيدر العام
require_once __DIR__ . '/includes/header.php';

// تهيئة رسائل الخطأ والنجاح
$error   = '';
$success = '';

// 4) معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // التحقق من اكتمال جميع الحقول
    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        $error = "❌ جميع الحقول مطلوبة.";
    }
    // التحقق من صحة البريد الإلكتروني
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "❌ البريد الإلكتروني غير صالح.";
    }
    else {
        // إدخال البيانات في جدول contacts
        $stmt = $pdo->prepare("
            INSERT INTO contacts (name, email, subject, message, created_at)
            VALUES (:name, :email, :subject, :message, NOW())
        ");
        $stmt->execute([
            ':name'    => $name,
            ':email'   => $email,
            ':subject' => $subject,
            ':message' => $message
        ]);

        $success = "✅ تم إرسال رسالتك بنجاح. سنقوم بالرد قريبًا.";
    }
}
?>

<div class="container">
    <h2>تواصل معنا</h2>

    <?php
    // عرض رسائل الخطأ أو النجاح
    if ($error)   showMessage('danger', $error);
    if ($success) showMessage('success', $success);
    ?>

    <form method="POST" action="contact.php" class="form-style">
        <div class="form-group">
            <label for="name">الاسم الكامل:</label>
            <input type="text" id="name" name="name" required
                   value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
            <label for="email">البريد الإلكتروني:</label>
            <input type="email" id="email" name="email" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
            <label for="subject">عنوان الرسالة:</label>
            <input type="text" id="subject" name="subject" required
                   value="<?= htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
            <label for="message">الرسالة:</label>
            <textarea id="message" name="message" rows="5" required><?= htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES) ?></textarea>
        </div>

        <button type="submit" class="btn-submit">إرسال</button>
    </form>
</div>

<?php
// 5) تضمين الفوتر العام
require_once __DIR__ . '/includes/footer.php';
?>
