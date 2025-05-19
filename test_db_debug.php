<?php
// تفعيل عرض جميع الأخطاء
error_reporting(E_ALL);
ini_set('display_errors', 1);

// طباعة بداية الاختبار
echo "✅ بدء اختبار الاتصال بقاعدة البيانات...<br>";

// تحميل ملف الاتصال
require_once 'includes/db.php';

// طباعة بعد التحميل
echo "✅ تم تحميل ملف db.php بنجاح...<br>";

// اختبار تنفيذ استعلام
try {
    $stmt = $pdo->query('SELECT NOW()');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<h2 style='color:green;'>✅ الاتصال بقاعدة البيانات ناجح!</h2>";
    echo "<p>الوقت الحالي على السيرفر: " . htmlspecialchars($row['now']) . "</p>";

} catch (PDOException $e) {
    echo "<h2 style='color:red;'>❌ فشل الاتصال بقاعدة البيانات!</h2>";
    echo "<p>خطأ: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
