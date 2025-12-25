<?php
/**
 * DrivingExperience Class
 * Represents a driving experience with all its properties
 */
class DrivingExperience {
    private $idDrivingExp;
    private $mileage;
    private $date;
    private $startTime;
    private $endTime;
    private $idUser;
    private $idTimeOfDay;
    private $idTraffic;
    private $idRoadType;
    private $weatherIds = [];
    private $maneuverIds = [];
    
    // Constructor
    public function __construct($data = []) {
        if (!empty($data)) {
            $this->loadFromArray($data);
        }
    }
    
    // Load data from array (e.g., from JSON input)
    public function loadFromArray($data) {
        $this->mileage = isset($data['mileage']) ? (float)$data['mileage'] : 0;
        $this->date = isset($data['date']) ? $data['date'] : null;
        $this->startTime = isset($data['startTime']) ? $data['startTime'] : null;
        $this->endTime = isset($data['endTime']) ? $data['endTime'] : null;
        $this->idUser = isset($data['idUser']) ? (int)$data['idUser'] : null;
        $this->idTimeOfDay = isset($data['idTimeOfDay']) ? (int)$data['idTimeOfDay'] : null;
        $this->idTraffic = isset($data['idTraffic']) ? (int)$data['idTraffic'] : null;
        $this->idRoadType = isset($data['idRoadType']) ? (int)$data['idRoadType'] : null;
    }
    
    // Getters
    public function getIdDrivingExp() { return $this->idDrivingExp; }
    public function getMileage() { return $this->mileage; }
    public function getDate() { return $this->date; }
    public function getStartTime() { return $this->startTime; }
    public function getEndTime() { return $this->endTime; }
    public function getIdUser() { return $this->idUser; }
    public function getIdTimeOfDay() { return $this->idTimeOfDay; }
    public function getIdTraffic() { return $this->idTraffic; }
    public function getIdRoadType() { return $this->idRoadType; }
    public function getWeatherIds() { return $this->weatherIds; }
    public function getManeuverIds() { return $this->maneuverIds; }
    
    // Setters
    public function setIdDrivingExp($id) { $this->idDrivingExp = (int)$id; }
    public function setMileage($mileage) { $this->mileage = (float)$mileage; }
    public function setDate($date) { $this->date = $date; }
    public function setStartTime($time) { $this->startTime = $time; }
    public function setEndTime($time) { $this->endTime = $time; }
    public function setIdUser($id) { $this->idUser = (int)$id; }
    public function setIdTimeOfDay($id) { $this->idTimeOfDay = (int)$id; }
    public function setIdTraffic($id) { $this->idTraffic = $id ? (int)$id : null; }
    public function setIdRoadType($id) { $this->idRoadType = $id ? (int)$id : null; }
    public function addWeatherId($id) { $this->weatherIds[] = (int)$id; }
    public function addManeuverId($id) { $this->maneuverIds[] = (int)$id; }
    
    // Calculate duration in minutes
    public function getDuration() {
        if ($this->startTime && $this->endTime) {
            $start = strtotime($this->startTime);
            $end = strtotime($this->endTime);
            return ($end - $start) / 60; // Returns minutes
        }
        return null;
    }
    
    // Validate required fields
    public function isValid() {
        return !empty($this->mileage) && 
               !empty($this->date) && 
               !empty($this->idUser) && 
               !empty($this->idTimeOfDay);
    }
    
    // Convert to array
    public function toArray() {
        return [
            'idDrivingExp' => $this->idDrivingExp,
            'mileage' => $this->mileage,
            'date' => $this->date,
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
            'idUser' => $this->idUser,
            'idTimeOfDay' => $this->idTimeOfDay,
            'idTraffic' => $this->idTraffic,
            'idRoadType' => $this->idRoadType,
            'duration' => $this->getDuration()
        ];
    }
}
?>
