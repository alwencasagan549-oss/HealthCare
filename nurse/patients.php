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
$audit = new AuditLogger($pdo);
$districtId = (int)($_SESSION['district_id'] ?? 0);

$search = trim((string)($_GET['search'] ?? ''));
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$where = ['p.district_id = :district_id', "p.status = 'active'"];
$params = [':district_id' => $districtId];

if ($search !== '') {
    $where[] = '(p.first_name LIKE :search OR p.last_name LIKE :search OR p.patient_code LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

$whereSql = implode(' AND ', $where);

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM patients p WHERE {$whereSql}");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$pagination = paginate($total, $page, DEFAULT_PAGE_LIMIT);

$stmt = $pdo->prepare("
    SELECT p.patient_id, p.patient_code, p.first_name, p.middle_name, p.last_name, p.suffix,
           p.date_of_birth, p.gender, p.address, p.contact, p.blood_type, p.allergies, p.medical_history
    FROM patients p
    WHERE {$whereSql}
    ORDER BY p.last_name, p.first_name
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->execute();
$patients = $stmt->fetchAll();

$pageTitle = 'Patients';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <h2 class="mb-0">Patients</h2>
            <p class="text-muted mb-0">Manage patients in your district</p>
        </div>
        <a href="<?= e(url('nurse/patient_form.php')) ?>" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i> Add Patient
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" action="" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label for="search" class="form-label small">Search</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Name or patient code" value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i> Search
                    </button>
                </div>
                <?php if ($search): ?>
                    <div class="col-md-3">
                        <a href="<?= e(url('nurse/patients.php')) ?>" class="btn btn-outline-secondary w-100">Clear</a>
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
                            <th>Patient Code</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Blood Type</th>
                            <th>Contact</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$patients): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No patients found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($patients as $p): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border"><?= e($p['patient_code']) ?></span></td>
                                    <td>
                                        <div class="fw-semibold"><?= e(trim($p['first_name'] . ' ' . ($p['middle_name'] ?? '') . ' ' . $p['last_name'] . ' ' . ($p['suffix'] ?? ''))) ?></div>
                                        <div class="small text-muted"><?= e($p['address'] ?? '') ?></div>
                                    </td>
                                    <td><?= e(ucfirst($p['gender'] ?? 'other')) ?></td>
                                    <td><?= e($p['blood_type'] ?? 'unknown') ?></td>
                                    <td class="small"><?= e($p['contact'] ?? '') ?></td>
                                    <td class="text-end">
                                        <a href="<?= e(url('nurse/patient_form.php?id=' . $p['patient_id'])) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
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
                    <a class="page-link" href="?page=<?= $pagination['prev_page'] ?><?= $search ? '&search=' . urlencode($search) : '' ?>">Previous</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $pagination['has_next'] ? '' : 'disabled' ?>">
                    <a class="page-link" href="?page=<?= $pagination['next_page'] ?><?= $search ? '&search=' . urlencode($search) : '' ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
