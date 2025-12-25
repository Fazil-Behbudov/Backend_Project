<?php
/**
 * Save Driving Experience API
 * 
 * Features:
 * - PDO with prepared statements and named parameters
 * - OOP classes (DrivingExperience, User, DatabaseHelper)
 * - Session management with ID anonymization
 * - Transaction support
 * - Secure parameter binding with bindParam()
 */

header('Content-Type: application/json');
require_once 'config.php';
require_once 'classes/DrivingExperience.php';
require_once 'classes/User.php';
require_once 'classes/DatabaseHelper.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    $db = new DatabaseHelper($pdo);
    
    // Start transaction
    $db->beginTransaction();
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    
    // 1. Create User object and save to database
    $user = new User($input['userName'], isset($input['age']) ? $input['age'] : null);
    
    if (!$user->isValid()) {
        throw new Exception('Invalid user data');
    }
    
    // Check if user exists using prepared statement
    $stmt = $pdo->prepare("SELECT idUser FROM user WHERE userName = :userName LIMIT 1");
    $stmt->bindParam(':userName', $input['userName'], PDO::PARAM_STR);
    $stmt->execute();
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        $idUser = (int)$existingUser['idUser'];
        $user->setIdUser($idUser);
    } else {
        // Insert new user with prepared statement
        $stmt = $pdo->prepare("INSERT INTO user (userName, age) VALUES (:userName, :age)");
        $userName = $user->getUserName();
        $age = $user->getAge();
        $stmt->bindParam(':userName', $userName, PDO::PARAM_STR);
        $stmt->bindParam(':age', $age, PDO::PARAM_INT);
        $stmt->execute();
        $idUser = (int)$pdo->lastInsertId();
        $user->setIdUser($idUser);
    }
    
    // Store anonymized user code in session
    if (!isset($_SESSION['user_codes'][$idUser])) {
        $_SESSION['user_codes'][$idUser] = generateAnonymousCode($idUser, 'USR');
    }
    
    // 2. Handle optional lookup values (Weather, Traffic, RoadType)
    $idWeather = isset($input['idWeather']) && (int)$input['idWeather'] > 0 ? (int)$input['idWeather'] : null;
    if (isset($input['newWeatherType']) && trim($input['newWeatherType']) !== '') {
        $idWeather = $db->getOrCreateLookupValue('weather', 'idWeather', 'weatherType', trim($input['newWeatherType']));
    }
    
    $idTraffic = isset($input['idTraffic']) && (int)$input['idTraffic'] > 0 ? (int)$input['idTraffic'] : null;
    if (isset($input['newTrafficType']) && trim($input['newTrafficType']) !== '') {
        $idTraffic = $db->getOrCreateLookupValue('traffic', 'idTraffic', 'trafficType', trim($input['newTrafficType']));
    }
    
    $idRoadType = isset($input['idRoadType']) && (int)$input['idRoadType'] > 0 ? (int)$input['idRoadType'] : null;
    if (isset($input['newRoadTypeName']) && trim($input['newRoadTypeName']) !== '') {
        $idRoadType = $db->getOrCreateLookupValue('roadType', 'idRoadType', 'roadTypeName', trim($input['newRoadTypeName']));
    }
    
    // 3. Create DrivingExperience object
    $experience = new DrivingExperience();
    $experience->setMileage($input['mileage']);
    $experience->setDate($input['date']);
    $experience->setStartTime(isset($input['startTime']) ? $input['startTime'] : null);
    $experience->setEndTime(isset($input['endTime']) ? $input['endTime'] : null);
    $experience->setIdUser($idUser);
    $experience->setIdTimeOfDay($input['idTimeOfDay']);
    $experience->setIdTraffic($idTraffic);
    $experience->setIdRoadType($idRoadType);
    
    if (!$experience->isValid()) {
        throw new Exception('Invalid driving experience data');
    }
    
    // Get next available ID using prepared statement
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(idDrivingExp), 0) + 1 AS nextId FROM drivingExperience");
    $stmt->execute();
    $idRow = $stmt->fetch();
    $idDrivingExp = (int)$idRow['nextId'];
    $experience->setIdDrivingExp($idDrivingExp);
    
    // Insert driving experience using prepared statement with named parameters
    if ($experience->getStartTime() && $experience->getEndTime()) {
        $sql = "INSERT INTO drivingExperience 
                (idDrivingExp, mileage, date, startTime, endTime, idUser, idTimeOfDay, idTraffic, idRoadType) 
                VALUES (:idDrivingExp, :mileage, :date, :startTime, :endTime, :idUser, :idTimeOfDay, :idTraffic, :idRoadType)";
        $stmt = $pdo->prepare($sql);
        
        $idDrivingExpVal = $experience->getIdDrivingExp();
        $mileageVal = $experience->getMileage();
        $dateVal = $experience->getDate();
        $startTimeVal = $experience->getStartTime();
        $endTimeVal = $experience->getEndTime();
        $idUserVal = $experience->getIdUser();
        $idTimeOfDayVal = $experience->getIdTimeOfDay();
        $idTrafficVal = $experience->getIdTraffic();
        $idRoadTypeVal = $experience->getIdRoadType();
        
        $stmt->bindParam(':idDrivingExp', $idDrivingExpVal, PDO::PARAM_INT);
        $stmt->bindParam(':mileage', $mileageVal, PDO::PARAM_STR);
        $stmt->bindParam(':date', $dateVal, PDO::PARAM_STR);
        $stmt->bindParam(':startTime', $startTimeVal, PDO::PARAM_STR);
        $stmt->bindParam(':endTime', $endTimeVal, PDO::PARAM_STR);
        $stmt->bindParam(':idUser', $idUserVal, PDO::PARAM_INT);
        $stmt->bindParam(':idTimeOfDay', $idTimeOfDayVal, PDO::PARAM_INT);
        $stmt->bindParam(':idTraffic', $idTrafficVal, PDO::PARAM_INT);
        $stmt->bindParam(':idRoadType', $idRoadTypeVal, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $sql = "INSERT INTO drivingExperience 
                (idDrivingExp, mileage, date, idUser, idTimeOfDay, idTraffic, idRoadType) 
                VALUES (:idDrivingExp, :mileage, :date, :idUser, :idTimeOfDay, :idTraffic, :idRoadType)";
        $stmt = $pdo->prepare($sql);
        
        $idDrivingExpVal = $experience->getIdDrivingExp();
        $mileageVal = $experience->getMileage();
        $dateVal = $experience->getDate();
        $idUserVal = $experience->getIdUser();
        $idTimeOfDayVal = $experience->getIdTimeOfDay();
        $idTrafficVal = $experience->getIdTraffic();
        $idRoadTypeVal = $experience->getIdRoadType();
        
        $stmt->bindParam(':idDrivingExp', $idDrivingExpVal, PDO::PARAM_INT);
        $stmt->bindParam(':mileage', $mileageVal, PDO::PARAM_STR);
        $stmt->bindParam(':date', $dateVal, PDO::PARAM_STR);
        $stmt->bindParam(':idUser', $idUserVal, PDO::PARAM_INT);
        $stmt->bindParam(':idTimeOfDay', $idTimeOfDayVal, PDO::PARAM_INT);
        $stmt->bindParam(':idTraffic', $idTrafficVal, PDO::PARAM_INT);
        $stmt->bindParam(':idRoadType', $idRoadTypeVal, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // Store anonymized experience code in session
    $_SESSION['experience_codes'][$idDrivingExp] = generateAnonymousCode($idDrivingExp, 'EXP');
    
    // 4. Insert weather relation if provided
    if ($idWeather && $idWeather > 0) {
        $stmt = $pdo->prepare("INSERT INTO drivingExp_weather (idWeather, idDrivingExp) VALUES (:idWeather, :idDrivingExp)");
        $stmt->bindParam(':idWeather', $idWeather, PDO::PARAM_INT);
        $stmt->bindParam(':idDrivingExp', $idDrivingExp, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // 5. Insert maneuver relation if provided
    if (isset($input['idManeuver']) && (int)$input['idManeuver'] > 0) {
        $idManeuver = (int)$input['idManeuver'];
        $stmt = $pdo->prepare("INSERT INTO drivingExp_maneuver (idManeuver, idDrivingExp) VALUES (:idManeuver, :idDrivingExp)");
        $stmt->bindParam(':idManeuver', $idManeuver, PDO::PARAM_INT);
        $stmt->bindParam(':idDrivingExp', $idDrivingExp, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // Commit transaction
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    $db->commit();
    
    // Return success with anonymized code
    echo json_encode([
        'success' => true,
        'message' => 'Driving experience saved successfully',
        'idDrivingExp' => $idDrivingExp,
        'anonymousCode' => $_SESSION['experience_codes'][$idDrivingExp]
    ]);
    
} catch (PDOException $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error saving experience: ' . $e->getMessage()
    ]);
}
?>
