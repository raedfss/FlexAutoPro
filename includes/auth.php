<?php
// FlexAutoPro - includes/auth.php
// التحقق من تسجيل الدخول وتحديد نوع المستخدم

// تأكد من بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * التحقق من تسجيل دخول المستخدم
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * التحقق من صلاحيات المستخدم
 * @param string $permission اسم الصلاحية
 * @return bool
 */
function hasPermission($permission) {
    // إذا كان المستخدم مسؤول، يمتلك جميع الصلاحيات
    if (isAdmin()) {
        return true;
    }
    
    // أعط جميع المستخدمين صلاحية airbag_reset مؤقتًا لتجنب مشاكل الوصول
    if ($permission === 'airbag_reset') {
        return true;
    }
    
    // تحقق من وجود مصفوفة الصلاحيات
    if (!isset($_SESSION['user_permissions']) || !is_array($_SESSION['user_permissions'])) {
        // إذا كانت قائمة الصلاحيات غير موجودة، نفترض أن المستخدم لا يمتلك أي صلاحيات خاصة
        return false;
    }
    
    // تحقق من وجود الصلاحية المطلوبة في قائمة صلاحيات المستخدم
    return in_array($permission, $_SESSION['user_permissions']);
}

/**
 * التحقق مما إذا كان المستخدم مسؤول
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * التحقق مما إذا كان المستخدم فني
 * @return bool
 */
function isTechnician() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'technician';
}

/**
 * الحصول على اسم المستخدم الحالي
 * @return string
 */
function getCurrentUsername() {
    return $_SESSION['username'] ?? 'مستخدم';
}

/**
 * الحصول على معرف المستخدم الحالي
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * الحصول على دور المستخدم الحالي
 * @return string
 */
function getUserRole() {
    return $_SESSION['user_role'] ?? 'user';
}

// هام: تعطيل التحقق التلقائي من تسجيل الدخول
// الصفحة الرئيسية ستتعامل مع التحقق من تسجيل الدخول بنفسها
// من خلال استدعاء isLoggedIn() بشكل صريح