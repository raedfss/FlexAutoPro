<?php
/**
 * FlexAutoPro - نظام البحث الذكي لبيانات الإيرباق
 * 
 * هذا الملف يعالج طلبات البحث الذكي لصفحة مسح بيانات الإيرباق
 * ويقوم بإرجاع النتائج بتنسيق JSON
 * 
 * @version     2.0.0
 * @author      FlexAutoPro Team
 * @copyright   2025 FlexAutoPro
 */

// 1. إعداد الجلسة والاتصال بقاعدة البيانات
session_start();

// إعدادات الأمان والاستجابة
header('Content-Type: application/json; charset=utf-8');

// منع التخزين المؤقت للاستجابات
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// التحقق من وجود ملفات الضرورية
if (!file_exists(__DIR__ . '/includes/db.php')) {
    die(json_encode(['error' => 'خطأ في التكوين: ملف قاعدة البيانات غير موجود.']));
}

// الاتصال بقاعدة البيانات
require_once __DIR__ . '/includes/db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['email'])) {
    die(json_encode(['error' => 'يجب تسجيل الدخول أولاً.']));
}

// استيراد الدوال المساعدة
if (file_exists(__DIR__ . '/includes/functions.php')) {
    require_once __DIR__ . '/includes/functions.php';
} else {
    // دوال مساعدة بديلة إذا كان الملف غير موجود
    function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

// 2. التحقق من وجود معلمة الإجراء
if (!isset($_GET['action'])) {
    die(json_encode(['error' => 'لم يتم تحديد الإجراء المطلوب.']));
}

// 3. استخراج وتنظيف المعلمات
$action = sanitizeInput($_GET['action']);
$query = sanitizeInput($_GET['q'] ?? '');

// معلمات إضافية
$brand = sanitizeInput($_GET['brand'] ?? '');
$model = sanitizeInput($_GET['model'] ?? '');
$year = sanitizeInput($_GET['year'] ?? '');
$ecu = sanitizeInput($_GET['ecu'] ?? '');

// 4. التحقق من طول الاستعلام
if (strlen($query) < 1) {
    die(json_encode([]));
}

// 5. تنفيذ البحث حسب الإجراء
try {
    $results = [];
    
    switch ($action) {
        case 'brands':
            // البحث عن الماركات
            $stmt = $pdo->prepare("
                SELECT DISTINCT brand 
                FROM airbag_ecus 
                WHERE brand LIKE :query 
                ORDER BY 
                    CASE 
                        WHEN brand = :exact THEN 1
                        WHEN brand LIKE :start THEN 2
                        ELSE 3
                    END,
                    brand ASC
                LIMIT 10
            ");
            
            $stmt->execute([
                ':query' => "%$query%",
                ':exact' => $query,
                ':start' => "$query%"
            ]);
            
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
            
        case 'models':
            // البحث عن الموديلات (مع تصفية حسب الماركة إن وجدت)
            $sql = "
                SELECT DISTINCT model 
                FROM airbag_ecus 
                WHERE model LIKE :query 
            ";
            
            $params = [':query' => "%$query%"];
            
            // إضافة شرط الماركة إذا كانت موجودة
            if (!empty($brand)) {
                $sql .= " AND brand = :brand ";
                $params[':brand'] = $brand;
            }
            
            $sql .= "
                ORDER BY 
                    CASE 
                        WHEN model = :exact THEN 1
                        WHEN model LIKE :start THEN 2
                        ELSE 3
                    END,
                    model ASC
                LIMIT 10
            ";
            
            $stmt = $pdo->prepare($sql);
            $params[':exact'] = $query;
            $params[':start'] = "$query%";
            
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
            
        case 'years':
            // البحث عن السنوات (مع تصفية حسب الماركة والموديل إن وجدت)
            $sql = "
                SELECT DISTINCT year 
                FROM airbag_ecus 
                WHERE year LIKE :query 
            ";
            
            $params = [':query' => "%$query%"];
            
            // إضافة شروط الماركة والموديل إذا كانت موجودة
            if (!empty($brand)) {
                $sql .= " AND brand = :brand ";
                $params[':brand'] = $brand;
            }
            
            if (!empty($model)) {
                $sql .= " AND model = :model ";
                $params[':model'] = $model;
            }
            
            $sql .= "
                ORDER BY 
                    CASE 
                        WHEN year = :exact THEN 1
                        WHEN year LIKE :start THEN 2
                        ELSE 3
                    END,
                    year DESC
                LIMIT 10
            ";
            
            $stmt = $pdo->prepare($sql);
            $params[':exact'] = $query;
            $params[':start'] = "$query%";
            
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
            
        case 'ecus':
            // البحث عن أرقام ECU (مع تصفية حسب الماركة والموديل والسنة إن وجدت)
            $sql = "
                SELECT DISTINCT ecu_number 
                FROM airbag_ecus 
                WHERE ecu_number LIKE :query 
            ";
            
            $params = [':query' => "%$query%"];
            
            // إضافة شروط الماركة والموديل والسنة إذا كانت موجودة
            if (!empty($brand)) {
                $sql .= " AND brand = :brand ";
                $params[':brand'] = $brand;
            }
            
            if (!empty($model)) {
                $sql .= " AND model = :model ";
                $params[':model'] = $model;
            }
            
            if (!empty($year)) {
                $sql .= " AND year = :year ";
                $params[':year'] = $year;
            }
            
            $sql .= "
                ORDER BY 
                    CASE 
                        WHEN ecu_number = :exact THEN 1
                        WHEN ecu_number LIKE :start THEN 2
                        ELSE 3
                    END,
                    ecu_number ASC
                LIMIT 10
            ";
            
            $stmt = $pdo->prepare($sql);
            $params[':exact'] = $query;
            $params[':start'] = "$query%";
            
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
            
        case 'eeproms':
            // البحث عن أنواع EEPROM (مع تصفية حسب الماركة والموديل و ECU إن وجدت)
            $sql = "
                SELECT DISTINCT eeprom_type 
                FROM airbag_ecus 
                WHERE eeprom_type LIKE :query 
            ";
            
            $params = [':query' => "%$query%"];
            
            // إضافة شروط الماركة والموديل و ECU إذا كانت موجودة
            if (!empty($brand)) {
                $sql .= " AND brand = :brand ";
                $params[':brand'] = $brand;
            }
            
            if (!empty($model)) {
                $sql .= " AND model = :model ";
                $params[':model'] = $model;
            }
            
            if (!empty($ecu)) {
                $sql .= " AND ecu_number = :ecu ";
                $params[':ecu'] = $ecu;
            }
            
            $sql .= "
                ORDER BY 
                    CASE 
                        WHEN eeprom_type = :exact THEN 1
                        WHEN eeprom_type LIKE :start THEN 2
                        ELSE 3
                    END,
                    eeprom_type ASC
                LIMIT 10
            ";
            
            $stmt = $pdo->prepare($sql);
            $params[':exact'] = $query;
            $params[':start'] = "$query%";
            
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
            
        default:
            die(json_encode(['error' => 'إجراء غير معروف.']));
    }
    
    // إرجاع النتائج بتنسيق JSON
    echo json_encode($results);
    
} catch (PDOException $e) {
    // تسجيل الخطأ بشكل آمن (بدون كشف تفاصيل قاعدة البيانات)
    error_log('Database error in search_airbag_ecus.php: ' . $e->getMessage());
    
    // إرجاع رسالة خطأ عامة
    die(json_encode(['error' => 'حدث خطأ أثناء البحث. يرجى المحاولة مرة أخرى.']));
}