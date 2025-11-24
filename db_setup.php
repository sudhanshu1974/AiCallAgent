<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/Database.php';

try {
    $db = Database::getInstance();

    if (DB_TYPE === 'sqlserver') {
        echo "Running migration_script.sql...\n";
        $sql = file_get_contents(__DIR__ . '/migration_script.sql');
        $stmt = $db->query($sql);
        echo "Database setup completed for SQL Server.\n";
    }

    echo "Database setup completed successfully for " . DB_TYPE . ".\n";

} catch (Exception $e) {
    echo 'Database error: ' . $e->getMessage() . "\n";
}

?>