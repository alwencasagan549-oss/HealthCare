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

$errors = [];
$success = '';
$temporaryPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $contactNumber = trim((string)($_POST['contact_number'] ?? ''));
    $csrfToken = (string)($_POST['csrf_token'] ?? '');

    if (!verify_csrf($csrfToken)) {
        $errors[] = 'Invalid request. Please try again.';
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
                VALUES (:username, :password_hash, :email, :full_name, :contact_number, 'dpwh', :district_id, 'active', TRUE, :created_by)
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
                    'role' => 'dpwh',
                    'district_id' => $districtId,
                ]);

                $success = 'DPWH account created successfully. Please share the temporary password securely.';
                $temporaryPassword = $password;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'Username already exists.';
                } else {
                    $errors[] = 'Failed to create DPWH account: ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'Create DPWH Account';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('nurse/dashboard.php')) ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= e(url('nurse/dpwh_accounts.php')) ?>">DPWH Accounts</a></li>
                <li class="breadcrumb-item active" aria-current="page">Create Account</li>
            </ol>
        </nav>
        <h2 class="mb-0">Create DPWH Account</h2>
        <p class="text-muted mb-0">Create a new Data Processor / Health Worker account</p>
    </div>

    <?php if ($success && $temporaryPassword): ?>
        <div class="alert alert-success">
            <?= e($success) ?>
            <div class="mt-3 p-3 bg-white border rounded">
                <strong>Credentials (share securely):</strong><br>
                <strong>Username:</strong> <?= e($username) ?><br>
                <strong>Temporary Password:</strong> <code class="text-danger"><?= e($temporaryPassword) ?></code>
            </div>
        </div>
    <?php elseif ($success): ?>
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
                    <div class="col-md-6">
                        <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required value="<?= e(old('full_name')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" required value="<?= e(old('username')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label">Temporary Password <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="password" name="password" required placeholder="Generate a temporary password">
                        <div class="form-text">Minimum 8 characters with uppercase, lowercase, number, and special character.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e(old('email')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="contact_number" class="form-label">Contact Number</label>
                        <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?= e(old('contact_number')) ?>">
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-person-plus me-1"></i> Create DPWH Account
                    </button>
                    <a href="<?= e(url('nurse/dpwh_accounts.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
