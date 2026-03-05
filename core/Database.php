<?php
require_once __DIR__ . '/../config/database.php';

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            die("Database connection failed: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    // Fetch all rows
    public function fetchAll($sql, $params = [], $types = '') {
        $stmt = $this->prepare($sql, $params, $types);
        if (!$stmt) return [];

        $stmt->execute();
        $stmt->store_result();

        $meta    = $stmt->result_metadata();
        $fields  = [];
        $row     = [];
        $results = [];

        if (!$meta) return [];

        while ($field = $meta->fetch_field()) {
            $fields[] = &$row[$field->name];
        }
        call_user_func_array([$stmt, 'bind_result'], $fields);

        while ($stmt->fetch()) {
            $results[] = array_map(fn($v) => $v, $row);
        }

        $stmt->free_result();
        $stmt->close();
        return $results;
    }

    // Fetch single row
    public function fetchOne($sql, $params = [], $types = '') {
        $stmt = $this->prepare($sql, $params, $types);
        if (!$stmt) return null;

        $stmt->execute();
        $stmt->store_result();

        $meta   = $stmt->result_metadata();
        $fields = [];
        $row    = [];

        if (!$meta) {
            $stmt->free_result();
            $stmt->close();
            return null;
        }

        while ($field = $meta->fetch_field()) {
            $fields[] = &$row[$field->name];
        }
        call_user_func_array([$stmt, 'bind_result'], $fields);

        if ($stmt->fetch()) {
            $result = array_map(fn($v) => $v, $row);
        } else {
            $result = null;
        }

        $stmt->free_result();
        $stmt->close();
        return $result;
    }

    // Insert - returns insert ID
    public function insert($sql, $params = [], $types = '') {
        $stmt = $this->prepare($sql, $params, $types);
        if (!$stmt) return false;
        $stmt->execute();
        $id = $this->conn->insert_id;
        $stmt->close();
        return $id;
    }

    // Update / Delete - returns affected rows
    public function execute($sql, $params = [], $types = '') {
        $stmt = $this->prepare($sql, $params, $types);
        if (!$stmt) return false;
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    // Internal: prepare statement
    private function prepare($sql, $params, $types) {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("SQL Error: " . $this->conn->error . " | Query: " . $sql);
            return false;
        }
        if (!empty($params)) {
            if (empty($types)) {
                $types = str_repeat('s', count($params));
            }
            $stmt->bind_param($types, ...$params);
        }
        return $stmt;
    }

    public function getError() {
        return $this->conn->error;
    }
}