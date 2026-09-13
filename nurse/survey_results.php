<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/header.php';

Middleware::nurse();

$pdo = Database::getConnection();
$districtId = (int)($_SESSION['district_id'] ?? 0);

$status = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$where = ['sr.district_id = :district_id'];
$params = [':district_id' => $districtId];

if ($status !== '' && in_array($status, ['pending','reviewed','flagged'], true)) {
    $where[] = 'sr.status = :status';
    $params[':status'] = $status;
}

$whereSql = implode(' AND ', $where);

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM survey_results sr WHERE {$whereSql}");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$pagination = paginate($total, $page, DEFAULT_PAGE_LIMIT);

$stmt = $pdo->prepare("
    SELECT sr.result_id, sr.status, sr.remarks, sr.created_at,
           s.survey_name,
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
           CONCAT(u.full_name, ' (', u.username, ')') AS conducted_by
    FROM survey_results sr
    JOIN surveys s ON s.survey_id = sr.survey_id
    JOIN patients p ON p.patient_id = sr.patient_id
    JOIN users u ON u.user_id = sr.conducted_by
    WHERE {$whereSql}
    ORDER BY sr.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->execute();
$results = $stmt->fetchAll();

$pageTitle = 'Survey Results';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <h2 class="mb-0">Survey Results</h2>
            <p class="text-muted mb-0">All survey results for your district</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" action="" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="status" class="form-label small">Status</label>
                    <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="reviewed" <?= $status === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
                        <option value="flagged" <?= $status === 'flagged' ? 'selected' : '' ?>>Flagged</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <a href="<?= e(url('nurse/survey_results.php')) ?>" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Survey</th>
                            <th>Patient</th>
                            <th>Conducted By</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$results): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No survey results found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($results as $r): ?>
                                <tr>
                                    <td><?= e($r['survey_name']) ?></td>
                                    <td><?= e($r['patient_name']) ?></td>
                                    <td class="small"><?= e($r['conducted_by']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $r['status'] === 'pending' ? 'warning' : ($r['status'] === 'reviewed' ? 'success' : 'danger') ?>">
                                            <?= e(ucfirst($r['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= e(format_datetime($r['created_at'])) ?></td>
                                    <td class="text-end">
                                        <a href="<?= e(url('nurse/survey_review.php?id=' . $r['result_id'])) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i> Review
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
        <nav class="mt-3" aria-label="Page navigation">
            <ul class="pagination">
                <li class="page-item <?= $pagination['has_prev'] ? '' : 'disabled' ?>">
                    <a class="page-link" href="?page=<?= $pagination['prev_page'] ?><?= $status ? '&status=' . urlencode($status) : '' ?>">Previous</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?><?= $status ? '&status=' . urlencode($status) : '' ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $pagination['has_next'] ? '' : 'disabled' ?>">
                    <a class="page-link" href="?page=<?= $pagination['next_page'] ?><?= $status ? '&status=' . urlencode($status) : '' ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
