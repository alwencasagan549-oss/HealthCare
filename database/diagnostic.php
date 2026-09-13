<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::getConnection();

    echo "<pre>";
    echo "Driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";
    echo "Connected.\n\n";

    $dbName = $pdo->query("SELECT current_database()")->fetchColumn();
    echo "Current database: " . $dbName . "\n\n";

    $tables = $pdo->query("
        SELECT table_schema, table_name
        FROM information_schema.tables
        WHERE table_type = 'BASE TABLE'
        ORDER BY table_schema, table_name
    ")->fetchAll();

    echo "Tables:\n";
    foreach ($tables as $table) {
        echo "- " . $table['table_schema'] . "." . $table['table_name'] . "\n";
    }

    if (!$tables) {
        echo "\nNo tables found.\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "Connection failed: " . $e->getMessage() . "\n";
}
