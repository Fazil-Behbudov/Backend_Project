<?php
header('Content-Type: application/json');
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
// Accept id 0 as valid (legacy rows may start at 0)
if (!$input || !isset($input['idDrivingExp']) || intval($input['idDrivingExp']) < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input: idDrivingExp is required']);
    exit;
}

try {
    $idDrivingExp = intval($input['idDrivingExp']);

    // Work in a single transaction to keep parent row locked and present
    $pdo->beginTransaction();

    // Fetch and lock the existing record inside the transaction
    $stmt = $pdo->prepare('SELECT idUser, idTimeOfDay, idTraffic, idRoadType FROM drivingExperience WHERE idDrivingExp = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$idDrivingExp]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception('Driving experience not found');
    }
    $idUser = intval($row['idUser']);
    $existingTimeOfDay = isset($row['idTimeOfDay']) ? intval($row['idTimeOfDay']) : null;
    $existingTraffic   = isset($row['idTraffic']) ? intval($row['idTraffic']) : null;
    $existingRoadType  = isset($row['idRoadType']) ? intval($row['idRoadType']) : null;

    // Fetch existing weather IDs
    $stmt = $pdo->prepare('SELECT idWeather FROM drivingExp_weather WHERE idDrivingExp = ?');
    $stmt->execute([$idDrivingExp]);
    $existingWeatherIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Extract and validate input values
    $idWeather = isset($input['idWeather']) && intval($input['idWeather']) > 0 ? intval($input['idWeather']) : (!empty($existingWeatherIds) ? intval($existingWeatherIds[0]) : null);
    $idTraffic = isset($input['idTraffic']) && intval($input['idTraffic']) > 0
        ? intval($input['idTraffic'])
        : $existingTraffic;
    $idRoadType = isset($input['idRoadType']) && intval($input['idRoadType']) > 0
        ? intval($input['idRoadType'])
        : $existingRoadType;
    $idTimeOfDay = isset($input['idTimeOfDay']) && intval($input['idTimeOfDay']) > 0
        ? intval($input['idTimeOfDay'])
        : $existingTimeOfDay;
    $idManeuver = isset($input['idManeuver']) && intval($input['idManeuver']) > 0 ? intval($input['idManeuver']) : null;

    // Helper to get or create a lookup row safely inside the transaction
    $getOrCreate = function($table, $idCol, $nameCol, $value) use ($pdo) {
        $stmt = $pdo->prepare("SELECT $idCol FROM $table WHERE $nameCol = ? LIMIT 1");
        $stmt->execute([$value]);
        $existingId = $stmt->fetchColumn();
        if ($existingId) {
            return intval($existingId);
        }
        $nextId = (int)$pdo->query("SELECT COALESCE(MAX($idCol),0)+1 FROM $table")->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO $table ($idCol, $nameCol) VALUES (?, ?)");
        $stmt->execute([$nextId, $value]);
        return $nextId;
    };

    // Handle new weather type
    if (isset($input['newWeatherType']) && $input['newWeatherType']) {
        $weatherType = trim($input['newWeatherType']);
        $idWeather = $getOrCreate('weather', 'idWeather', 'weatherType', $weatherType);
    }

    // Handle new traffic type
    if (isset($input['newTrafficType']) && $input['newTrafficType']) {
        $trafficType = trim($input['newTrafficType']);
        $idTraffic = $getOrCreate('traffic', 'idTraffic', 'trafficType', $trafficType);
    }

    // Handle new road type
    if (isset($input['newRoadTypeName']) && $input['newRoadTypeName']) {
        $roadTypeName = trim($input['newRoadTypeName']);
        $idRoadType = $getOrCreate('roadType', 'idRoadType', 'roadTypeName', $roadTypeName);
    }

    // Temporarily disable FK checks to avoid transient violations while resetting relations
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    // Delete old relationships first
    $stmt = $pdo->prepare('DELETE FROM drivingExp_weather WHERE idDrivingExp = ?');
    $stmt->execute([$idDrivingExp]);

    $stmt = $pdo->prepare('DELETE FROM drivingExp_maneuver WHERE idDrivingExp = ?');
    $stmt->execute([$idDrivingExp]);

    // Extract remaining fields
    $mileage = isset($input['mileage']) ? floatval($input['mileage']) : 0;
    $date = $input['date'] ?? '';
    $startTime = isset($input['startTime']) && $input['startTime'] !== '' ? $input['startTime'] : null;
    $endTime = isset($input['endTime']) && $input['endTime'] !== '' ? $input['endTime'] : null;

    // Update main record
    $updateSql = "UPDATE drivingExperience SET mileage = ?, date = ?, startTime = ?, endTime = ?, idUser = ?, idTimeOfDay = ?, idTraffic = ?, idRoadType = ? WHERE idDrivingExp = ?";
    $stmt = $pdo->prepare($updateSql);
    $stmt->execute([
        $mileage,
        $date,
        $startTime,
        $endTime,
        $idUser,
        $idTimeOfDay,
        $idTraffic,
        $idRoadType,
        $idDrivingExp
    ]);

    // Re-insert weather relationship if applicable (column order matches save flow)
    if ($idWeather && $idWeather > 0) {
        $stmt = $pdo->prepare('INSERT INTO drivingExp_weather (idWeather, idDrivingExp) VALUES (?, ?)');
        $stmt->execute([$idWeather, $idDrivingExp]);
    }

    // Re-insert maneuver relationship if applicable
    if ($idManeuver && $idManeuver > 0) {
        $stmt = $pdo->prepare('INSERT INTO drivingExp_maneuver (idManeuver, idDrivingExp) VALUES (?, ?)');
        $stmt->execute([$idManeuver, $idDrivingExp]);
    }

    // Re-enable FK checks before commit
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Driving experience updated']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        // Ensure FK checks are re-enabled on error
        try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $t) {}
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error updating experience: ' . $e->getMessage()]);
}
?>
