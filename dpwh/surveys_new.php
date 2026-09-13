<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/header.php';

Middleware::dpwh();

$pdo = Database::getConnection();
$districtId = (int)($_SESSION['district_id'] ?? 0);
$dpwhId = (int)($_SESSION['user_id'] ?? 0);

$errors = [];
$success = '';

// Get surveys available to this DPWH's district
$stmtSurveys = $pdo->prepare("
    SELECT survey_id, survey_name, survey_type, description, questions
    FROM surveys
    WHERE is_active = TRUE AND (district_id IS NULL OR district_id = :district_id)
    ORDER BY survey_name
");
$stmtSurveys->execute([':district_id' => $districtId]);
$surveys = $stmtSurveys->fetchAll();

// Get patients in this district
$stmtPatients = $pdo->prepare("
    SELECT patient_id, patient_code, CONCAT(first_name, ' ', last_name) AS patient_name
    FROM patients
    WHERE district_id = :district_id AND status = 'active'
    ORDER BY last_name, first_name
");
$stmtPatients->execute([':district_id' => $districtId]);
$patients = $stmtPatients->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $surveyId = (int)($_POST['survey_id'] ?? 0);
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $answersJson = (string)($_POST['answers'] ?? '[]');
    $csrfToken = (string)($_POST['csrf_token'] ?? '');

    if (!verify_csrf($csrfToken)) {
        $errors[] = 'Invalid request. Please try again.';
    } elseif (!$surveyId) {
        $errors[] = 'Please select a survey.';
    } elseif (!$patientId) {
        $errors[] = 'Please select a patient.';
    } else {
        $answers = json_decode($answersJson, true);
        if (!is_array($answers) || empty($answers)) {
            $errors[] = 'Please answer at least one question.';
        } else {
            // Verify survey is available to this district
            $stmt = $pdo->prepare("
                SELECT survey_id FROM surveys
                WHERE survey_id = :survey_id AND is_active = TRUE AND (district_id IS NULL OR district_id = :district_id)
            ");
            $stmt->execute([':survey_id' => $surveyId, ':district_id' => $districtId]);
            if (!$stmt->fetch()) {
                $errors[] = 'Selected survey is not available for your district.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO survey_results (survey_id, patient_id, conducted_by, district_id, answers, status, created_at, updated_at)
                    VALUES (:survey_id, :patient_id, :conducted_by, :district_id, :answers, 'pending', NOW(), NOW())
                ");
                try {
                    $stmt->execute([
                        ':survey_id' => $surveyId,
                        ':patient_id' => $patientId,
                        ':conducted_by' => $dpwhId,
                        ':district_id' => $districtId,
                        ':answers' => json_encode($answers),
                    ]);

                    $newResultId = (int)$pdo->lastInsertId();
                    $audit = new AuditLogger($pdo);
                    $audit->log('CREATE', 'survey_results', (string)$newResultId, null, [
                        'survey_id' => $surveyId,
                        'patient_id' => $patientId,
                        'district_id' => $districtId,
                    ]);

                    $success = 'Survey submitted successfully.';
                } catch (PDOException $e) {
                    $errors[] = 'Failed to submit survey: ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'New Survey';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('dpwh/dashboard.php')) ?>">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">New Survey</li>
            </ol>
        </nav>
        <h2 class="mb-0">New Survey</h2>
        <p class="text-muted mb-0">Submit a new health survey for a patient</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= e($success) ?>
            <a href="<?= e(url('dpwh/surveys_new.php')) ?>" class="alert-link">Submit another?</a>
        </div>
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

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form id="surveyForm" method="POST" action="" novalidate>
                <?= csrf_field() ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="survey_id" class="form-label">Survey <span class="text-danger">*</span></label>
                        <select class="form-select" id="survey_id" name="survey_id" required>
                            <option value="">Select survey...</option>
                            <?php foreach ($surveys as $s): ?>
                                <option value="<?= e((string)$s['survey_id']) ?>">
                                    <?= e($s['survey_name']) ?> (<?= e($s['survey_type'] ?? 'General') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="patient_id" class="form-label">Patient <span class="text-danger">*</span></label>
                        <select class="form-select" id="patient_id" name="patient_id" required>
                            <option value="">Select patient...</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= e((string)$p['patient_id']) ?>">
                                    <?= e($p['patient_code'] . ' - ' . $p['patient_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="surveyQuestions" class="d-none">
                    <hr class="my-4">
                    <h5 class="mb-3">Survey Questions</h5>
                    <div id="questionsContainer"></div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Submit Survey
                    </button>
                    <a href="<?= e(url('dpwh/dashboard.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const surveyQuestions = {};
<?php foreach ($surveys as $s): ?>
surveyQuestions[<?= (int)$s['survey_id'] ?>] = <?= $s['questions'] ? json_encode($s['questions']) : '[]' ?>;
<?php endforeach; ?>

document.getElementById('survey_id').addEventListener('change', function() {
    const surveyId = this.value;
    const container = document.getElementById('questionsContainer');
    const wrapper = document.getElementById('surveyQuestions');

    if (!surveyId) {
        wrapper.classList.add('d-none');
        container.innerHTML = '';
        return;
    }

    const questions = surveyQuestions[surveyId] || [];
    if (!questions.length) {
        wrapper.classList.add('d-none');
        container.innerHTML = '<p class="text-muted">No questions in this survey.</p>';
        return;
    }

    wrapper.classList.remove('d-none');
    let html = '';
    questions.forEach((q, idx) => {
        html += '<div class="mb-3">';
        html += '<label class="form-label">' + (idx + 1) + '. ' + escapeHtml(q.question) + '</label>';
        if (q.type === 'text') {
            html += '<input type="text" class="form-control answer-input" data-question="' + escapeHtml(q.question) + '" value="">';
        } else if (q.type === 'textarea') {
            html += '<textarea class="form-control answer-input" data-question="' + escapeHtml(q.question) + '" rows="3"></textarea>';
        } else if (q.type === 'select') {
            html += '<select class="form-select answer-input" data-question="' + escapeHtml(q.question) + '">';
            html += '<option value="">Select...</option>';
            (q.options || []).forEach(opt => {
                html += '<option value="' + escapeHtml(opt) + '">' + escapeHtml(opt) + '</option>';
            });
            html += '</select>';
        } else if (q.type === 'radio') {
            (q.options || []).forEach(opt => {
                html += '<div class="form-check">';
                html += '<input class="form-check-input answer-input" type="radio" name="q_' + idx + '" data-question="' + escapeHtml(q.question) + '" value="' + escapeHtml(opt) + '" id="q_' + idx + '_' + escapeHtml(opt) + '">';
                html += '<label class="form-check-label" for="q_' + idx + '_' + escapeHtml(opt) + '">' + escapeHtml(opt) + '</label>';
                html += '</div>';
            });
        } else if (q.type === 'checkbox') {
            html += '<div>';
            (q.options || []).forEach(opt => {
                html += '<div class="form-check">';
                html += '<input class="form-check-input answer-input" type="checkbox" data-question="' + escapeHtml(q.question) + '" value="' + escapeHtml(opt) + '" id="q_' + idx + '_' + escapeHtml(opt) + '">';
                html += '<label class="form-check-label" for="q_' + idx + '_' + escapeHtml(opt) + '">' + escapeHtml(opt) + '</label>';
                html += '</div>';
            });
            html += '</div>';
        } else {
            html += '<input type="text" class="form-control answer-input" data-question="' + escapeHtml(q.question) + '" value="">';
        }
        html += '</div>';
    });
    container.innerHTML = html;
});

document.getElementById('surveyForm').addEventListener('submit', function(e) {
    const inputs = document.querySelectorAll('.answer-input');
    const answers = [];
    inputs.forEach(input => {
        const question = input.getAttribute('data-question');
        if (!question) return;
        let value = '';
        if (input.type === 'radio') {
            if (input.checked) value = input.value;
        } else if (input.type === 'checkbox') {
            if (input.checked) value = input.value;
        } else {
            value = input.value;
        }
        if (value) {
            answers.push({ question: question, answer: value });
        }
    });
    if (!answers.length) {
        e.preventDefault();
        alert('Please answer at least one question.');
        return;
    }
    let hidden = document.getElementById('answersHidden');
    if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.id = 'answersHidden';
        hidden.name = 'answers';
        this.appendChild(hidden);
    }
    hidden.value = JSON.stringify(answers);
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
