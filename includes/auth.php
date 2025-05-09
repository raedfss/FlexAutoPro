<?php
// FlexAutoPro - includes/auth.php
// التحقق من تسجيل الدخول وتحديد نوع المستخدم + دعم صلاحيات مخصصة

// تأكد من بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// دالة: هل المستخدم مسجل دخول؟
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// دالة: هل المستخدم أدمن؟
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// دالة: التحقق من صلاحيات مخصصة
function hasPermission($permission) {
    if (isAdmin()) {
        return true; // الأدمن يملك كل الصلاحيات
    }

    // تحقق من وجود قائمة صلاحيات مخصصة للمستخدم
    if (!isset($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
        return false;
    }

    return in_array($permission, $_SESSION['permissions']);
}
?>
