<?php
// Database.php

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $connection;
    private $dbType;

    private function __construct() {
        $this->dbType = DB_TYPE;
        if ($this->dbType === 'sqlserver') {
            try {
                $serverName = SQLSRV_SERVERNAME;
                $connectionOptions = [
                    "Database" => SQLSRV_DATABASE,
                    "Uid" => SQLSRV_UID,
                    "PWD" => SQLSRV_PWD
                ];
                $this->connection = sqlsrv_connect($serverName, $connectionOptions);
                if ($this->connection === false) {
                    throw new Exception("SQL Server connection failed: " . print_r(sqlsrv_errors(), true));
                }
            } catch (Exception $e) {
                die("SQL Server connection failed: " . $e->getMessage());
            }
        } else {
            die("Unsupported DB_TYPE in config.php");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql, $params = []) {
        if ($this->dbType === 'sqlserver') {
            $stmt = sqlsrv_query($this->connection, $sql, $params);
            if ($stmt === false) {
                // Log the error
                error_log("SQL Server Query Error: " . print_r(sqlsrv_errors(), true) . " SQL: " . $sql);
                throw new Exception("SQL Server Query Failed: " . print_r(sqlsrv_errors(), true));
            }
            return $stmt;
        }
    }

    public function fetch($stmt) {
        if ($this->dbType === 'sqlserver') {
            return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        }
    }
    
    public function fetchAll($stmt) {
        $rows = [];
        while ($row = $this->fetch($stmt)) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function getLastInsertId() {
        if ($this->dbType === 'sqlserver') {
            $sql = "SELECT SCOPE_IDENTITY() as id";
            // Add logging for debugging getLastInsertId
            error_log("Database.php: Executing getLastInsertId query: " . $sql);
            $stmt = $this->query($sql);
            if ($stmt === false) {
                error_log("Database.php: getLastInsertId query failed.");
                return null; // Or throw an exception
            }
            $row = $this->fetch($stmt);
            error_log("Database.php: getLastInsertId fetched row: " . var_export($row, true));
            if ($row && isset($row['id'])) {
                error_log("Database.php: getLastInsertId returning ID: " . $row['id']);
                return $row['id'];
            } else {
                error_log("Database.php: getLastInsertId did not find 'id' in row or row is empty.");
                return null;
            }
        }
    }
    
    public function close() {
        if ($this->dbType === 'sqlserver') {
            sqlsrv_close($this->connection);
        }
        self::$instance = null;
    }
}
?>