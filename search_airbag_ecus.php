<?php
require_once __DIR__ . '/includes/db.php';

// تعيين رؤوس JSON
header('Content-Type: application/json; charset=utf-8');

$search = trim($_GET['q'] ?? '');
$field = $_GET['field'] ?? 'brand';
$action = $_GET['action'] ?? 'search';

// الحقول المسموح البحث فيها
$allowed_fields = ['brand', 'model', 'ecu_number', 'eeprom_type'];

if (!in_array($field, $allowed_fields)) {
    echo json_encode([]);
    exit;
}

try {
    switch ($action) {
        case 'brands':
            $stmt = $pdo->prepare("SELECT DISTINCT brand FROM airbag_ecus WHERE brand IS NOT NULL ORDER BY brand");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode($results);
            break;
            
        case 'models':
            $brand = $_GET['brand'] ?? '';
            if (empty($brand)) {
                echo json_encode([]);
                exit;
            }
            $stmt = $pdo->prepare("SELECT DISTINCT model FROM airbag_ecus WHERE brand = $1 AND model IS NOT NULL ORDER BY model");
            $stmt->execute([$brand]);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode($results);
            break;
            
        case 'ecus':
            $brand = $_GET['brand'] ?? '';
            $model = $_GET['model'] ?? '';
            if (empty($brand) || empty($model)) {
                echo json_encode([]);
                exit;
            }
            $stmt = $pdo->prepare("SELECT ecu_number, eeprom_type FROM airbag_ecus WHERE brand = $1 AND model = $2 ORDER BY ecu_number");
            $stmt->execute([$brand, $model]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($results);
            break;
            
        case 'full_search':
            if (empty($search)) {
                echo json_encode([]);
                exit;
            }
            $search_term = '%' . $search . '%';
            $stmt = $pdo->prepare("
                SELECT brand, model, ecu_number, eeprom_type,
                       CASE 
                           WHEN brand ILIKE $1 THEN 1
                           WHEN model ILIKE $2 THEN 2
                           WHEN ecu_number ILIKE $3 THEN 3
                           ELSE 4
                       END as priority
                FROM airbag_ecus 
                WHERE brand ILIKE $4 OR model ILIKE $5 OR ecu_number ILIKE $6
                ORDER BY priority, brand, model, ecu_number
                LIMIT 30
            ");
            $stmt->execute([
                $search . '%', $search . '%', $search . '%',
                $search_term, $search_term, $search_term
            ]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($results);
            break;
            
        default:
            if ($search && in_array($field, $allowed_fields)) {
                $stmt = $pdo->prepare("SELECT DISTINCT \"$field\" FROM airbag_ecus WHERE \"$field\" ILIKE $1 ORDER BY \"$field\" LIMIT 10");
                $stmt->execute([$search . '%']);
                $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
                echo json_encode($results);
                exit;
            }
            echo json_encode([]);
    }
} catch (Exception $e) {
    error_log('Search error: ' . $e->getMessage());
    echo json_encode(['error' => 'Search failed']);
}
?>