<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    // Helpers
    $parseIds = function($key) {
        if (!isset($_GET[$key]) || $_GET[$key] === '') return [];
        $parts = explode(',', $_GET[$key]);
        $ids = [];
        foreach ($parts as $p) {
            $val = (int)$p;
            if ($val > 0) $ids[] = $val;
        }
        return array_values(array_unique($ids));
    };

    $startDate = isset($_GET['startDate']) && $_GET['startDate'] !== '' ? $_GET['startDate'] : null;
    $endDate = isset($_GET['endDate']) && $_GET['endDate'] !== '' ? $_GET['endDate'] : null;

    $weatherIds = $parseIds('weatherIds');
    $trafficIds = $parseIds('trafficIds');
    $roadTypeIds = $parseIds('roadTypeIds');
    $timeOfDayIds = $parseIds('timeOfDayIds');
    $maneuverIds = $parseIds('maneuverIds');

    // Helper to fetch labels
    $fetchLabels = function($table, $idCol, $nameCol, $ids) use ($pdo) {
        if (!$ids) return [];
        $place = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT $nameCol AS label FROM $table WHERE $idCol IN ($place)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_column($rows, 'label');
    };

    $filters = [
        'startDate' => $startDate,
        'endDate' => $endDate,
        'weatherIds' => $weatherIds,
        'trafficIds' => $trafficIds,
        'roadTypeIds' => $roadTypeIds,
        'timeOfDayIds' => $timeOfDayIds,
        'maneuverIds' => $maneuverIds,
        'weatherLabels' => $fetchLabels('weather', 'idWeather', 'weatherType', $weatherIds),
        'trafficLabels' => $fetchLabels('traffic', 'idTraffic', 'trafficType', $trafficIds),
        'roadTypeLabels' => $fetchLabels('roadType', 'idRoadType', 'roadTypeName', $roadTypeIds),
        'timeOfDayLabels' => $fetchLabels('timeOfDay', 'idTimeOfDay', 'periodOfDay', $timeOfDayIds),
        'maneuverLabels' => $fetchLabels('maneuver', 'idManeuver', 'maneuverType', $maneuverIds)
    ];

    // Build WHERE clause
    $where = [];
    $params = [];
    // Using PDO so we just track params in order

    if ($startDate) {
        $where[] = 'de.date >= ?';
        $params[] = $startDate;
    }
    if ($endDate) {
        $where[] = 'de.date <= ?';
        $params[] = $endDate;
    }
    if ($trafficIds) {
        $place = implode(',', array_fill(0, count($trafficIds), '?'));
        $where[] = "de.idTraffic IN ($place)";
        $params = array_merge($params, $trafficIds);
    }
    if ($roadTypeIds) {
        $place = implode(',', array_fill(0, count($roadTypeIds), '?'));
        $where[] = "de.idRoadType IN ($place)";
        $params = array_merge($params, $roadTypeIds);
    }
    if ($timeOfDayIds) {
        $place = implode(',', array_fill(0, count($timeOfDayIds), '?'));
        $where[] = "de.idTimeOfDay IN ($place)";
        $params = array_merge($params, $timeOfDayIds);
    }
    if ($weatherIds) {
        $place = implode(',', array_fill(0, count($weatherIds), '?'));
        $where[] = "EXISTS (SELECT 1 FROM drivingExp_weather dw WHERE dw.idDrivingExp = de.idDrivingExp AND dw.idWeather IN ($place))";
        $params = array_merge($params, $weatherIds);
    }
    if ($maneuverIds) {
        $place = implode(',', array_fill(0, count($maneuverIds), '?'));
        $where[] = "EXISTS (SELECT 1 FROM drivingExp_maneuver dm WHERE dm.idDrivingExp = de.idDrivingExp AND dm.idManeuver IN ($place))";
        $params = array_merge($params, $maneuverIds);
    }

    $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Helper to run queries
    $runQuery = function($sql, $params) use ($pdo) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    // Get totals
    $totalsSql = "SELECT COUNT(DISTINCT de.idDrivingExp) AS cnt, COALESCE(SUM(de.mileage),0) AS dist, COALESCE(AVG(de.mileage),0) AS avgDist
                  FROM drivingExperience de
                  $whereClause";
    $totals = $runQuery($totalsSql, $params);
    
    $totalExperiences = 0;
    $totalDistance = 0;
    $avgMileage = 0;
    
    if ($totals && isset($totals[0])) {
        $totalExperiences = (int)$totals[0]['cnt'];
        $totalDistance = (float)$totals[0]['dist'];
        $avgMileage = (float)$totals[0]['avgDist'];
    }

    // Get detailed experiences
    $experiencesSql = "SELECT 
                        de.idDrivingExp,
                        de.date,
                        de.startTime,
                        de.endTime,
                        de.mileage,
                        de.idTraffic,
                        de.idRoadType,
                        de.idTimeOfDay,
                        t.trafficType,
                        rt.roadTypeName,
                        tod.periodOfDay,
                        GROUP_CONCAT(DISTINCT w.weatherType ORDER BY w.weatherType SEPARATOR ', ') AS weather,
                        GROUP_CONCAT(DISTINCT w.idWeather ORDER BY w.idWeather SEPARATOR ',') AS weatherIds,
                        GROUP_CONCAT(DISTINCT m.maneuverType ORDER BY m.maneuverType SEPARATOR ', ') AS maneuvers,
                        GROUP_CONCAT(DISTINCT m.idManeuver ORDER BY m.idManeuver SEPARATOR ',') AS maneuverIds
                       FROM drivingExperience de
                       INNER JOIN traffic t ON t.idTraffic = de.idTraffic
                       INNER JOIN roadType rt ON rt.idRoadType = de.idRoadType
                       INNER JOIN timeOfDay tod ON tod.idTimeOfDay = de.idTimeOfDay
                       LEFT JOIN drivingExp_weather dw ON dw.idDrivingExp = de.idDrivingExp
                       LEFT JOIN weather w ON w.idWeather = dw.idWeather
                       LEFT JOIN drivingExp_maneuver dm ON dm.idDrivingExp = de.idDrivingExp
                       LEFT JOIN maneuver m ON m.idManeuver = dm.idManeuver
                       $whereClause
                       GROUP BY de.idDrivingExp
                       ORDER BY de.date DESC, de.idDrivingExp DESC";
    
    $experiencesRaw = $runQuery($experiencesSql, $params);
    $experiences = [];

    foreach ($experiencesRaw as $row) {
        // Convert concatenated id strings to arrays for easier editing on the frontend
        $row['weatherIds'] = ($row['weatherIds'] ?? '') === '' ? [] : array_map('intval', explode(',', $row['weatherIds']));
        $row['maneuverIds'] = ($row['maneuverIds'] ?? '') === '' ? [] : array_map('intval', explode(',', $row['maneuverIds']));
        $row['idTraffic'] = isset($row['idTraffic']) ? intval($row['idTraffic']) : null;
        $row['idRoadType'] = isset($row['idRoadType']) ? intval($row['idRoadType']) : null;
        $row['idTimeOfDay'] = isset($row['idTimeOfDay']) ? intval($row['idTimeOfDay']) : null;
        $row['mileage'] = isset($row['mileage']) ? floatval($row['mileage']) : null;
        $experiences[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'totalExperiences' => $totalExperiences,
            'totalDistance' => $totalDistance,
            'avgMileage' => $avgMileage,
            'experiences' => $experiences,
            'filters' => $filters
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
