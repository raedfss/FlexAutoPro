
<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_POST['brand']) || !isset($_POST['model']) || !isset($_POST['ecu_number'])) {
    http_response_code(400);
    echo json_encode(['images' => null]);
    exit;
}

$brand = trim($_POST['brand']);
$model = trim($_POST['model']);
$ecu_number = trim($_POST['ecu_number']);

try {
    $stmt = $pdo->prepare("
        SELECT wiring_image, board_image, description 
        FROM ecu_images 
        WHERE brand = ? AND model = ? AND ecu_number = ?
    ");
    $stmt->execute([$brand, $model, $ecu_number]);
    $images = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($images) {
        echo json_encode(['images' => $images]);
    } else {
        echo json_encode(['images' => null]);
    }
} catch (PDOException $e) {
    error_log("Error fetching ECU images: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['images' => null]);
}
?>