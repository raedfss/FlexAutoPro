<?php
// FlexAutoPro - includes/auth.php
// إعداد الجلسة وتعريف دوال الدخول والصلاحيات فقط

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

// دالة: هل لديه صلاحية معينة؟
function hasPermission($permission) {
    if (isAdmin()) return true;

    return isset($_SESSION['permissions']) && is_array($_SESSION['permissions']) && in_array($permission, $_SESSION['permissions']);
}
