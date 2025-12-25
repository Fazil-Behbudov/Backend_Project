<?php
require_once 'config.php';

try {
    // Disable foreign key checks temporarily
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    
    // Update any references from duplicate IDs to their originals
    // ID 4 (Turn left) -> ID 1 (Turn left)
    // ID 5 (Turn right) -> ID 2 (Turn right)
    // ID 6 (Lane change) -> ID 3 (Lane change)
    
    $conn->query("UPDATE drivingExp_maneuver SET idManeuver = 1 WHERE idManeuver = 4");
    $conn->query("UPDATE drivingExp_maneuver SET idManeuver = 2 WHERE idManeuver = 5");
    $conn->query("UPDATE drivingExp_maneuver SET idManeuver = 3 WHERE idManeuver = 6");
    
    // Now delete the duplicate entries
    $conn->query("DELETE FROM maneuver WHERE idManeuver IN (4, 5, 6)");
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
    
    echo json_encode(['success' => true, 'message' => 'Duplicates removed and references updated']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
?>
