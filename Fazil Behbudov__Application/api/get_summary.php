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
    
    // Parse all filter IDs
    $weatherIds = $parseIds('weatherIds');
    $trafficIds = $parseIds('trafficIds');
    $roadTypeIds = $parseIds('roadTypeIds');
    $timeOfDayIds = $parseIds('timeOfDayIds');
    $maneuverIds = $parseIds('maneuverIds');
    
    // Parse dimensions (can be comma-separated)
    $dimensionParam = isset($_GET['dimension']) && $_GET['dimension'] !== '' ? $_GET['dimension'] : 'weather';
    $dimensionList = array_filter(array_map('trim', explode(',', $dimensionParam)));
    if (empty($dimensionList)) $dimensionList = ['weather'];
    
    $allowedDimensions = [
        'weather' => ['label' => 'Weather'],
        'traffic' => ['label' => 'Traffic'],
        'roadType' => ['label' => 'Road Type'],
        'timeOfDay' => ['label' => 'Time of Day'],
        'maneuver' => ['label' => 'Maneuver']
    ];
    
    // Validate and filter dimensions
    $validDimensions = [];
    foreach ($dimensionList as $dim) {
        if (array_key_exists($dim, $allowedDimensions)) {
            $validDimensions[] = $dim;
        }
    }
    if (empty($validDimensions)) $validDimensions = ['weather'];
    $dimensionList = array_unique($validDimensions);

    $filters = [
        'startDate' => $startDate,
        'endDate' => $endDate,
        'dimensions' => $dimensionList,
        'dimensionIds' => [],
        'dimensionLabels' => [],
        'weatherIds' => $weatherIds,
        'trafficIds' => $trafficIds,
        'roadTypeIds' => $roadTypeIds,
        'timeOfDayIds' => $timeOfDayIds,
        'maneuverIds' => $maneuverIds,
        'weatherLabels' => [],
        'trafficLabels' => [],
        'roadTypeLabels' => [],
        'timeOfDayLabels' => [],
        'maneuverLabels' => []
    ];

    // Helper to fetch labels for selected ids
        $fetchLabels = function($table, $idCol, $nameCol, $ids) use ($pdo) {
        if (!$ids) return [];
        $place = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT $nameCol AS label FROM $table WHERE $idCol IN ($place)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($ids);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_column($rows, 'label');
    };

    $filters['weatherLabels'] = $fetchLabels('weather', 'idWeather', 'weatherType', $weatherIds);
    $filters['trafficLabels'] = $fetchLabels('traffic', 'idTraffic', 'trafficType', $trafficIds);
    $filters['roadTypeLabels'] = $fetchLabels('roadType', 'idRoadType', 'roadTypeName', $roadTypeIds);
    $filters['timeOfDayLabels'] = $fetchLabels('timeOfDay', 'idTimeOfDay', 'periodOfDay', $timeOfDayIds);
    $filters['maneuverLabels'] = $fetchLabels('maneuver', 'idManeuver', 'maneuverType', $maneuverIds);

    // Helper function to fetch labels for a dimension (used in JSON output)
    $getDimensionLabel = function($dimension) use ($allowedDimensions) {
        return $allowedDimensions[$dimension]['label'] ?? $dimension;
    };

    $where = [];
    $params = [];

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

    $summary = [
        'totalExperiences' => 0,
        'totalDistance' => 0,
        'avgMileage' => 0,
        'breakdowns' => [], // Now an array of breakdowns by dimension
        'dimensions' => $dimensionList,
        'filters' => $filters
    ];

    $runQuery = function($sql, $params) use ($pdo) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    // Totals with DISTINCT to avoid junction duplicates
    $totalsSql = "SELECT COUNT(DISTINCT de.idDrivingExp) AS cnt, COALESCE(SUM(de.mileage),0) AS dist, COALESCE(AVG(de.mileage),0) AS avgDist
                  FROM drivingExperience de
                  $whereClause";
    $totals = $runQuery($totalsSql, $params);
    if ($totals && isset($totals[0])) {
        $summary['totalExperiences'] = (int)$totals[0]['cnt'];
        $summary['totalDistance'] = (float)$totals[0]['dist'];
        $summary['avgMileage'] = (float)$totals[0]['avgDist'];
    }

    // Helper function to build breakdown queries
    $getBreakdownQuery = function($dimension) {
        switch ($dimension) {
            case 'weather':
                return [
                    'sql' => "SELECT w.weatherType AS label, COUNT(DISTINCT de.idDrivingExp) AS cnt
                              FROM drivingExperience de
                              INNER JOIN drivingExp_weather dw ON dw.idDrivingExp = de.idDrivingExp
                              INNER JOIN weather w ON w.idWeather = dw.idWeather",
                    'where_prefix' => '',
                    'group_by' => 'GROUP BY w.idWeather'
                ];
            case 'traffic':
                return [
                    'sql' => "SELECT t.trafficType AS label, COUNT(DISTINCT de.idDrivingExp) AS cnt
                              FROM drivingExperience de
                              INNER JOIN traffic t ON t.idTraffic = de.idTraffic",
                    'where_prefix' => '',
                    'group_by' => 'GROUP BY t.idTraffic'
                ];
            case 'roadType':
                return [
                    'sql' => "SELECT r.roadTypeName AS label, COUNT(DISTINCT de.idDrivingExp) AS cnt
                              FROM drivingExperience de
                              INNER JOIN roadType r ON r.idRoadType = de.idRoadType",
                    'where_prefix' => '',
                    'group_by' => 'GROUP BY r.idRoadType'
                ];
            case 'timeOfDay':
                return [
                    'sql' => "SELECT tod.periodOfDay AS label, COUNT(DISTINCT de.idDrivingExp) AS cnt
                              FROM drivingExperience de
                              INNER JOIN timeOfDay tod ON tod.idTimeOfDay = de.idTimeOfDay",
                    'where_prefix' => '',
                    'group_by' => 'GROUP BY tod.idTimeOfDay'
                ];
            case 'maneuver':
                return [
                    'sql' => "SELECT m.maneuverType AS label, COUNT(DISTINCT de.idDrivingExp) AS cnt
                              FROM drivingExperience de
                              INNER JOIN drivingExp_maneuver dm ON dm.idDrivingExp = de.idDrivingExp
                              INNER JOIN maneuver m ON m.idManeuver = dm.idManeuver",
                    'where_prefix' => '',
                    'group_by' => 'GROUP BY m.idManeuver'
                ];
            default:
                return null;
        }
    };

    // Generate breakdowns for each selected dimension
    foreach ($dimensionList as $dim) {
        $dimQuery = $getBreakdownQuery($dim);
        if ($dimQuery) {
            $whereLocal = $where;
            $paramsLocal = $params;
            
            $sql = $dimQuery['sql'] . ' ' . ($whereLocal ? ('WHERE ' . implode(' AND ', $whereLocal)) : '') . ' ' . $dimQuery['group_by'] . ' ORDER BY cnt DESC, label ASC';
            
            $breakdown = $runQuery($sql, $paramsLocal);
            
            // Consolidate breakdown by grouping duplicate labels
            if ($breakdown) {
                $consolidated = [];
                foreach ($breakdown as $row) {
                    $label = $row['label'];
                    if (isset($consolidated[$label])) {
                        $consolidated[$label]['cnt'] += $row['cnt'];
                    } else {
                        $consolidated[$label] = $row;
                    }
                }
                // Re-sort by count descending
                usort($consolidated, function($a, $b) {
                    if ($a['cnt'] != $b['cnt']) {
                        return $b['cnt'] - $a['cnt'];
                    }
                    return strcmp($a['label'], $b['label']);
                });
                $breakdown = array_values($consolidated);
            }
            
            $summary['breakdowns'][$dim] = [
                'dimension' => $dim,
                'label' => $getDimensionLabel($dim),
                'data' => $breakdown
            ];
        }
    }

    $summary['filters'] = $filters;

    echo json_encode(['success' => true, 'data' => $summary]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

?>
