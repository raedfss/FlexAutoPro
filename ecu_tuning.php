<?php
$page_title = "طلب تعديل برمجيات ECU";
$page_css = <<<CSS
.ecu-form {
    max-width: 700px;
    margin: auto;
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
}
CSS;

$success_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/db.php';

    $car_type = trim($_POST['car_type']);
    $vin = strtoupper(trim($_POST['vin']));
    $contact = trim($_POST['contact']);
    $programmer = trim($_POST['programmer']);
    $tool_type = trim($_POST['tool_type']);
    $filename = '';

    // تحقق من رقم الشاسيه وحقول التواصل
    if (strlen($vin) === 17 && $car_type && $contact) {
        // رفع الملف إذا وُجد
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $filename = 'ecu_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            move_uploaded_file($_FILES['file']['tmp_name'], __DIR__ . "/uploads/$filename");
        }

        $stmt = $pdo->prepare("INSERT INTO tickets (username, car_type, request_type, vin, phone_number, status, is_seen, created_at, data1, data2, file_path)
                               VALUES ('Guest', :car_type, 'ECU Tuning', :vin, :contact, 'pending', 0, NOW(), :programmer, :tool_type, :file)");

        $stmt->execute([
            'car_type' => $car_type,
            'vin' => $vin,
            'contact' => $contact,
            'programmer' => $programmer,
            'tool_type' => $tool_type,
            'file' => $filename
        ]);

        $success_msg = "✅ تم استلام طلبك بنجاح، سيتواصل معك فريقنا قريبًا.";
    } else {
        $success_msg = "❌ تأكد من إدخال رقم الشاسيه المكون من 17 خانة وكافة الحقول الإلزامية.";
    }
}

$page_content = <<<HTML
<div class="ecu-form">
    <h2 class="text-center mb-4">🔧 طلب تعديل برمجيات ECU</h2>
    {$success_msg ? "<div class='alert alert-info'>$success_msg</div>" : ""}
    <form method="POST" enctype="multipart/form-data" onsubmit="return validateVIN();">

        <div class="mb-3">
            <label for="car_type" class="form-label">نوع السيارة</label>
            <input type="text" name="car_type" id="car_type" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="vin" class="form-label">رقم الشاسيه (VIN)</label>
            <input type="text" name="vin" id="vin" class="form-control" maxlength="17" required placeholder="17 خانة">
        </div>

        <div class="mb-3">
            <label for="contact" class="form-label">رقم الهاتف أو البريد الإلكتروني</label>
            <input type="text" name="contact" id="contact" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="programmer" class="form-label">اسم المبرمج (اختياري)</label>
            <input type="text" name="programmer" id="programmer" class="form-control">
        </div>

        <div class="mb-3">
            <label for="tool_type" class="form-label">نوع الأداة المستخدمة</label>
            <select name="tool_type" id="tool_type" class="form-select" required>
                <option value="Master">Master</option>
                <option value="Slave">Slave</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="file" class="form-label">رفع ملف (اختياري)</label>
            <input type="file" name="file" id="file" class="form-control">
        </div>

        <div class="text-center">
            <button type="submit" class="btn btn-primary">📩 إرسال الطلب</button>
        </div>
    </form>
</div>

<script>
function validateVIN() {
    var vin = document.getElementById('vin').value.trim();
    if (vin.length !== 17) {
        alert("يجب أن يكون رقم الشاسيه 17 خانة.");
        return false;
    }
    return true;
}
</script>
HTML;

require_once 'includes/layout.php';
