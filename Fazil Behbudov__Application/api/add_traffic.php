<?php
header('Content-Type: application/json');
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['trafficType']) || empty(trim($input['trafficType']))) {
    echo json_encode(['success' => false, 'message' => 'Traffic type name is required']);
    exit;
}

try {
    $trafficType = $conn->real_escape_string(trim($input['trafficType']));
    
    // Check if it already exists
    $checkResult = $conn->query("SELECT idTraffic FROM traffic WHERE trafficType = '$trafficType'");
    
    if ($checkResult->num_rows > 0) {
        $row = $checkResult->fetch_assoc();
        echo json_encode([
            'success' => true,
            'message' => 'Traffic type already exists',
            'idTraffic' => intval($row['idTraffic']),
            'isNew' => false
        ]);
    } else {
        // Get next ID
        $maxResult = $conn->query("SELECT COALESCE(MAX(idTraffic), 0) + 1 as nextId FROM traffic");
        $maxRow = $maxResult->fetch_assoc();
        $nextId = intval($maxRow['nextId']);
        
        // Insert new traffic type
        $insertQuery = "INSERT INTO traffic (idTraffic, trafficType) VALUES ($nextId, '$trafficType')";
        
        if ($conn->query($insertQuery)) {
            echo json_encode([
                'success' => true,
                'message' => 'Traffic type added successfully',
                'idTraffic' => $nextId,
                'isNew' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to add traffic: ' . $conn->error
            ]);
        }
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
