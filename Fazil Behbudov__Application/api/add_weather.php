<?php
header('Content-Type: application/json');
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['weatherType']) || empty(trim($input['weatherType']))) {
    echo json_encode(['success' => false, 'message' => 'Weather type name is required']);
    exit;
}

try {
    $weatherType = $conn->real_escape_string(trim($input['weatherType']));
    
    // Check if it already exists
    $checkResult = $conn->query("SELECT idWeather FROM weather WHERE weatherType = '$weatherType'");
    
    if ($checkResult->num_rows > 0) {
        $row = $checkResult->fetch_assoc();
        echo json_encode([
            'success' => true,
            'message' => 'Weather type already exists',
            'idWeather' => intval($row['idWeather']),
            'isNew' => false
        ]);
    } else {
        // Get next ID
        $maxResult = $conn->query("SELECT COALESCE(MAX(idWeather), 0) + 1 as nextId FROM weather");
        $maxRow = $maxResult->fetch_assoc();
        $nextId = intval($maxRow['nextId']);
        
        // Insert new weather
        $insertQuery = "INSERT INTO weather (idWeather, weatherType) VALUES ($nextId, '$weatherType')";
        
        if ($conn->query($insertQuery)) {
            echo json_encode([
                'success' => true,
                'message' => 'Weather type added successfully',
                'idWeather' => $nextId,
                'isNew' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to add weather: ' . $conn->error
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
