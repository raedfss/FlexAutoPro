<?php
// FlexAutoPro - includes/functions.php
// دوال مساعدة عامة للمشروع

// دالة تنسيق التاريخ بطريقة جميلة
function formatDate($datetime) {
    if (!$datetime) {
        return '';
    }

    // التأكد من أن القيمة قابلة للقراءة
    try {
        $date = new DateTime($datetime);
        return $date->format('Y-m-d H:i');
    } catch (Exception $e) {
        return '';
    }
}

// دالة تنظيف إدخالات المستخدم (اختياري إضافي لزيادة الأمان)
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// دالة عرض رسالة تنبيه (Bootstrap Alert)
function displayAlert($message, $type = 'success') {
    if (!$message) return '';
    return '<div class="alert alert-' . htmlspecialchars($type) . ' text-center" role="alert">'
         . htmlspecialchars($message)
         . '</div>';
}

// دالة التحقق مما إذا كان المستخدم Admin
function isAdmin() {
    return (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
}
?>
