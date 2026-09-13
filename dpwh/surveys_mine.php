<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/audit.php';

Middleware::dpwh();
Middleware::dpwhFirstLogin();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$pdo = Database::getConnection();
$districtId = (int)($_SESSION['district_id'] ?? 0);
$dpwhId = (int)($_SESSION['user_id'] ?? 0);

$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = 'ra.conducted_by = :dpwh_id';
$params = [':dpwh_id' => $dpwhId];

if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'reviewed', 'flagged'], true)) {
    $where .= ' AND ra.status = :status';
    $params[':status'] = $statusFilter;
}

$totalStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM risk_assessments ra
    WHERE {$where}
");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$totalPages = (int)ceil($total / $perPage);
if ($totalPages === 0) { $totalPages = 1; }
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT ra.assessment_id, ra.status, ra.created_at, ra.assessment_date,
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name
    FROM risk_assessments ra
    JOIN patients p ON p.patient_id = ra.patient_id
    WHERE {$where}
    ORDER BY ra.assessment_date DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$results = $stmt->fetchAll();

$pageTitle = 'My Assessments';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <h2 class="mb-0">My Assessments</h2>
            <p class="text-muted mb-0">Risk assessments you have submitted</p>
        </div>
        <a href="<?= e(url('dpwh/surveys_new.php')) ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> New Assessment
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="reviewed" <?= $statusFilter === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
                        <option value="flagged" <?= $statusFilter === 'flagged' ? 'selected' : '' ?>>Flagged</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                </div>
                <?php if ($statusFilter !== ''): ?>
                    <div class="col-md-2">
                        <a href="<?= e(url('dpwh/surveys_mine.php')) ?>" class="btn btn-outline-secondary w-100">Clear</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Patient</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$results): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No assessments found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($results as $r): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($r['patient_name']) ?></td>
                                    <td class="small text-muted"><?= e(format_date($r['assessment_date'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $r['status'] === 'pending' ? 'warning' : ($r['status'] === 'reviewed' ? 'success' : 'danger') ?>">
                                            <?= e(ucfirst($r['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= e(format_datetime($r['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-0 py-3">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= e((string)($page - 1)) ?><?= $statusFilter !== '' ? '&status=' . e($statusFilter) : '' ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= e((string)$i) ?><?= $statusFilter !== '' ? '&status=' . e($statusFilter) : '' ?>"><?= e((string)$i) ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= e((string)($page + 1)) ?><?= $statusFilter !== '' ? '&status=' . e($statusFilter) : '' ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
