<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $new_password = $_POST['password'];
    $confirm = $_POST['confirm'];

    if (empty($email) || empty($new_password) || empty($confirm)) {
        $error = "جميع الحقول مطلوبة.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "بريد إلكتروني غير صالح.";
    } elseif ($new_password !== $confirm) {
        $error = "كلمتا المرور غير متطابقتين.";
    } else {
        // تحقق من وجود المستخدم
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() === 0) {
            $error = "البريد الإلكتروني غير موجود في النظام.";
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $update->execute([$hashed, $email]);
            $success = "تم تحديث كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول.";
        }
    }
}
?>

<h2>استعادة كلمة المرور</h2>

<?php
if (!empty($error)) {
    showMessage("danger", $error);
}
if (!empty($success)) {
    showMessage("success", $success);
}
?>

<form method="POST" action="reset_password.php">
    <label>البريد الإلكتروني:</label><br>
    <input type="email" name="email" required><br><br>

    <label>كلمة المرور الجديدة:</label><br>
    <input type="password" name="password" required><br><br>

    <label>تأكيد كلمة المرور:</label><br>
    <input type="password" name="confirm" required><br><br>

    <button type="submit">تحديث كلمة المرور</button>
</form>

<?php require_once 'includes/footer.php'; ?>
