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

/**
 * تحديث صلاحيات المستخدم في الجلسة
 * @param array $permissions قائمة الصلاحيات
 */
function updateUserPermissions($permissions) {
    $_SESSION['user_permissions'] = $permissions;
}

/**
 * تحميل صلاحيات المستخدم من قاعدة البيانات
 * @param PDO $pdo اتصال قاعدة البيانات
 * @param int $user_id معرف المستخدم
 */
function loadUserPermissions($pdo, $user_id) {
    try {
        // استعلام الصلاحيات من قاعدة البيانات
        $stmt = $pdo->prepare("
            SELECT permission_name 
            FROM user_permissions 
            WHERE user_id = :user_id
        ");
        $stmt->execute([':user_id' => $user_id]);
        
        // جمع الصلاحيات في مصفوفة
        $permissions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions[] = $row['permission_name'];
        }
        
        // تحديث الجلسة
        $_SESSION['user_permissions'] = $permissions;
        
        return $permissions;
    } catch (PDOException $e) {
        // تسجيل الخطأ
        error_log('خطأ في تحميل صلاحيات المستخدم: ' . $e->getMessage());
        return [];
    }
}

// يتم استدعاء الكود التالي عند تضمين الملف، وهو يتحقق من تسجيل الدخول
// ويمنع الوصول غير المصرح به للصفحات

// ملاحظة: يمكنك تعطيل هذا الجزء إذا كنت تريد تضمين هذا الملف دون تنفيذ التحقق الفوري
// عن طريق تعريف ثابت AUTH_SKIP_CHECK قبل تضمين هذا الملف

if (!defined('AUTH_SKIP_CHECK') && !isLoggedIn()) {
    // لم يتم تسجيل الدخول → إعادة التوجيه لصفحة تسجيل الدخول
    $_SESSION['message'] = "يجب تسجيل الدخول للوصول إلى هذه الصفحة";
    $_SESSION['message_type'] = "error";
    header("Location: /login.php");
    exit;
}