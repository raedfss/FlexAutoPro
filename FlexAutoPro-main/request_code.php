<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chassis = trim($_POST['chassis'] ?? '');
    $make = trim($_POST['make'] ?? '');
    $model = trim($_POST['model'] ?? '');

    if (empty($chassis) || empty($make) || empty($model)) {
        $error = "جميع الحقول مطلوبة.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO code_requests (user_id, chassis_number, vehicle_make, vehicle_model) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $chassis, $make, $model]);
        $success = "تم إرسال طلب الكود بنجاح. سيتم مراجعته قريبًا.";
    }
}
?>

<h2>طلب كود برمجي</h2>

<?php
if (!empty($error)) showMessage("danger", $error);
if (!empty($success)) showMessage("success", $success);
?>

<form method="POST" action="request_code.php">
    <label>رقم الشاسيه:</label><br>
    <input type="text" name="chassis" required><br><br>

    <label>نوع السيارة:</label><br>
    <input type="text" name="make" placeholder="مثال: Hyundai" required><br><br>

    <label>موديل السيارة:</label><br>
    <input type="text" name="model" placeholder="مثال: Elantra 2018" required><br><br>

    <button type="submit">إرسال الطلب</button>
</form>

<?php require_once 'includes/footer.php'; ?>
