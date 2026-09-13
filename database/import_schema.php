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

$statements = splitPostgresSql($sql);

if (!$statements) {
    echo "No SQL statements found in schema file.";
    exit;
}

$pdo->beginTransaction();

try {
    foreach ($statements as $index => $statement) {
        $statement = trim($statement);
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

/**
 * Split PostgreSQL SQL by top-level statement-ending semicolons,
 * while preserving $$...$$ dollar-quoted blocks.
 */
function splitPostgresSql(string $sql): array
{
    $statements = [];
    $current = '';
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];

        if ($char === '$') {
            $dollarEnd = strpos($sql, '$', $i + 1);
            if ($dollarEnd === false) {
                $current .= $char;
                continue;
            }

            $tag = substr($sql, $i + 1, $dollarEnd - $i - 1);
            $endMarker = '$' . $tag . '$';
            $endPos = strpos($sql, $endMarker, $dollarEnd);
            if ($endPos === false) {
                $current .= $char;
                continue;
            }

            $current .= substr($sql, $i, $endPos - $i + strlen($endMarker));
            $i = $endPos + strlen($endMarker) - 1;
            continue;
        }

        if ($char === ';') {
            $statements[] = $current;
            $current = '';
            continue;
        }

        $current .= $char;
    }

    $last = trim($current);
    if ($last !== '') {
        $statements[] = $last;
    }

    return $statements;
}
