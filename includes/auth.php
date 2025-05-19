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
    // استدعاء دالة isAdmin() الموجودة في ملف functions.php
    if (function_exists('isAdmin') && isAdmin()) {
        return true;
    }
    
    // منح صلاحية airbag_reset لجميع المستخدمين مؤقتًا لتسهيل الاختبار
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

// ملاحظة: قمنا بإزالة دالة isAdmin() من هنا لأنها معرّفة في ملف functions.php
// ونستخدم function_exists() للتحقق من وجودها عند استدعائها

// كما قمنا بإزالة كود التحقق التلقائي وإعادة التوجيه 
// ليتم التحكم فيه من خلال الصفحة الرئيسية