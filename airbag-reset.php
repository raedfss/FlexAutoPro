<?php
/**
 * FlexAutoPro - نظام البحث الذكي لبيانات الإيرباق
 * 
 * هذا الملف يوفر واجهة برمجة تطبيقات (API) للبحث الذكي في قاعدة بيانات الإيرباق
 * يستجيب للطلبات من صفحة airbag-reset.php لتوفير اقتراحات البحث
 * 
 * @author FlexAutoPro Team
 * @version 1.0.0
 */

// بدء الجلسة
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['email'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'غير مصرح']);
    exit;
}

// استدعاء الاتصال بقاعدة البيانات
require_once __DIR__ . '/includes/db.php';

// تعيين نوع المحتوى كـ JSON
header('Content-Type: application/json');

// استلام نوع البحث والاستعلام
$action = $_GET['action'] ?? '';
$query = trim($_GET['q'] ?? '');

// تحديد الحد الأدنى لطول الاستعلام
$min_query_length = 1;

// التحقق من طول الاستعلام
if (strlen($query) < $min_query_length && $action !== 'years') {
    echo json_encode([]);
    exit;
}

// دالة لتنظيف المدخلات
function safeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// تنفيذ البحث حسب نوع الإجراء
try {
    switch ($action) {
        // بحث عن ماركات السيارات
        case 'brands':
            $stmt = $pdo->prepare("
                SELECT DISTINCT brand 
                FROM airbag_ecus 
                WHERE brand LIKE :query 
                ORDER BY brand 
                LIMIT 15
            ");
            $stmt->execute([':query' => "%$query%"]);
            $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode($result);
            break;

        // بحث عن موديلات السيارات
        case 'models':
            $brand = safeInput($_GET['brand'] ?? '');
            
            if (empty($brand)) {
                echo json_encode([]);
                break;
            }
            
            $stmt = $pdo->prepare("
                SELECT DISTINCT model 
                FROM airbag_ecus 
                WHERE brand = :brand AND model LIKE :query
                ORDER BY model 
                LIMIT 15
            ");
            $stmt->execute([
                ':brand' => $brand,
                ':query' => "%$query%"
            ]);
            $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode($result);
            break;

        // بحث عن سنوات الصنع
        case 'years':
            $brand = safeInput($_GET['brand'] ?? '');
            $model = safeInput($_GET['model'] ?? '');
            
            if (empty($brand) || empty($model)) {
                echo json_encode([]);
                break;
            }

            // البحث عن السنوات المتاحة (استخدم جدول خاص إذا كان متوفرًا)
            // هنا نفترض أن لدينا سنوات محددة مرتبطة بكل موديل
            // يمكن تعديل هذا حسب هيكل قاعدة البيانات الفعلي

            // نموذج للسنوات المحتملة (يمكن استبداله بقاعدة بيانات فعلية)
            $all_years = range(date('Y'), 1990);
            $filtered_years = [];
            
            // تطبيق فلتر على السنوات حسب الاستعلام
            if (!empty($query)) {
                foreach ($all_years as $year) {
                    if (strpos((string)$year, $query) !== false) {
                        $filtered_years[] = $year;
                    }
                }
            } else {
                // إذا لم يكن هناك استعلام، أعد السنوات الأحدث
                $filtered_years = array_slice($all_years, 0, 15);
            }
            
            echo json_encode($filtered_years);
            break;

        // بحث عن أرقام وحدات ECU
        case 'ecus':
            $brand = safeInput($_GET['brand'] ?? '');
            $model = safeInput($_GET['model'] ?? '');
            $year = safeInput($_GET['year'] ?? '');
            
            if (empty($brand) || empty($model)) {
                echo json_encode([]);
                break;
            }
            
            $params = [
                ':brand' => $brand,
                ':model' => $model
            ];
            
            $sql = "
                SELECT DISTINCT ecu_number 
                FROM airbag_ecus 
                WHERE brand = :brand AND model = :model
            ";
            
            // إضافة شرط السنة إذا تم تحديدها
            if (!empty($year)) {
                // هنا نفترض وجود عمود year في الجدول
                // إذا لم يكن موجودًا، يمكن تعديل هذا الجزء
                $sql .= " AND year = :year";
                $params[':year'] = $year;
            }
            
            // إضافة شرط البحث
            if (!empty($query)) {
                $sql .= " AND ecu_number LIKE :query";
                $params[':query'] = "%$query%";
            }
            
            $sql .= " ORDER BY ecu_number LIMIT 15";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode($result);
            break;

        // بحث عن أنواع EEPROM
        case 'eeproms':
            $brand = safeInput($_GET['brand'] ?? '');
            $model = safeInput($_GET['model'] ?? '');
            $ecu = safeInput($_GET['ecu'] ?? '');
            
            if (empty($brand) || empty($model)) {
                echo json_encode([]);
                break;
            }
            
            $params = [
                ':brand' => $brand,
                ':model' => $model
            ];
            
            $sql = "
                SELECT DISTINCT eeprom_type 
                FROM airbag_ecus 
                WHERE brand = :brand AND model = :model
            ";
            
            // إضافة شرط رقم ECU إذا تم تحديده
            if (!empty($ecu)) {
                $sql .= " AND ecu_number = :ecu";
                $params[':ecu'] = $ecu;
            }
            
            // إضافة شرط البحث
            if (!empty($query)) {
                $sql .= " AND eeprom_type LIKE :query";
                $params[':query'] = "%$query%";
            }
            
            $sql .= " ORDER BY eeprom_type LIMIT 15";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // إذا لم تكن هناك نتائج، قم بتقديم اقتراحات عامة
            if (empty($result)) {
                // اقتراحات شائعة لأنواع EEPROM
                $common_eeproms = [
                    '24C02', '24C04', '24C08', '24C16', '24C32', '24C64',
                    '25C160', '25C320', '25C640', '25C128', '25C256',
                    '93C46', '93C56', '93C66', '93C76', '93C86'
                ];
                
                // فلترة حسب الاستعلام
                if (!empty($query)) {
                    $filtered_eeproms = [];
                    foreach ($common_eeproms as $eeprom) {
                        if (stripos($eeprom, $query) !== false) {
                            $filtered_eeproms[] = $eeprom;
                        }
                    }
                    $result = $filtered_eeproms;
                } else {
                    $result = $common_eeproms;
                }
            }
            
            echo json_encode($result);
            break;

        // إجراء غير معروف
        default:
            echo json_encode(['error' => 'إجراء غير معروف']);
            break;
    }
} catch (PDOException $e) {
    // تسجيل الخطأ في ملف السجل
    error_log('خطأ في البحث الذكي للإيرباق: ' . $e->getMessage());
    
    // إعادة رسالة خطأ عامة للمستخدم
    echo json_encode(['error' => 'حدث خطأ أثناء البحث، يرجى المحاولة مرة أخرى']);
}
?>