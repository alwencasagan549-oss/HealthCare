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
$audit = new AuditLogger($pdo);
$districtId = (int)($_SESSION['district_id'] ?? 0);
$nurseId = (int)($_SESSION['user_id'] ?? 0);

$resultId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT sr.*, s.survey_name, s.description,
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
           CONCAT(u.full_name, ' (', u.username, ')') AS conducted_by_name
    FROM survey_results sr
    JOIN surveys s ON s.survey_id = sr.survey_id
    JOIN patients p ON p.patient_id = sr.patient_id
    JOIN users u ON u.user_id = sr.conducted_by
    WHERE sr.result_id = :result_id AND sr.district_id = :district_id
    LIMIT 1
");
$stmt->execute([':result_id' => $resultId, ':district_id' => $districtId]);
$result = $stmt->fetch();

if (!$result) {
    redirect(url('nurse/survey_results.php'));
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $remarks = trim((string)($_POST['remarks'] ?? ''));
    $csrfToken = (string)($_POST['csrf_token'] ?? '');

    if (!verify_csrf($csrfToken)) {
        $errors[] = 'Invalid request. Please try again.';
    } elseif (!in_array($action, ['review','flag'], true)) {
        $errors[] = 'Invalid action.';
    } else {
        $newStatus = $action === 'review' ? 'reviewed' : 'flagged';
        $oldValues = ['status' => $result['status'], 'remarks' => $result['remarks']];

        $stmt = $pdo->prepare("
            UPDATE survey_results
            SET status = :status, remarks = :remarks, reviewed_by = :reviewed_by, reviewed_at = NOW()
            WHERE result_id = :result_id
        ");
        $stmt->execute([
            ':status' => $newStatus,
            ':remarks' => $remarks ?: null,
            ':reviewed_by' => $nurseId,
            ':result_id' => $resultId,
        ]);

        $audit->log('UPDATE', 'survey_results', (string)$resultId, $oldValues, [
            'status' => $newStatus,
            'remarks' => $remarks,
            'reviewed_by' => $nurseId,
        ]);

        $success = 'Survey ' . $newStatus . ' successfully.';

        // Refresh result
        $stmt->execute([':result_id' => $resultId, ':district_id' => $districtId]);
        $result = $stmt->fetch();
    }
}

$answers = json_decode((string)$result['answers'], true) ?: [];

$pageTitle = 'Survey Review';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('nurse/dashboard.php')) ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= e(url('nurse/survey_results.php')) ?>">Survey Results</a></li>
                <li class="breadcrumb-item active" aria-current="page">Review</li>
            </ol>
        </nav>
        <h2 class="mb-0">Survey Review</h2>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Patient</div>
                    <div class="fw-semibold"><?= e($result['patient_name']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Survey</div>
                    <div class="fw-semibold"><?= e($result['survey_name']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Conducted By</div>
                    <div class="fw-semibold"><?= e($result['conducted_by_name']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Status</div>
                    <div>
                        <span class="badge bg-<?= $result['status'] === 'pending' ? 'warning' : ($result['status'] === 'reviewed' ? 'success' : 'danger') ?>">
                            <?= e(ucfirst($result['status'])) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0">Survey Answers</h5>
        </div>
        <div class="card-body">
            <?php if (!$answers): ?>
                <p class="text-muted mb-0">No answers recorded.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <tbody>
                            <?php foreach ($answers as $question => $answer): ?>
                                <tr class="border-bottom">
                                    <td class="fw-semibold" style="width: 40%;"><?= e($question) ?></td>
                                    <td><?= e((string)$answer) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($result['status'] === 'pending'): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="mb-0">Review Action</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="Add your review remarks..."><?= e($result['remarks'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="action" value="review" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i> Mark as Reviewed
                        </button>
                        <button type="submit" name="action" value="flag" class="btn btn-danger">
                            <i class="bi bi-flag me-1"></i> Flag for Follow-up
                        </button>
                        <a href="<?= e(url('nurse/survey_results.php')) ?>" class="btn btn-outline-secondary">Back</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
