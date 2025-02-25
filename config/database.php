<?php
// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_NAME', getenv('DB_NAME') ?: 'mydatabase');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');

// Add debugging info
file_put_contents('debug.txt', "Trying to connect to MySQL at host: " . DB_HOST . "\n", FILE_APPEND);

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            file_put_contents('debug.txt', "Database connection established successfully\n", FILE_APPEND);
        } catch (PDOException $e) {
            file_put_contents('debug.txt', "Database connection error: " . $e->getMessage() . "\n", FILE_APPEND);
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    private function generateUUID() {
        // Genera un UUID v4
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);    // Versione 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);    // Variant RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function query($sql, $params = []) {
        try {
            file_put_contents('debug.txt', "Executing query: " . $sql . "\n", FILE_APPEND);
            file_put_contents('debug.txt', "With parameters: " . print_r($params, true) . "\n", FILE_APPEND);
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            file_put_contents('debug.txt', "Query error: " . $e->getMessage() . "\n", FILE_APPEND);
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert($table, $data) {
        try {
            file_put_contents('debug.txt', "Attempting insert into table: " . $table . "\n", FILE_APPEND);
            
            // Genera un UUID per il nuovo record
            $uuid = $this->generateUUID();
            $data['id'] = $uuid;
            
            file_put_contents('debug.txt', "Generated UUID: " . $uuid . "\n", FILE_APPEND);
            file_put_contents('debug.txt', "Insert data: " . print_r($data, true) . "\n", FILE_APPEND);

            $fields = array_keys($data);
            $placeholders = array_fill(0, count($fields), '?');
            
            $sql = "INSERT INTO " . $table . " 
                    (" . implode(', ', $fields) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";
            
            file_put_contents('debug.txt', "Generated SQL: " . $sql . "\n", FILE_APPEND);
            
            $this->query($sql, array_values($data));  // Fixed array.values to array_values
            
            file_put_contents('debug.txt', "Insert successful with UUID: " . $uuid . "\n", FILE_APPEND);  // Fixed file.put_contents to file_put_contents
            
            return $uuid;
        } catch (Exception $e) {
            file_put_contents('debug.txt', "Insert error: " . $e->getMessage() . "\n", FILE_APPEND);
            throw new Exception("Insert failed: " . $e->getMessage());
        }
    }
}
?>