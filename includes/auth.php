<?php
// FlexAutoPro - includes/auth.php
// التحقق من تسجيل الدخول وتحديد نوع المستخدم

// تأكد من بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    // لم يتم تسجيل الدخول → إعادة التوجيه لصفحة تسجيل الدخول
    header("Location: /login.php");
    exit;
}

// التحقق من وجود الدور (user_role) إذا لزم الأمر
if (!isset($_SESSION['user_role'])) {
    // تعيين دور افتراضي في حال غيابه (اختياري)
    $_SESSION['user_role'] = 'user';
}

// يمكن استخدام هذا الملف للتحقق من الصلاحيات أيضًا:
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}
?>
