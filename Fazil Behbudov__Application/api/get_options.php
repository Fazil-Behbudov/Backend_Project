<?php
/**
 * Get Options API - Populates select lists from database
 * Uses PDO with prepared statements for security
 * Demonstrates: SELECT queries from lookup tables
 */
header('Content-Type: application/json');
require_once 'config.php';

try {
    $options = [
        'weather' => [],
        'traffic' => [],
        'roadType' => [],
        'timeOfDay' => [],
        'maneuver' => []
    ];

    // Weather - Using prepared statement
    $stmt = $pdo->prepare("SELECT idWeather AS id, weatherType AS label FROM weather ORDER BY idWeather");
    $stmt->execute();
    $options['weather'] = $stmt->fetchAll();

    // Traffic
    $stmt = $pdo->prepare("SELECT idTraffic AS id, trafficType AS label FROM traffic ORDER BY idTraffic");
    $stmt->execute();
    $options['traffic'] = $stmt->fetchAll();

    // Road Type
    $stmt = $pdo->prepare("SELECT idRoadType AS id, roadTypeName AS label FROM roadType ORDER BY idRoadType");
    $stmt->execute();
    $options['roadType'] = $stmt->fetchAll();

    // Time of Day
    $stmt = $pdo->prepare("SELECT idTimeOfDay AS id, periodOfDay AS label FROM timeOfDay ORDER BY idTimeOfDay");
    $stmt->execute();
    $options['timeOfDay'] = $stmt->fetchAll();

    // Maneuver
    $stmt = $pdo->prepare("SELECT idManeuver AS id, maneuverType AS label FROM maneuver ORDER BY idManeuver");
    $stmt->execute();
    $options['maneuver'] = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $options]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
