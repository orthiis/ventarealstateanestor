<?php
// database.php - Clase para manejo de base de datos

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                die("Error de conexión: " . $e->getMessage());
            } else {
                die("Error al conectar con la base de datos");
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                die("Error en consulta: " . $e->getMessage() . "\nSQL: " . $sql);
            }
            error_log("DB Error: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }
    
    public function select($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }
    
    public function selectOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : null;
    }
    
    public function selectValue($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        if (!$stmt) {
            return null;
        }
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return $row ? $row[0] : null;
    }
    
    public function insert($table, $data) {
        $fields = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
        
        $this->query($sql, $data);
        return $this->connection->lastInsertId();
    }
    
    // ✅ MÉTODO UPDATE CORREGIDO
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        $params = [];
        
        // Crear SET con placeholders nombrados únicos
        $i = 0;
        foreach ($data as $key => $value) {
            $placeholder = "set_{$i}_{$key}";
            $set[] = "{$key} = :{$placeholder}";
            $params[$placeholder] = $value;
            $i++;
        }
        $set = implode(', ', $set);
        
        // Procesar WHERE params
        if (is_array($whereParams)) {
            // Si whereParams es array asociativo, usarlo directamente
            if (array_keys($whereParams) !== range(0, count($whereParams) - 1)) {
                // Es asociativo
                $params = array_merge($params, $whereParams);
            } else {
                // Es numérico, convertir ? a placeholders nombrados
                $whereCounter = 0;
                $where = preg_replace_callback('/\?/', function() use (&$whereCounter, $whereParams, &$params) {
                    $placeholder = "where_" . $whereCounter;
                    $params[$placeholder] = $whereParams[$whereCounter];
                    $whereCounter++;
                    return ":{$placeholder}";
                }, $where);
            }
        }
        
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        
        return $this->query($sql, $params);
    }
    
    public function delete($table, $where, $params = []) {
        // Convertir ? a placeholders nombrados
        if (is_array($params) && array_keys($params) === range(0, count($params) - 1)) {
            $namedParams = [];
            $whereCounter = 0;
            $where = preg_replace_callback('/\?/', function() use (&$whereCounter, $params, &$namedParams) {
                $placeholder = "where_" . $whereCounter;
                $namedParams[$placeholder] = $params[$whereCounter];
                $whereCounter++;
                return ":{$placeholder}";
            }, $where);
            $params = $namedParams;
        }
        
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params);
    }
    
    public function count($table, $where = '', $params = []) {
        $sql = "SELECT COUNT(*) as total FROM {$table}";
        if ($where) {
            $sql .= " WHERE {$where}";
        }
        $result = $this->selectOne($sql, $params);
        return $result ? $result['total'] : 0;
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    public function inTransaction() {
        return $this->connection->inTransaction();
    }
}

// Función helper para obtener la instancia de la base de datos
function db() {
    return Database::getInstance();
}