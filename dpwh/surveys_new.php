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

$pageTitle = 'New Risk Assessment';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$pdo = Database::getConnection();
$districtId = (int)($_SESSION['district_id'] ?? 0);
$dpwhId = (int)($_SESSION['user_id'] ?? 0);

$errors = [];
$success = '';

// Get patients in this district
$stmtPatients = $pdo->prepare("
    SELECT patient_id, patient_code,
           CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS patient_name,
           date_of_birth, gender
    FROM patients
    WHERE district_id = :district_id AND status = 'active'
    ORDER BY last_name, first_name
");
$stmtPatients->execute([':district_id' => $districtId]);
$patients = $stmtPatients->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $assessmentDate = (string)($_POST['assessment_date'] ?? '');
    $csrfToken = (string)($_POST['csrf_token'] ?? '');

    if (!verify_csrf($csrfToken)) {
        $errors[] = 'Invalid request. Please try again.';
    } elseif (!$patientId) {
        $errors[] = 'Please select a patient.';
    } elseif (empty($assessmentDate)) {
        $errors[] = 'Please enter the assessment date.';
    } else {
        // Verify patient belongs to district
        $stmt = $pdo->prepare("
            SELECT patient_id FROM patients
            WHERE patient_id = :patient_id AND district_id = :district_id AND status = 'active'
        ");
        $stmt->execute([':patient_id' => $patientId, ':district_id' => $districtId]);
        if (!$stmt->fetch()) {
            $errors[] = 'Selected patient is not available for your district.';
        } else {
            // Collect boolean fields
            $redFlags = [];
            $redFlagFields = [
                'red_flag_chest_pain', 'red_flag_difficulty_breathing', 'red_flag_loss_of_consciousness',
                'red_flag_slurred_speech', 'red_flag_facial_asymmetry', 'red_flag_weakness_numbness_one_side',
                'red_flag_disoriented', 'red_flag_chest_retractions', 'red_flag_seizure',
                'red_flag_self_harm', 'red_flag_agitated', 'red_flag_eye_injury', 'red_flag_severe_injuries'
            ];
            foreach ($redFlagFields as $field) {
                $redFlags[$field] = !empty($_POST[$field]) ? true : false;
            }

            $pmhFields = [];
            $pmhFieldNames = [
                'pmh_hypertension', 'pmh_heart_diseases', 'pmh_diabetes', 'pmh_cancer',
                'pmh_copd', 'pmh_asthma', 'pmh_allergies', 'pmh_mental_neuro_substance_disorders',
                'pmh_vision_problems', 'pmh_surgical_history', 'pmh_thyroid', 'pmh_kidney'
            ];
            foreach ($pmhFieldNames as $field) {
                $pmhFields[$field] = !empty($_POST[$field]) ? true : false;
            }

            $fhFields = [];
            $fhFieldNames = [
                'fh_hypertension', 'fh_stroke', 'fh_heart_disease', 'fh_diabetes',
                'fh_asthma', 'fh_cancer', 'fh_kidney_disease', 'fh_premature_cvd',
                'fh_tb_last_5_years', 'fh_mental_neuro_substance', 'fh_copd'
            ];
            foreach ($fhFieldNames as $field) {
                $fhFields[$field] = !empty($_POST[$field]) ? true : false;
            }

            $ncdTobaccoUse = !empty($_POST['ncd_tobacco_use']) ? true : false;
            $ncdAlcoholIntake = !empty($_POST['ncd_alcohol_intake']) ? true : false;
            $ncdPhysicalActivity = !empty($_POST['ncd_physical_activity']) ? true : false;
            $ncdNutrition = trim((string)($_POST['ncd_nutrition'] ?? ''));
            $ncdWeight = $_POST['ncd_weight'] !== '' ? (float)$_POST['ncd_weight'] : null;
            $ncdHeight = $_POST['ncd_height'] !== '' ? (float)$_POST['ncd_height'] : null;
            $ncdWaist = $_POST['ncd_waist_circumference'] !== '' ? (float)$_POST['ncd_waist_circumference'] : null;

            // Auto-calculate BMI
            $ncdBmi = null;
            if ($ncdWeight !== null && $ncdHeight !== null && $ncdHeight > 0) {
                $heightM = $ncdHeight / 100;
                $ncdBmi = round($ncdWeight / ($heightM * $heightM), 2);
            }

            $bpFirstSystolic = $_POST['bp_first_systolic'] !== '' ? (int)$_POST['bp_first_systolic'] : null;
            $bpFirstDiastolic = $_POST['bp_first_diastolic'] !== '' ? (int)$_POST['bp_first_diastolic'] : null;
            $bpSecondSystolic = $_POST['bp_second_systolic'] !== '' ? (int)$_POST['bp_second_systolic'] : null;
            $bpSecondDiastolic = $_POST['bp_second_diastolic'] !== '' ? (int)$_POST['bp_second_diastolic'] : null;

            $screeningBloodSugar = $_POST['screening_blood_sugar'] !== '' ? (float)$_POST['screening_blood_sugar'] : null;
            $screeningDmSymptoms = !empty($_POST['screening_dm_symptoms']) ? true : false;
            $screeningLipidProfile = trim((string)($_POST['screening_lipid_profile'] ?? ''));
            $screeningUrinalysis = trim((string)($_POST['screening_urinalysis'] ?? ''));
            $screeningChronicRespiratory = !empty($_POST['screening_chronic_respiratory_symptoms']) ? true : false;
            $screeningPefr = $_POST['screening_pefr'] !== '' ? (float)$_POST['screening_pefr'] : null;

            $managementLifestyle = trim((string)($_POST['management_lifestyle_modification'] ?? ''));
            $managementMedications = trim((string)($_POST['management_medications'] ?? ''));
            $followUpDate = !empty($_POST['follow_up_date']) ? (string)$_POST['follow_up_date'] : null;
            $remarks = trim((string)($_POST['remarks'] ?? ''));
            $assessorName = trim((string)($_POST['assessor_name'] ?? ''));
            $assessorSignature = trim((string)($_POST['assessor_signature'] ?? ''));

            $stmt = $pdo->prepare("
                INSERT INTO risk_assessments (
                    patient_id, district_id, conducted_by, assessment_date, status,
                    red_flag_chest_pain, red_flag_difficulty_breathing, red_flag_loss_of_consciousness,
                    red_flag_slurred_speech, red_flag_facial_asymmetry, red_flag_weakness_numbness_one_side,
                    red_flag_disoriented, red_flag_chest_retractions, red_flag_seizure,
                    red_flag_self_harm, red_flag_agitated, red_flag_eye_injury, red_flag_severe_injuries,
                    pmh_hypertension, pmh_heart_diseases, pmh_diabetes, pmh_cancer,
                    pmh_copd, pmh_asthma, pmh_allergies, pmh_mental_neuro_substance_disorders,
                    pmh_vision_problems, pmh_surgical_history, pmh_thyroid, pmh_kidney,
                    fh_hypertension, fh_stroke, fh_heart_disease, fh_diabetes,
                    fh_asthma, fh_cancer, fh_kidney_disease, fh_premature_cvd,
                    fh_tb_last_5_years, fh_mental_neuro_substance, fh_copd,
                    ncd_tobacco_use, ncd_alcohol_intake, ncd_physical_activity, ncd_nutrition,
                    ncd_weight, ncd_height, ncd_bmi, ncd_waist_circumference,
                    bp_first_systolic, bp_first_diastolic, bp_second_systolic, bp_second_diastolic,
                    screening_blood_sugar, screening_dm_symptoms, screening_lipid_profile,
                    screening_urinalysis, screening_chronic_respiratory_symptoms, screening_pefr,
                    management_lifestyle_modification, management_medications, follow_up_date,
                    remarks, assessor_name, assessor_signature
                ) VALUES (
                    :patient_id, :district_id, :conducted_by, :assessment_date, 'pending',
                    :red_flag_chest_pain, :red_flag_difficulty_breathing, :red_flag_loss_of_consciousness,
                    :red_flag_slurred_speech, :red_flag_facial_asymmetry, :red_flag_weakness_numbness_one_side,
                    :red_flag_disoriented, :red_flag_chest_retractions, :red_flag_seizure,
                    :red_flag_self_harm, :red_flag_agitated, :red_flag_eye_injury, :red_flag_severe_injuries,
                    :pmh_hypertension, :pmh_heart_diseases, :pmh_diabetes, :pmh_cancer,
                    :pmh_copd, :pmh_asthma, :pmh_allergies, :pmh_mental_neuro_substance_disorders,
                    :pmh_vision_problems, :pmh_surgical_history, :pmh_thyroid, :pmh_kidney,
                    :fh_hypertension, :fh_stroke, :fh_heart_disease, :fh_diabetes,
                    :fh_asthma, :fh_cancer, :fh_kidney_disease, :fh_premature_cvd,
                    :fh_tb_last_5_years, :fh_mental_neuro_substance, :fh_copd,
                    :ncd_tobacco_use, :ncd_alcohol_intake, :ncd_physical_activity, :ncd_nutrition,
                    :ncd_weight, :ncd_height, :ncd_bmi, :ncd_waist_circumference,
                    :bp_first_systolic, :bp_first_diastolic, :bp_second_systolic, :bp_second_diastolic,
                    :screening_blood_sugar, :screening_dm_symptoms, :screening_lipid_profile,
                    :screening_urinalysis, :screening_chronic_respiratory_symptoms, :screening_pefr,
                    :management_lifestyle_modification, :management_medications, :follow_up_date,
                    :remarks, :assessor_name, :assessor_signature
                )
            ");

            try {
                $stmt->execute(array_merge([
                    ':patient_id' => $patientId,
                    ':district_id' => $districtId,
                    ':conducted_by' => $dpwhId,
                    ':assessment_date' => $assessmentDate,
                ], $redFlags, $pmhFields, $fhFields, [
                    ':ncd_tobacco_use' => $ncdTobaccoUse,
                    ':ncd_alcohol_intake' => $ncdAlcoholIntake,
                    ':ncd_physical_activity' => $ncdPhysicalActivity,
                    ':ncd_nutrition' => $ncdNutrition ?: null,
                    ':ncd_weight' => $ncdWeight,
                    ':ncd_height' => $ncdHeight,
                    ':ncd_bmi' => $ncdBmi,
                    ':ncd_waist_circumference' => $ncdWaist,
                    ':bp_first_systolic' => $bpFirstSystolic,
                    ':bp_first_diastolic' => $bpFirstDiastolic,
                    ':bp_second_systolic' => $bpSecondSystolic,
                    ':bp_second_diastolic' => $bpSecondDiastolic,
                    ':screening_blood_sugar' => $screeningBloodSugar,
                    ':screening_dm_symptoms' => $screeningDmSymptoms,
                    ':screening_lipid_profile' => $screeningLipidProfile ?: null,
                    ':screening_urinalysis' => $screeningUrinalysis ?: null,
                    ':screening_chronic_respiratory_symptoms' => $screeningChronicRespiratory,
                    ':screening_pefr' => $screeningPefr,
                    ':management_lifestyle_modification' => $managementLifestyle ?: null,
                    ':management_medications' => $managementMedications ?: null,
                    ':follow_up_date' => $followUpDate,
                    ':remarks' => $remarks ?: null,
                    ':assessor_name' => $assessorName ?: null,
                    ':assessor_signature' => $assessorSignature ?: null,
                ]));

                $newId = (int)$pdo->lastInsertId();
                $audit = new AuditLogger($pdo);
                $audit->log('CREATE', 'risk_assessments', (string)$newId, null, [
                    'patient_id' => $patientId,
                    'district_id' => $districtId,
                    'assessment_date' => $assessmentDate,
                ]);

                $success = 'Risk assessment submitted successfully.';
            } catch (PDOException $e) {
                $errors[] = 'Failed to submit assessment: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'New Risk Assessment';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('dpwh/dashboard.php')) ?>">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">New Risk Assessment</li>
            </ol>
        </nav>
        <h2 class="mb-0">PhilPen Risk Assessment</h2>
        <p class="text-muted mb-0">Complete all applicable sections for the selected patient</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= e($success) ?>
            <a href="<?= e(url('dpwh/surveys_new.php')) ?>" class="alert-link">Submit another assessment</a>
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

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form id="assessmentForm" method="POST" action="" novalidate>
                <?= csrf_field() ?>

                <div class="row g-3 mb-4">
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
                    <div class="col-md-3">
                        <label for="assessment_date" class="form-label">Assessment Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="assessment_date" name="assessment_date"
                               value="<?= e(date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="assessor_name" class="form-label">Assessor Name</label>
                        <input type="text" class="form-control" id="assessor_name" name="assessor_name"
                               value="<?= e($_SESSION['full_name'] ?? '') ?>">
                    </div>
                </div>

                <hr class="my-4">

                <!-- Section I: Patient Information -->
                <div class="accordion" id="assessmentAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="sectionIHeading">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#sectionICollapse" aria-expanded="true" aria-controls="sectionICollapse">
                                <i class="bi bi-person-lines-fill me-2"></i>
                                <strong>Section I. Patient Information</strong>
                            </button>
                        </h2>
                        <div id="sectionICollapse" class="accordion-collapse collapse show"
                             aria-labelledby="sectionIHeading" data-bs-parent="#assessmentAccordion">
                            <div class="accordion-body">
                                <p class="text-muted small">Core patient identification and demographics.</p>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" disabled
                                               value="Selected from patient record">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Age</label>
                                        <input type="text" class="form-control" disabled
                                               value="Auto from birthdate">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Sex</label>
                                        <input type="text" class="form-control" disabled
                                               value="Auto from patient record">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Address</label>
                                        <input type="text" class="form-control" disabled
                                               value="Auto from patient record">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">PHIC No.</label>
                                        <input type="text" class="form-control" disabled
                                               value="From patient demographics">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Civil Status</label>
                                        <input type="text" class="form-control" disabled
                                               value="From patient demographics">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Religion</label>
                                        <input type="text" class="form-control" disabled
                                               value="From patient demographics">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Ethnicity</label>
                                        <input type="text" class="form-control" disabled
                                               value="From patient demographics">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">PWD ID Card No.</label>
                                        <input type="text" class="form-control" disabled
                                               value="From patient demographics">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">IP / Non-IP</label>
                                        <input type="text" class="form-control" disabled
                                               value="From patient demographics">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Employment Status</label>
                                        <input type="text" class="form-control" disabled
                                               value="From patient demographics">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Contact No.</label>
                                        <input type="text" class="form-control" disabled
                                               value="From patient record">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section II: Assess For Red Flags -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="sectionIIHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#sectionIICollapse" aria-expanded="false" aria-controls="sectionIICollapse">
                                <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>
                                <strong>Section II. Assess For Red Flags</strong>
                            </button>
                        </h2>
                        <div id="sectionIICollapse" class="accordion-collapse collapse"
                             aria-labelledby="sectionIIHeading" data-bs-parent="#assessmentAccordion">
                            <div class="accordion-body">
                                <p class="text-muted small">Check all red flags present.</p>
                                <div class="row g-2">
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
                                    foreach ($redFlags as $name => $label):
                                    ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="<?= e($name) ?>" id="<?= e($name) ?>">
                                            <label class="form-check-label" for="<?= e($name) ?>">
                                                <?= e($label) ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section III: Past Medical History -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="sectionIIIHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#sectionIIICollapse" aria-expanded="false" aria-controls="sectionIIICollapse">
                                <i class="bi bi-journal-medical me-2"></i>
                                <strong>Section III. Past Medical History</strong>
                            </button>
                        </h2>
                        <div id="sectionIIICollapse" class="accordion-collapse collapse"
                             aria-labelledby="sectionIIIHeading" data-bs-parent="#assessmentAccordion">
                            <div class="accordion-body">
                                <p class="text-muted small">Check all applicable past medical conditions.</p>
                                <div class="row g-2">
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
                                    foreach ($pmh as $name => $label):
                                    ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="<?= e($name) ?>" id="<?= e($name) ?>">
                                            <label class="form-check-label" for="<?= e($name) ?>">
                                                <?= e($label) ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section IV: Family History -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="sectionIVHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#sectionIVCollapse" aria-expanded="false" aria-controls="sectionIVCollapse">
                                <i class="bi bi-people me-2"></i>
                                <strong>Section IV. Family History</strong>
                            </button>
                        </h2>
                        <div id="sectionIVCollapse" class="accordion-collapse collapse"
                             aria-labelledby="sectionIVHeading" data-bs-parent="#assessmentAccordion">
                            <div class="accordion-body">
                                <p class="text-muted small">Check all conditions present in the patient's family.</p>
                                <div class="row g-2">
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
                                    foreach ($fh as $name => $label):
                                    ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="<?= e($name) ?>" id="<?= e($name) ?>">
                                            <label class="form-check-label" for="<?= e($name) ?>">
                                                <?= e($label) ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section V: NCD Risk Factors -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="sectionVHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#sectionVCollapse" aria-expanded="false" aria-controls="sectionVCollapse">
                                <i class="bi bi-activity me-2"></i>
                                <strong>Section V. NCD Risk Factors</strong>
                            </button>
                        </h2>
                        <div id="sectionVCollapse" class="accordion-collapse collapse"
                             aria-labelledby="sectionVHeading" data-bs-parent="#assessmentAccordion">
                            <div class="accordion-body">
                                <p class="text-muted small">Document lifestyle and physiological risk indicators.</p>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="ncd_tobacco_use" id="ncd_tobacco_use">
                                            <label class="form-check-label" for="ncd_tobacco_use">Tobacco use</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="ncd_alcohol_intake" id="ncd_alcohol_intake">
                                            <label class="form-check-label" for="ncd_alcohol_intake">Alcohol intake</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="ncd_physical_activity" id="ncd_physical_activity">
                                            <label class="form-check-label" for="ncd_physical_activity">Physical activity</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="ncd_nutrition" class="form-label">Nutrition</label>
                                        <textarea class="form-control" id="ncd_nutrition" name="ncd_nutrition" rows="3"
                                                  placeholder="Dietary notes..."></textarea>
                                    </div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <label for="ncd_weight" class="form-label">Weight (kg)</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="ncd_weight" name="ncd_weight">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="ncd_height" class="form-label">Height (cm)</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="ncd_height" name="ncd_height">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="ncd_bmi" class="form-label">BMI</label>
                                        <input type="text" class="form-control" id="ncd_bmi" readonly>
                                        <div class="form-text">Auto-calculated from weight and height</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="ncd_waist_circumference" class="form-label">Waist Circumference (cm)</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="ncd_waist_circumference" name="ncd_waist_circumference">
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label for="bp_first_systolic" class="form-label">1st BP Systolic</label>
                                        <input type="number" min="0" class="form-control" id="bp_first_systolic" name="bp_first_systolic">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="bp_first_diastolic" class="form-label">1st BP Diastolic</label>
                                        <input type="number" min="0" class="form-control" id="bp_first_diastolic" name="bp_first_diastolic">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="bp_second_systolic" class="form-label">2nd BP Systolic</label>
                                        <input type="number" min="0" class="form-control" id="bp_second_systolic" name="bp_second_systolic">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="bp_second_diastolic" class="form-label">2nd BP Diastolic</label>
                                        <input type="number" min="0" class="form-control" id="bp_second_diastolic" name="bp_second_diastolic">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section VI: Risk Screening -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="sectionVIHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#sectionVICollapse" aria-expanded="false" aria-controls="sectionVICollapse">
                                <i class="bi bi-clipboard2-pulse me-2"></i>
                                <strong>Section VI. Risk Screening</strong>
                            </button>
                        </h2>
                        <div id="sectionVICollapse" class="accordion-collapse collapse"
                             aria-labelledby="sectionVIHeading" data-bs-parent="#assessmentAccordion">
                            <div class="accordion-body">
                                <p class="text-muted small">Record screening results and respiratory findings.</p>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <label for="screening_blood_sugar" class="form-label">Blood Sugar (mg/dL)</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="screening_blood_sugar" name="screening_blood_sugar">
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" name="screening_dm_symptoms" id="screening_dm_symptoms">
                                            <label class="form-check-label" for="screening_dm_symptoms">DM Symptoms Present</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="screening_lipid_profile" class="form-label">Lipid Profile</label>
                                        <textarea class="form-control" id="screening_lipid_profile" name="screening_lipid_profile" rows="3"
                                                  placeholder="Cholesterol, triglycerides, HDL, LDL..."></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="screening_urinalysis" class="form-label">Urinalysis</label>
                                        <textarea class="form-control" id="screening_urinalysis" name="screening_urinalysis" rows="3"
                                                  placeholder="Color, transparency, sugar, protein..."></textarea>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="screening_chronic_respiratory_symptoms" id="screening_chronic_respiratory_symptoms">
                                            <label class="form-check-label" for="screening_chronic_respiratory_symptoms">Chronic respiratory symptoms</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="screening_pefr" class="form-label">PEFR (L/min)</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="screening_pefr" name="screening_pefr">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section VII: Management -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="sectionVIIHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#sectionVIICollapse" aria-expanded="false" aria-controls="sectionVIICollapse">
                                <i class="bi bi-clipboard-check me-2"></i>
                                <strong>Section VII. Management</strong>
                            </button>
                        </h2>
                        <div id="sectionVIICollapse" class="accordion-collapse collapse"
                             aria-labelledby="sectionVIIHeading" data-bs-parent="#assessmentAccordion">
                            <div class="accordion-body">
                                <p class="text-muted small">Document management plan and follow-up instructions.</p>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="management_lifestyle_modification" class="form-label">Lifestyle Modification</label>
                                        <textarea class="form-control" id="management_lifestyle_modification" name="management_lifestyle_modification" rows="3"
                                                  placeholder="Diet, exercise, smoking/alcohol cessation..."></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="management_medications" class="form-label">Medications</label>
                                        <textarea class="form-control" id="management_medications" name="management_medications" rows="3"
                                                  placeholder="Prescribed medications, dosages..."></textarea>
                                    </div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label for="follow_up_date" class="form-label">Follow-up Date</label>
                                        <input type="date" class="form-control" id="follow_up_date" name="follow_up_date">
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label for="remarks" class="form-label">Remarks</label>
                                        <textarea class="form-control" id="remarks" name="remarks" rows="3"
                                                  placeholder="Additional notes..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Submit Assessment
                    </button>
                    <a href="<?= e(url('dpwh/dashboard.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const weightInput = document.getElementById('ncd_weight');
    const heightInput = document.getElementById('ncd_height');
    const bmiInput = document.getElementById('ncd_bmi');

    function calculateBmi() {
        const weight = parseFloat(weightInput.value);
        const height = parseFloat(heightInput.value);
        if (weight > 0 && height > 0) {
            const heightM = height / 100;
            const bmi = weight / (heightM * heightM);
            bmiInput.value = bmi.toFixed(2);
        } else {
            bmiInput.value = '';
        }
    }

    if (weightInput && heightInput) {
        weightInput.addEventListener('input', calculateBmi);
        heightInput.addEventListener('input', calculateBmi);
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
