<?php
// عرض جميع الأخطاء
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php'; // تأكد أن المسار صحيح بالنسبة لمكان وضع هذا الملف

try {
    echo "<p>✅ ملف الاتصال محمّل بنجاح...</p>";

    // تنفيذ استعلام بسيط للتحقق من الاتصال
    $stmt = $pdo->query('SELECT NOW()');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<h2 style='color:green;'>✅ الاتصال بقاعدة البيانات ناجح!</h2>";
    echo "<p>الوقت الحالي على السيرفر: " . htmlspecialchars($row['now']) . "</p>";

} catch (PDOException $e) {
    echo "<h2 style='color:red;'>❌ فشل الاتصال بقاعدة البيانات!</h2>";
    echo "<p>تفاصيل الخطأ: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
