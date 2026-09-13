<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/audit.php';

Middleware::admin();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$pdo = Database::getConnection();
$audit = new AuditLogger($pdo);

$errors = [];
$success = '';
$districtId = isset($_GET['district_id']) ? (int)$_GET['district_id'] : 0;

if ($districtId <= 0) {
    redirect(url('admin/districts.php'));
}

$stmt = $pdo->prepare("SELECT * FROM districts WHERE district_id = :id LIMIT 1");
$stmt->execute([':id' => $districtId]);
$district = $stmt->fetch();

if (!$district) {
    redirect(url('admin/districts.php'));
}

// Check if nurse already assigned
$existingNurse = $pdo->prepare("
    SELECT user_id FROM users
    WHERE district_id = :district_id AND role = 'nurse' AND status = 'active'
    LIMIT 1
");
$existingNurse->execute([':district_id' => $districtId]);
$existingNurseId = $existingNurse->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $contactNumber = trim((string)($_POST['contact_number'] ?? ''));
    $csrfToken = (string)($_POST['csrf_token'] ?? '');

    if (!verify_csrf($csrfToken)) {
        $errors[] = 'Invalid request. Please try again.';
    } elseif ($existingNurseId) {
        $errors[] = 'This district already has an active nurse assigned.';
    } elseif (empty($username) || empty($password) || empty($fullName)) {
        $errors[] = 'Username, password, and full name are required.';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    } else {
        $validationErrors = validate_password($password);
        if (!empty($validationErrors)) {
            $errors = array_merge($errors, $validationErrors);
        }

        if (empty($errors)) {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

            $stmt = $pdo->prepare("
                INSERT INTO users (username, password_hash, email, full_name, contact_number, role, district_id, status, force_password_change, created_by)
                VALUES (:username, :password_hash, :email, :full_name, :contact_number, 'nurse', :district_id, 'active', FALSE, :created_by)
            ");

            try {
                $stmt->execute([
                    ':username' => $username,
                    ':password_hash' => $passwordHash,
                    ':email' => $email ?: null,
                    ':full_name' => $fullName,
                    ':contact_number' => $contactNumber ?: null,
                    ':district_id' => $districtId,
                    ':created_by' => $_SESSION['user_id'] ?? null,
                ]);

                $newUserId = (int)$pdo->lastInsertId();
                $audit->log('CREATE', 'users', (string)$newUserId, null, [
                    'username' => $username,
                    'role' => 'nurse',
                    'district_id' => $districtId,
                ]);

                $success = 'Nurse account created successfully.';
                $existingNurseId = $newUserId;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'Username already exists.';
                } else {
                    $errors[] = 'Failed to create nurse account: ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'Assign Nurse';
?>

<div class="main-content">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('admin/dashboard.php')) ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= e(url('admin/districts.php')) ?>">Districts</a></li>
                <li class="breadcrumb-item"><a href="<?= e(url('admin/district_view.php?id=' . $districtId)) ?>"><?= e($district['district_name']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Assign Nurse</li>
            </ol>
        </nav>
        <h2 class="mb-0">Assign Nurse</h2>
        <p class="text-muted mb-0">Create a nurse account for <?= e($district['district_name']) ?></p>
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

    <?php if ($existingNurseId): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            This district already has an active nurse assigned. Only one nurse per district is allowed.
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="" novalidate>
                    <?= csrf_field() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required
                                   value="<?= e(old('full_name')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" required
                                   value="<?= e(old('username')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="password" name="password" required
                                   placeholder="Temporary password">
                            <div class="form-text">Minimum 8 characters with uppercase, lowercase, number, and special character.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= e(old('email')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="contact_number" class="form-label">Contact Number</label>
                            <input type="text" class="form-control" id="contact_number" name="contact_number"
                                   value="<?= e(old('contact_number')) ?>">
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-person-plus me-1"></i> Create Nurse Account
                        </button>
                        <a href="<?= e(url('admin/district_view.php?id=' . $districtId)) ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
