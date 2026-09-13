<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/audit.php';

Middleware::nurse();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$pdo = Database::getConnection();
$districtId = (int)($_SESSION['district_id'] ?? 0);

$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ra.assessment_id) AS total_assessments,
           COUNT(DISTINCT CASE WHEN ra.status = 'pending' THEN ra.assessment_id END) AS pending,
           COUNT(DISTINCT CASE WHEN ra.status = 'reviewed' THEN ra.assessment_id END) AS reviewed,
           COUNT(DISTINCT CASE WHEN ra.status = 'flagged' THEN ra.assessment_id END) AS flagged
    FROM risk_assessments ra
    WHERE ra.district_id = :district_id
        AND ra.assessment_date BETWEEN :start AND :end
");
$stmt->execute([
    ':district_id' => $districtId,
    ':start' => $startDate . ' 00:00:00',
    ':end' => $endDate . ' 23:59:59',
]);
$reports = $stmt->fetchAll();

$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="mb-4">
        <h2 class="mb-0">District Reports</h2>
        <p class="text-muted mb-0">Risk assessment summary for your district</p>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= e($startDate) ?>">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= e($endDate) ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Report Results</h5>
            <span class="text-muted small"><?= e($startDate) ?> to <?= e($endDate) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Assessment</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Pending</th>
                            <th class="text-center">Reviewed</th>
                            <th class="text-center">Flagged</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$reports): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No data found for the selected period.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reports as $row): ?>
                                <tr>
                                    <td class="fw-semibold">PhilPen Risk Assessment</td>
                                    <td class="text-center"><?= e((string)$row['total_assessments']) ?></td>
                                    <td class="text-center"><?= e((string)$row['pending']) ?></td>
                                    <td class="text-center"><?= e((string)$row['reviewed']) ?></td>
                                    <td class="text-center"><?= e((string)$row['flagged']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-0 py-3 text-end">
            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print / Save as PDF
            </button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
