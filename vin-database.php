<?php
// vin-database.php – طلب كود برمجة بدون تسجيل
$page_title = "طلب كود برمجة بدون تسجيل";
$page_css = <<<CSS
.vin-form {
    max-width: 700px;
    margin: 0 auto;
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
}
.form-section {
    padding: 40px 20px;
    background-color: #f8f9fa;
}
.success-msg {
    background-color: #d1e7dd;
    border: 1px solid #badbcc;
    color: #0f5132;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
}
CSS;

$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/db.php';

    $request_type = $_POST['request_type'];
    $other_service = trim($_POST['other_service'] ?? '');
    $car_type = trim($_POST['car_type']);
    $vin = strtoupper(trim($_POST['vin']));
    $contact = trim($_POST['contact']);

    if (strlen($vin) === 17 && $car_type && $contact) {
        $full_type = ($request_type === 'other') ? $other_service : $request_type;

        $stmt = $pdo->prepare("INSERT INTO tickets (username, car_type, request_type, vin, phone_number, status, is_seen, created_at) 
                               VALUES (:username, :car_type, :request_type, :vin, :phone, 'pending', 0, NOW())");

        $stmt->execute([
            'username' => 'Guest',
            'car_type' => $car_type,
            'request_type' => $full_type,
            'vin' => $vin,
            'phone' => $contact
        ]);

        $success_message = "✅ تم استلام طلبك بنجاح. سيتواصل معك فريق FlexAuto قريبًا.";
    } else {
        $success_message = "❌ تأكد من إدخال كافة الحقول بشكل صحيح.";
    }
}

$page_content = <<<HTML
<div class="form-section">
    <div class="vin-form">
        <h2 class="text-center mb-4">🔐 طلب كود برمجة / خدمة أخرى</h2>

        <!-- رسالة النجاح -->
        {$success_message ? "<div class='success-msg'>$success_message</div>" : ""}

        <form method="post" onsubmit="return validateVIN();">
            <div class="mb-3">
                <label for="request_type" class="form-label">نوع الطلب</label>
                <select name="request_type" id="request_type" class="form-select" required onchange="toggleOtherField()">
                    <option value="">اختر...</option>
                    <option value="Key Code Request">طلب كود برمجة مفتاح</option>
                    <option value="other">خدمة أخرى</option>
                </select>
            </div>

            <div class="mb-3" id="otherServiceGroup" style="display:none;">
                <label for="other_service" class="form-label">يرجى تحديد الخدمة</label>
                <input type="text" name="other_service" id="other_service" class="form-control">
            </div>

            <div class="mb-3">
                <label for="car_type" class="form-label">نوع السيارة</label>
                <input type="text" name="car_type" id="car_type" class="form-control" placeholder="مثل: Hyundai Elantra 2020" required>
            </div>

            <div class="mb-3">
                <label for="vin" class="form-label">رقم الشاسيه (VIN)</label>
                <input type="text" name="vin" id="vin" class="form-control" maxlength="17" required pattern=".{17,17}" placeholder="17 خانة">
            </div>

            <div class="mb-3">
                <label for="contact" class="form-label">رقم الهاتف أو البريد الإلكتروني</label>
                <input type="text" name="contact" id="contact" class="form-control" required>
            </div>

            <div class="text-center">
                <button type="submit" class="btn btn-primary">📩 إرسال الطلب</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleOtherField() {
    var reqType = document.getElementById('request_type').value;
    document.getElementById('otherServiceGroup').style.display = (reqType === 'other') ? 'block' : 'none';
}

function validateVIN() {
    var vin = document.getElementById('vin').value.trim();
    if (vin.length !== 17) {
        alert("رقم الشاسيه يجب أن يكون 17 خانة.");
        return false;
    }
    return true;
}
</script>
HTML;

require_once 'includes/layout.php';
