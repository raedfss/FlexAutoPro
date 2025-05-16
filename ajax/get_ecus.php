<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_POST['brand']) || !isset($_POST['model'])) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

$brand = trim($_POST['brand']);
$model = trim($_POST['model']);

try {
    $stmt = $pdo->prepare("SELECT DISTINCT ecu_number FROM airbag_ecus WHERE brand = ? AND model = ? ORDER BY ecu_number");
    $stmt->execute([$brand, $model]);
    $ecus = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode($ecus);
} catch (PDOException $e) {
    error_log("Error fetching ECUs: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
}
?>