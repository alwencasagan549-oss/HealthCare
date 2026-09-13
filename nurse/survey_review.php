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
$nurseId = (int)($_SESSION['user_id'] ?? 0);

$assessmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT ra.*,
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
           CONCAT(u.full_name, ' (', u.username, ')') AS conducted_by_name
    FROM risk_assessments ra
    JOIN patients p ON p.patient_id = ra.patient_id
    JOIN users u ON u.user_id = ra.conducted_by
    WHERE ra.assessment_id = :assessment_id AND ra.district_id = :district_id
    LIMIT 1
");
$stmt->execute([':assessment_id' => $assessmentId, ':district_id' => $districtId]);
$assessment = $stmt->fetch();

if (!$assessment) {
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
        $oldValues = ['status' => $assessment['status'], 'remarks' => $assessment['remarks']];

        $stmt = $pdo->prepare("
            UPDATE risk_assessments
            SET status = :status, remarks = :remarks, reviewed_by = :reviewed_by, reviewed_at = NOW()
            WHERE assessment_id = :assessment_id
        ");
        $stmt->execute([
            ':status' => $newStatus,
            ':remarks' => $remarks ?: null,
            ':reviewed_by' => $nurseId,
            ':assessment_id' => $assessmentId,
        ]);

        $audit->log('UPDATE', 'risk_assessments', (string)$assessmentId, $oldValues, [
            'status' => $newStatus,
            'remarks' => $remarks,
            'reviewed_by' => $nurseId,
        ]);

        $success = 'Assessment ' . $newStatus . ' successfully.';

        // Refresh assessment
        $stmt->execute([':assessment_id' => $assessmentId, ':district_id' => $districtId]);
        $assessment = $stmt->fetch();
    }
}

$pageTitle = 'Risk Assessment Review';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('nurse/dashboard.php')) ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= e(url('nurse/survey_results.php')) ?>">Risk Assessments</a></li>
                <li class="breadcrumb-item active" aria-current="page">Review</li>
            </ol>
        </nav>
        <h2 class="mb-0">Risk Assessment Review</h2>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= e($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Patient</div>
                    <div class="fw-semibold"><?= e($assessment['patient_name']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Assessment Date</div>
                    <div class="fw-semibold"><?= e(format_date($assessment['assessment_date'])) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Conducted By</div>
                    <div class="fw-semibold"><?= e($assessment['conducted_by_name']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Status</div>
                    <div>
                        <span class="badge bg-<?= $assessment['status'] === 'pending' ? 'warning' : ($assessment['status'] === 'reviewed' ? 'success' : 'danger') ?>">
                            <?= e(ucfirst($assessment['status'])) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0">PhilPen Risk Assessment</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <tbody>
                        <?php
                        $redFlags = [
                            'red_flag_chest_pain' => 'Chest pain',
                            'red_flag_difficulty_breathing' => 'Difficulty in breathing',
                            'red_flag_loss_of_consciousness' => 'Loss of consciousness',
                            'red_flag_slurred_speech' => 'Slurred speech',
                            'red_flag_facial_asymmetry' => 'Facial asymmetry',
                            'red_flag_weakness_numbness_one_side' => 'Weakness/numbness on one side',
                            'red_flag_disoriented' => 'Disoriented',
                            'red_flag_chest_retractions' => 'Chest retractions',
                            'red_flag_seizure' => 'Seizure',
                            'red_flag_self_harm' => 'Self-harm',
                            'red_flag_agitated' => 'Agitated',
                            'red_flag_eye_injury' => 'Eye injury',
                            'red_flag_severe_injuries' => 'Severe injuries',
                        ];
                        foreach ($redFlags as $field => $label):
                        ?>
                            <tr class="border-bottom">
                                <td class="fw-semibold" style="width: 40%;"><?= e($label) ?></td>
                                <td><?= $assessment[$field] ? '<span class="text-danger fw-semibold">Yes</span>' : 'No' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0">Past Medical History</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <tbody>
                        <?php
                        $pmh = [
                            'pmh_hypertension' => 'Hypertension',
                            'pmh_heart_diseases' => 'Heart diseases',
                            'pmh_diabetes' => 'Diabetes',
                            'pmh_cancer' => 'Cancer',
                            'pmh_copd' => 'COPD',
                            'pmh_asthma' => 'Asthma',
                            'pmh_allergies' => 'Allergies',
                            'pmh_mental_neuro_substance_disorders' => 'Mental/Neuro/Substance disorders',
                            'pmh_vision_problems' => 'Vision problems',
                            'pmh_surgical_history' => 'Surgical history',
                            'pmh_thyroid' => 'Thyroid disease',
                            'pmh_kidney' => 'Kidney disease',
                        ];
                        foreach ($pmh as $field => $label):
                        ?>
                            <tr class="border-bottom">
                                <td class="fw-semibold" style="width: 40%;"><?= e($label) ?></td>
                                <td><?= $assessment[$field] ? 'Yes' : 'No' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0">Family History</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <tbody>
                        <?php
                        $fh = [
                            'fh_hypertension' => 'Hypertension',
                            'fh_stroke' => 'Stroke',
                            'fh_heart_disease' => 'Heart disease',
                            'fh_diabetes' => 'Diabetes',
                            'fh_asthma' => 'Asthma',
                            'fh_cancer' => 'Cancer',
                            'fh_kidney_disease' => 'Kidney disease',
                            'fh_premature_cvd' => 'Premature CVD',
                            'fh_tb_last_5_years' => 'TB (last 5 years)',
                            'fh_mental_neuro_substance' => 'Mental/Neuro/Substance disorders',
                            'fh_copd' => 'COPD',
                        ];
                        foreach ($fh as $field => $label):
                        ?>
                            <tr class="border-bottom">
                                <td class="fw-semibold" style="width: 40%;"><?= e($label) ?></td>
                                <td><?= $assessment[$field] ? 'Yes' : 'No' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0">NCD Risk Factors</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <tbody>
                        <tr class="border-bottom">
                            <td class="fw-semibold" style="width: 40%;">Tobacco use</td>
                            <td><?= $assessment['ncd_tobacco_use'] ? 'Yes' : 'No' ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="fw-semibold">Alcohol intake</td>
                            <td><?= $assessment['ncd_alcohol_intake'] ? 'Yes' : 'No' ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="fw-semibold">Physical activity</td>
                            <td><?= $assessment['ncd_physical_activity'] ? 'Yes' : 'No' ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="fw-semibold">Nutrition</td>
                            <td><?= $assessment['ncd_nutrition'] ? e($assessment['ncd_nutrition']) : '<span class="text-muted">Not recorded</span>' ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="fw-semibold">Weight</td>
                            <td><?= $assessment['ncd_weight'] !== null ? e((string)$assessment['ncd_weight']) . ' kg' : '<span class="text-muted">Not recorded</span>' ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="fw-semibold">Height</td>
                            <td><?= $assessment['ncd_height'] !== null ? e((string)$assessment['ncd_height']) . ' cm' : '<span class="text-muted">Not recorded</span>' ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="fw-semibold">BMI</td>
                            <td><?= $assessment['ncd_bmi'] !== null ? e((string)$assessment['ncd_bmi']) : '<span class="text-muted">Not recorded</span>' ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="fw-semibold">Waist Circumference</td>
                            <td><?= $assessment['ncd_waist_circumference'] !== null ? e((string)$assessment['ncd_waist_circumference']) . ' cm' : '<span class="text-muted">Not recorded</span>' ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="fw-semibold">1st BP</td>
                            <td>
                                <?php if ($assessment['bp_first_systolic'] !== null && $assessment['bp_first_diastolic'] !== null): ?>
                                    <?= e((string)$assessment['bp_first_systolic']) ?>/<?= e((string)$assessment['bp_first_diastolic']) ?> mmHg
                                <?php else: ?>
                                    <span class="text-muted">Not recorded</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">2nd BP</td>
                            <td>
                                <?php if ($assessment['bp_second_systolic'] !== null && $assessment['bp_second_diastolic'] !== null): ?>
                                    <?= e((string)$assessment['bp_second_systolic']) ?>/<?= e((string)$assessment['bp_second_diastolic']) ?> mmHg
                                <?php else: ?>
                                    <span class="text-muted">Not recorded</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0">Risk Screening</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <tbody>
                        <tr class="border-bottom">
                            <td class="fw-semibold" style="width: 40%;">Blood Sugar</td>
                            <td><?= $assessment['screening_blood_sugar'] !== null ? e((string)$assessment['screening_blood_sugar']) . ' mg/dL' : '<span class="text-muted">Not recorded</span>' ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="fw-semibold">DM Symptoms</td>
                            <td><?= $assessment['screening_dm_symptoms'] ? 'Yes' : 'No' ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="fw-semibold">Lipid Profile</td>
                            <td><?= $assessment['screening_lipid_profile'] ? e($assessment['screening_lipid_profile']) : '<span class="text-muted">Not recorded</span>' ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="fw-semibold">Urinalysis</td>
                            <td><?= $assessment['screening_urinalysis'] ? e($assessment['screening_urinalysis']) : '<span class="text-muted">Not recorded</span>' ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="fw-semibold">Chronic Respiratory Symptoms</td>
                            <td><?= $assessment['screening_chronic_respiratory_symptoms'] ? 'Yes' : 'No' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">PEFR</td>
                            <td><?= $assessment['screening_pefr'] !== null ? e((string)$assessment['screening_pefr']) . ' L/min' : '<span class="text-muted">Not recorded</span>' ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0">Management</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <tbody>
                        <tr class="border-bottom">
                            <td class="fw-semibold" style="width: 40%;">Lifestyle Modification</td>
                            <td><?= $assessment['management_lifestyle_modification'] ? e($assessment['management_lifestyle_modification']) : '<span class="text-muted">Not recorded</span>' ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="fw-semibold">Medications</td>
                            <td><?= $assessment['management_medications'] ? e($assessment['management_medications']) : '<span class="text-muted">Not recorded</span>' ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="fw-semibold">Follow-up Date</td>
                            <td><?= $assessment['follow_up_date'] ? e(format_date($assessment['follow_up_date'])) : '<span class="text-muted">Not recorded</span>' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Remarks</td>
                            <td><?= $assessment['remarks'] ? e($assessment['remarks']) : '<span class="text-muted">Not recorded</span>' ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($assessment['status'] === 'pending'): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="mb-0">Review Action</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="Add your review remarks..."><?= e($assessment['remarks'] ?? '') ?></textarea>
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
