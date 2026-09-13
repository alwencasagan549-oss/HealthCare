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

$patient = null;
$errors = [];
$success = '';

$patientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patientId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = :id AND district_id = :district_id LIMIT 1");
    $stmt->execute([':id' => $patientId, ':district_id' => $districtId]);
    $patient = $stmt->fetch();

    if (!$patient) {
        redirect(url('nurse/patients.php'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $middleName = trim((string)($_POST['middle_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $suffix = trim((string)($_POST['suffix'] ?? ''));
    $dateOfBirth = (string)($_POST['date_of_birth'] ?? '');
    $gender = (string)($_POST['gender'] ?? 'other');
    $address = trim((string)($_POST['address'] ?? ''));
    $contact = trim((string)($_POST['contact'] ?? ''));
    $bloodType = (string)($_POST['blood_type'] ?? 'unknown');
    $allergies = trim((string)($_POST['allergies'] ?? ''));
    $medicalHistory = trim((string)($_POST['medical_history'] ?? ''));
    $csrfToken = (string)($_POST['csrf_token'] ?? '');

    if (!verify_csrf($csrfToken)) {
        $errors[] = 'Invalid request. Please try again.';
    } elseif (empty($firstName) || empty($lastName)) {
        $errors[] = 'First name and last name are required.';
    } elseif (!in_array($gender, ['male','female','other'], true)) {
        $errors[] = 'Invalid gender.';
    } elseif (!in_array($bloodType, ['A+','A-','B+','B-','AB+','AB-','O+','O-','unknown'], true)) {
        $errors[] = 'Invalid blood type.';
    } else {
        if ($patientId > 0) {
            $oldValues = $patient ?: [];
            $stmt = $pdo->prepare("
                UPDATE patients
                SET first_name = :first_name, middle_name = :middle_name, last_name = :last_name,
                    suffix = :suffix, date_of_birth = :dob, gender = :gender, address = :address,
                    contact = :contact, blood_type = :blood_type, allergies = :allergies,
                    medical_history = :medical_history, updated_at = NOW()
                WHERE patient_id = :id AND district_id = :district_id
            ");
            $stmt->execute([
                ':first_name' => $firstName,
                ':middle_name' => $middleName ?: null,
                ':last_name' => $lastName,
                ':suffix' => $suffix ?: null,
                ':dob' => $dateOfBirth ?: null,
                ':gender' => $gender,
                ':address' => $address ?: null,
                ':contact' => $contact ?: null,
                ':blood_type' => $bloodType,
                ':allergies' => $allergies ?: null,
                ':medical_history' => $medicalHistory ?: null,
                ':id' => $patientId,
                ':district_id' => $districtId,
            ]);

            $audit->log('UPDATE', 'patients', (string)$patientId, $oldValues, [
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]);
            $success = 'Patient updated successfully.';
        } else {
            $patientCode = generate_patient_code($pdo, $districtId, '');
            $stmt = $pdo->prepare("
                INSERT INTO patients
                    (patient_code, district_id, first_name, middle_name, last_name, suffix, date_of_birth,
                     gender, address, contact, blood_type, allergies, medical_history, status, registered_by)
                VALUES
                    (:patient_code, :district_id, :first_name, :middle_name, :last_name, :suffix, :dob,
                     :gender, :address, :contact, :blood_type, :allergies, :medical_history, 'active', :registered_by)
            ");
            $stmt->execute([
                ':patient_code' => $patientCode,
                ':district_id' => $districtId,
                ':first_name' => $firstName,
                ':middle_name' => $middleName ?: null,
                ':last_name' => $lastName,
                ':suffix' => $suffix ?: null,
                ':dob' => $dateOfBirth ?: null,
                ':gender' => $gender,
                ':address' => $address ?: null,
                ':contact' => $contact ?: null,
                ':blood_type' => $bloodType,
                ':allergies' => $allergies ?: null,
                ':medical_history' => $medicalHistory ?: null,
                ':registered_by' => $_SESSION['user_id'] ?? null,
            ]);

            $newPatientId = (int)$pdo->lastInsertId();
            $audit->log('CREATE', 'patients', (string)$newPatientId, null, [
                'patient_code' => $patientCode,
                'name' => $firstName . ' ' . $lastName,
            ]);
            $success = 'Patient registered successfully.';
            $patientId = $newPatientId;
        }
    }
}

$pageTitle = $patientId > 0 ? 'Edit Patient' : 'Register Patient';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('nurse/dashboard.php')) ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= e(url('nurse/patients.php')) ?>">Patients</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($pageTitle) ?></li>
            </ol>
        </nav>
        <h2 class="mb-0"><?= e($pageTitle) ?></h2>
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

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form method="POST" action="" novalidate>
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="first_name" name="first_name" required value="<?= e($patient['first_name'] ?? old('first_name')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="middle_name" class="form-label">Middle Name</label>
                        <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?= e($patient['middle_name'] ?? old('middle_name')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="last_name" name="last_name" required value="<?= e($patient['last_name'] ?? old('last_name')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="suffix" class="form-label">Suffix</label>
                        <input type="text" class="form-control" id="suffix" name="suffix" value="<?= e($patient['suffix'] ?? old('suffix')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= e($patient['date_of_birth'] ?? old('date_of_birth')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="gender" class="form-label">Gender</label>
                        <select class="form-select" id="gender" name="gender">
                            <option value="male" <?= (isset($patient['gender']) && $patient['gender'] === 'male') || old('gender') === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= (isset($patient['gender']) && $patient['gender'] === 'female') || old('gender') === 'female' ? 'selected' : '' ?>>Female</option>
                            <option value="other" <?= (!isset($patient['gender']) || $patient['gender'] === 'other') && old('gender') !== 'male' && old('gender') !== 'female' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="blood_type" class="form-label">Blood Type</label>
                        <select class="form-select" id="blood_type" name="blood_type">
                            <?php foreach (['unknown','A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                                <option value="<?= e($bt) ?>" <?= ($patient['blood_type'] ?? 'unknown') === $bt ? 'selected' : '' ?>><?= e($bt === 'unknown' ? 'Unknown' : $bt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?= e($patient['address'] ?? old('address')) ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label for="contact" class="form-label">Contact</label>
                        <input type="text" class="form-control" id="contact" name="contact" value="<?= e($patient['contact'] ?? old('contact')) ?>">
                    </div>
                    <div class="col-12">
                        <label for="allergies" class="form-label">Allergies</label>
                        <textarea class="form-control" id="allergies" name="allergies" rows="2"><?= e($patient['allergies'] ?? old('allergies')) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label for="medical_history" class="form-label">Medical History</label>
                        <textarea class="form-control" id="medical_history" name="medical_history" rows="3"><?= e($patient['medical_history'] ?? old('medical_history')) ?></textarea>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Save Patient
                    </button>
                    <a href="<?= e(url('nurse/patients.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
