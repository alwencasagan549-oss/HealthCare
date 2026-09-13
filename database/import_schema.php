<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$sqlFile = __DIR__ . '/schema.postgresql.sql';

if (!is_file($sqlFile)) {
    http_response_code(500);
    echo "Schema file not found: {$sqlFile}";
    exit;
}

$sql = file_get_contents($sqlFile);

if ($sql === false || trim($sql) === '') {
    http_response_code(500);
    echo "Schema file is empty or unreadable.";
    exit;
}

$pdo = Database::getConnection();

$statements = array_filter(array_map(
    static fn ($s) => trim($s),
    preg_split('/;\s*[\r\n]+/', $sql)
));

if (!$statements) {
    echo "No SQL statements found in schema file.";
    exit;
}

$pdo->beginTransaction();

try {
    foreach ($statements as $index => $statement) {
        if ($statement === '') {
            continue;
        }

        try {
            $pdo->exec($statement);
        } catch (Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo "Statement failed at block " . ($index + 1) . ": " . $e->getMessage() . "\n\n";
            echo "SQL:\n" . $statement . "\n";
            exit;
        }
    }

    $pdo->commit();

    echo "Schema imported successfully.\n";
    echo "Statements executed: " . count($statements) . "\n";

    $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name")->fetchAll();
    echo "Tables found:\n";
    foreach ($tables as $table) {
        echo "- " . $table['table_name'] . "\n";
    }

} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "Schema import failed: " . $e->getMessage() . "\n";
    exit;
}
