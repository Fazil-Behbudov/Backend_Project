<?php
/**
 * DatabaseHelper Class
 * Provides helper methods for database operations using PDO
 */
class DatabaseHelper {
    private $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Execute a prepared statement with named parameters
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Query execution failed: " . $e->getMessage());
        }
    }
    
    /**
     * Fetch all rows from a query
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Fetch single row from a query
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Get last inserted ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * Get or create a lookup value (Weather, Traffic, RoadType)
     */
    public function getOrCreateLookupValue($table, $idColumn, $nameColumn, $value) {
        // Try to find existing
        $sql = "SELECT $idColumn FROM $table WHERE $nameColumn = :value LIMIT 1";
        $result = $this->fetchOne($sql, [':value' => $value]);
        
        if ($result) {
            return (int)$result[$idColumn];
        }
        
        // Get next ID
        $nextIdSql = "SELECT COALESCE(MAX($idColumn), 0) + 1 AS nextId FROM $table";
        $nextIdResult = $this->fetchOne($nextIdSql);
        $nextId = (int)$nextIdResult['nextId'];
        
        // Insert new value
        $insertSql = "INSERT INTO $table ($idColumn, $nameColumn) VALUES (:id, :value)";
        $this->execute($insertSql, [':id' => $nextId, ':value' => $value]);
        
        return $nextId;
    }
}
?>
