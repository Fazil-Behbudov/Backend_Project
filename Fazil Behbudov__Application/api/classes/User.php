<?php
/**
 * User Class
 * Represents a user with their information
 */
class User {
    private $idUser;
    private $userName;
    private $age;
    
    // Constructor
    public function __construct($userName = null, $age = null) {
        $this->userName = $userName;
        $this->age = $age;
    }
    
    // Getters
    public function getIdUser() { return $this->idUser; }
    public function getUserName() { return $this->userName; }
    public function getAge() { return $this->age; }
    
    // Setters
    public function setIdUser($id) { $this->idUser = (int)$id; }
    public function setUserName($name) { $this->userName = $name; }
    public function setAge($age) { $this->age = $age ? (int)$age : null; }
    
    // Validate
    public function isValid() {
        return !empty($this->userName);
    }
    
    // Convert to array
    public function toArray() {
        return [
            'idUser' => $this->idUser,
            'userName' => $this->userName,
            'age' => $this->age
        ];
    }
}
?>
