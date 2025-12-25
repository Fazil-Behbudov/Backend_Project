<?php
header('Content-Type: application/json');
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['idDrivingExp']) || intval($input['idDrivingExp']) <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input: idDrivingExp is required']);
    exit;
}

try {
    $pdo->beginTransaction();
    $idDrivingExp = intval($input['idDrivingExp']);

    $stmt = $pdo->prepare('DELETE FROM drivingExp_weather WHERE idDrivingExp = ?');
    $stmt->execute([$idDrivingExp]);

    $stmt = $pdo->prepare('DELETE FROM drivingExp_maneuver WHERE idDrivingExp = ?');
    $stmt->execute([$idDrivingExp]);

    $stmt = $pdo->prepare('DELETE FROM drivingExperience WHERE idDrivingExp = ?');
    $stmt->execute([$idDrivingExp]);
    $deleted = $stmt->rowCount();

    if ($deleted === 0) {
        throw new Exception('Driving experience not found');
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Driving experience deleted']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error deleting experience: ' . $e->getMessage()]);
}
?>
