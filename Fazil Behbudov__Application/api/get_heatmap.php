<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
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

    $dimA = isset($_GET['dimA']) ? $_GET['dimA'] : null;
    $dimB = isset($_GET['dimB']) ? $_GET['dimB'] : null;

    $allowed = [
        'weather' => ['table' => 'weather', 'id' => 'idWeather', 'label' => 'weatherType', 'join' => 'INNER JOIN drivingExp_weather dw ON dw.idDrivingExp = de.idDrivingExp INNER JOIN weather w ON w.idWeather = dw.idWeather'],
        'traffic' => ['table' => 'traffic', 'id' => 'idTraffic', 'label' => 'trafficType', 'join' => 'INNER JOIN traffic t ON t.idTraffic = de.idTraffic'],
        'roadType' => ['table' => 'roadType', 'id' => 'idRoadType', 'label' => 'roadTypeName', 'join' => 'INNER JOIN roadType r ON r.idRoadType = de.idRoadType'],
        'timeOfDay' => ['table' => 'timeOfDay', 'id' => 'idTimeOfDay', 'label' => 'periodOfDay', 'join' => 'INNER JOIN timeOfDay tod ON tod.idTimeOfDay = de.idTimeOfDay'],
        'maneuver' => ['table' => 'maneuver', 'id' => 'idManeuver', 'label' => 'maneuverType', 'join' => 'INNER JOIN drivingExp_maneuver dm ON dm.idDrivingExp = de.idDrivingExp INNER JOIN maneuver m ON m.idManeuver = dm.idManeuver']
    ];

    if (!$dimA || !$dimB || !isset($allowed[$dimA]) || !isset($allowed[$dimB])) {
        throw new Exception('Invalid dimensions for heatmap');
    }

    // Filters
    $startDate = isset($_GET['startDate']) && $_GET['startDate'] !== '' ? $_GET['startDate'] : null;
    $endDate = isset($_GET['endDate']) && $_GET['endDate'] !== '' ? $_GET['endDate'] : null;
    $weatherIds = $parseIds('weatherIds');
    $trafficIds = $parseIds('trafficIds');
    $roadTypeIds = $parseIds('roadTypeIds');
    $timeOfDayIds = $parseIds('timeOfDayIds');
    $maneuverIds = $parseIds('maneuverIds');

    $where = [];
    $params = [];
    if ($startDate) { $where[] = 'de.date >= ?'; $params[] = $startDate; }
    if ($endDate) { $where[] = 'de.date <= ?'; $params[] = $endDate; }
    if ($trafficIds) { $place = implode(',', array_fill(0, count($trafficIds), '?')); $where[] = "de.idTraffic IN ($place)"; $params = array_merge($params, $trafficIds); }
    if ($roadTypeIds) { $place = implode(',', array_fill(0, count($roadTypeIds), '?')); $where[] = "de.idRoadType IN ($place)"; $params = array_merge($params, $roadTypeIds); }
    if ($timeOfDayIds) { $place = implode(',', array_fill(0, count($timeOfDayIds), '?')); $where[] = "de.idTimeOfDay IN ($place)"; $params = array_merge($params, $timeOfDayIds); }
    if ($weatherIds) { $place = implode(',', array_fill(0, count($weatherIds), '?')); $where[] = "EXISTS (SELECT 1 FROM drivingExp_weather dw2 WHERE dw2.idDrivingExp = de.idDrivingExp AND dw2.idWeather IN ($place))"; $params = array_merge($params, $weatherIds); }
    if ($maneuverIds) { $place = implode(',', array_fill(0, count($maneuverIds), '?')); $where[] = "EXISTS (SELECT 1 FROM drivingExp_maneuver dm2 WHERE dm2.idDrivingExp = de.idDrivingExp AND dm2.idManeuver IN ($place))"; $params = array_merge($params, $maneuverIds); }

    $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Build pair query
    $joinA = $allowed[$dimA]['join'];
    $joinB = $allowed[$dimB]['join'];
    $labelA = $allowed[$dimA]['label'];
    $labelB = $allowed[$dimB]['label'];

    $sql = "SELECT a.labelA, b.labelB, COUNT(DISTINCT de.idDrivingExp) AS cnt
            FROM drivingExperience de
            $joinA
            $joinB
            " . ($whereClause ? $whereClause : '') . "
            GROUP BY a.labelA, b.labelB
            ORDER BY cnt DESC";

    // Adjust alias mapping
    $sql = str_replace(['w.weatherType AS label', 't.trafficType AS label', 'r.roadTypeName AS label', 'tod.periodOfDay AS label', 'm.maneuverType AS label'], ['w.weatherType AS labelA', 't.trafficType AS labelA', 'r.roadTypeName AS labelA', 'tod.periodOfDay AS labelA', 'm.maneuverType AS labelA'], $sql);
    $sql = str_replace(['w.weatherType', 't.trafficType', 'r.roadTypeName', 'tod.periodOfDay', 'm.maneuverType'], ['w.weatherType AS labelB', 't.trafficType AS labelB', 'r.roadTypeName AS labelB', 'tod.periodOfDay AS labelB', 'm.maneuverType AS labelB'], $sql);

    // Actually craft explicit SQL to avoid fragile replace
    $mapJoin = function($dim, $alias) use ($allowed) {
        switch ($dim) {
            case 'weather':
                return "INNER JOIN drivingExp_weather dw$alias ON dw$alias.idDrivingExp = de.idDrivingExp INNER JOIN weather w$alias ON w$alias.idWeather = dw$alias.idWeather";
            case 'traffic':
                return "INNER JOIN traffic t$alias ON t$alias.idTraffic = de.idTraffic";
            case 'roadType':
                return "INNER JOIN roadType r$alias ON r$alias.idRoadType = de.idRoadType";
            case 'timeOfDay':
                return "INNER JOIN timeOfDay tod$alias ON tod$alias.idTimeOfDay = de.idTimeOfDay";
            case 'maneuver':
                return "INNER JOIN drivingExp_maneuver dm$alias ON dm$alias.idDrivingExp = de.idDrivingExp INNER JOIN maneuver m$alias ON m$alias.idManeuver = dm$alias.idManeuver";
            default:
                return '';
        }
    };

    $joinA = $mapJoin($dimA, 'A');
    $joinB = $mapJoin($dimB, 'B');
    $labelSelectA = '';
    $labelSelectB = '';
    switch ($dimA) {
        case 'weather': $labelSelectA = 'wA.weatherType'; break;
        case 'traffic': $labelSelectA = 'tA.trafficType'; break;
        case 'roadType': $labelSelectA = 'rA.roadTypeName'; break;
        case 'timeOfDay': $labelSelectA = 'todA.periodOfDay'; break;
        case 'maneuver': $labelSelectA = 'mA.maneuverType'; break;
    }
    switch ($dimB) {
        case 'weather': $labelSelectB = 'wB.weatherType'; break;
        case 'traffic': $labelSelectB = 'tB.trafficType'; break;
        case 'roadType': $labelSelectB = 'rB.roadTypeName'; break;
        case 'timeOfDay': $labelSelectB = 'todB.periodOfDay'; break;
        case 'maneuver': $labelSelectB = 'mB.maneuverType'; break;
    }

    $sql = "SELECT $labelSelectA AS labelA, $labelSelectB AS labelB, COUNT(DISTINCT de.idDrivingExp) AS cnt
            FROM drivingExperience de
            $joinA
            $joinB
            " . ($whereClause ? $whereClause : '') . "
            GROUP BY labelA, labelB
            ORDER BY labelA ASC, labelB ASC";

    $runQuery = function($sql, $params) use ($pdo) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    $rows = $runQuery($sql, $params);

    // Build matrix structure
    $matrix = ['rows' => []];
    $byRow = [];
    foreach ($rows as $r) {
        $rowLabel = $r['labelA'];
        $colLabel = $r['labelB'];
        $cnt = (int)$r['cnt'];
        if (!isset($byRow[$rowLabel])) {
            $byRow[$rowLabel] = [];
        }
        $byRow[$rowLabel][$colLabel] = $cnt;
    }

    // Collect unique column labels
    $colLabels = [];
    foreach ($rows as $r) {
        $colLabels[$r['labelB']] = true;
    }
    $colLabels = array_keys($colLabels);

    foreach ($byRow as $rowLabel => $cols) {
        $rowVals = [];
        foreach ($colLabels as $cLabel) {
            $rowVals[] = ['label' => $cLabel, 'count' => $cols[$cLabel] ?? 0];
        }
        $matrix['rows'][] = ['label' => $rowLabel, 'values' => $rowVals];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'matrix' => $matrix,
            'labels' => [ 'dimA' => ucfirst($dimA), 'dimB' => ucfirst($dimB) ]
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
