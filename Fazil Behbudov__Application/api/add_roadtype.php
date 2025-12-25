<?php
header('Content-Type: application/json');
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['roadTypeName']) || empty(trim($input['roadTypeName']))) {
    echo json_encode(['success' => false, 'message' => 'Road type name is required']);
    exit;
}

try {
    $roadTypeName = $conn->real_escape_string(trim($input['roadTypeName']));
    
    // Check if it already exists
    $checkResult = $conn->query("SELECT idRoadType FROM roadType WHERE roadTypeName = '$roadTypeName'");
    
    if ($checkResult->num_rows > 0) {
        $row = $checkResult->fetch_assoc();
        echo json_encode([
            'success' => true,
            'message' => 'Road type already exists',
            'idRoadType' => intval($row['idRoadType']),
            'isNew' => false
        ]);
    } else {
        // Get next ID
        $maxResult = $conn->query("SELECT COALESCE(MAX(idRoadType), 0) + 1 as nextId FROM roadType");
        $maxRow = $maxResult->fetch_assoc();
        $nextId = intval($maxRow['nextId']);
        
        // Insert new road type
        $insertQuery = "INSERT INTO roadType (idRoadType, roadTypeName) VALUES ($nextId, '$roadTypeName')";
        
        if ($conn->query($insertQuery)) {
            echo json_encode([
                'success' => true,
                'message' => 'Road type added successfully',
                'idRoadType' => $nextId,
                'isNew' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to add road type: ' . $conn->error
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
