<?php
declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/helpers.php';

$pdo = Database::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$districtId = 4;
$name = 'Test District';
$code = 'TST';
$address = '';
$contactNumber = '';
$email = '';
$status = 'active';

try {
    $stmt = $pdo->prepare("
        UPDATE districts
        SET district_name = :name, district_code = :code, address = :address,
            contact_number = :contact, email = :email, status = :status
        WHERE district_id = :id
    ");
    $stmt->execute([
        ':name' => $name,
        ':code' => $code,
        ':address' => $address ?: null,
        ':contact' => $contactNumber ?: null,
        ':email' => $email ?: null,
        ':status' => $status,
        ':id' => $districtId,
    ]);

    echo "UPDATE ok rows=" . $stmt->rowCount() . PHP_EOL;
} catch (Throwable $e) {
    echo "UPDATE failed: " . $e->getMessage() . PHP_EOL;
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM districts WHERE district_id = :id LIMIT 1");
    $stmt->execute([':id' => $districtId]);
    $row = $stmt->fetch();

    echo "Fetched row: " . print_r($row, true) . PHP_EOL;
} catch (Throwable $e) {
    echo "SELECT failed: " . $e->getMessage() . PHP_EOL;
}
