<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::getConnection();
    echo "Connected: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n\n";

    $queries = [
        'districts' => "SELECT COUNT(*) FROM districts WHERE status = 'active'",
        'nurses' => "SELECT COUNT(*) FROM users WHERE role = 'nurse' AND status = 'active'",
        'patients' => "SELECT COUNT(*) FROM patients WHERE status = 'active'",
        'surveys' => "SELECT COUNT(*) FROM surveys WHERE is_active = 1",
        'dpwh' => "SELECT COUNT(*) FROM users WHERE role = 'dpwh' AND status = 'active'",
        'pending' => "SELECT COUNT(*) FROM survey_results WHERE status = 'pending'",
        'district_rows' => "
            SELECT d.district_id, d.district_name, d.district_code,
                   COUNT(DISTINCT p.patient_id) AS patient_count,
                   COUNT(DISTINCT u.user_id) AS nurse_count,
                   COUNT(DISTINCT dp.user_id) AS dpwh_count
            FROM districts d
            LEFT JOIN patients p ON p.district_id = d.district_id AND p.status = 'active'
            LEFT JOIN users u ON u.district_id = d.district_id AND u.role = 'nurse' AND u.status = 'active'
            LEFT JOIN users dp ON dp.district_id = d.district_id AND dp.role = 'dpwh' AND dp.status = 'active'
            WHERE d.status = 'active'
            GROUP BY d.district_id
            ORDER BY d.district_name
        ",
    ];

    foreach ($queries as $label => $sql) {
        try {
            $stmt = $pdo->query($sql);
            $result = $stmt->fetchAll();
            echo strtoupper($label) . " OK\n";
            echo $sql . "\n";
            echo "Result count: " . count($result) . "\n";
            if ($result) {
                print_r($result);
            }
            echo "\n";
        } catch (Throwable $e) {
            echo strtoupper($label) . " FAILED: " . $e->getMessage() . "\n\n";
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "Connection failed: " . $e->getMessage() . "\n";
}
