<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/audit.php';

Middleware::admin();

$pdo = Database::getConnection();
$audit = new AuditLogger($pdo);

$district = null;
$errors = [];
$success = '';
$districtQueryError = null;

$districtId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// --------------------------------------------------------
// Handle POST before any output so redirect() can send headers
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['district_name'] ?? ''));
    $code = strtoupper(trim((string)($_POST['district_code'] ?? '')));
    $address = trim((string)($_POST['address'] ?? ''));
    $contactNumber = trim((string)($_POST['contact_number'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $status = (string)($_POST['status'] ?? 'active');
    $csrfToken = (string)($_POST['csrf_token'] ?? '');

    error_log('district_form POST: districtId=' . $districtId . ', name=' . $name . ', code=' . $code . ', status=' . $status);

    if (!verify_csrf($csrfToken)) {
        $errors[] = 'Invalid request. Please try again.';
    } elseif (empty($name) || empty($code)) {
        $errors[] = 'District name and code are required.';
    } elseif (!in_array($status, ['active','inactive'], true)) {
        $errors[] = 'Invalid status.';
    } else {
        if ($districtId > 0) {
            // Load existing district for audit oldValues before update
            try {
                $stmt = $pdo->prepare("SELECT * FROM districts WHERE district_id = :id LIMIT 1");
                $stmt->execute([':id' => $districtId]);
                $district = $stmt->fetch();
            } catch (Throwable $e) {
                error_log('District preload error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }

            $oldValues = $district ?: [];
            try {
                $stmt = $pdo->prepare("
                    UPDATE districts
                    SET district_name = :name, district_code = :code, address = :address,
                        contact_number = :contact, email = :email, status = :status
                    WHERE district_id = :id
                ");
                $stmt->execute([
                    ':name' => $name,
                    ':code' => $code,
                    ':address' => $address ?: null,
                    ':contact' => $contactNumber ?: null,
                    ':email' => $email ?: null,
                    ':status' => $status,
                    ':id' => $districtId,
                ]);

                error_log('district_form UPDATE success: districtId=' . $districtId . ', rows=' . $stmt->rowCount());

                $newValues = [
                    'district_name' => $name,
                    'district_code' => $code,
                    'status' => $status,
                ];

                $audit->log('UPDATE', 'districts', (string)$districtId, $oldValues, $newValues);
                error_log('district_form audit log success');
                redirect(url('admin/districts.php'));
            } catch (Throwable $e) {
                error_log('District update error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                $errors[] = 'Failed to update district. Please try again or contact support.';
            }
        } else {
            // Create
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO districts (district_name, district_code, address, contact_number, email, status, created_by)
                    VALUES (:name, :code, :address, :contact, :email, :status, :created_by)
                ");
                $stmt->execute([
                    ':name' => $name,
                    ':code' => $code,
                    ':address' => $address ?: null,
                    ':contact' => $contactNumber ?: null,
                    ':email' => $email ?: null,
                    ':status' => $status,
                    ':created_by' => $_SESSION['user_id'] ?? null,
                ]);

                $newId = (int)$pdo->lastInsertId();
                $audit->log('CREATE', 'districts', (string)$newId, null, ['district_name' => $name, 'district_code' => $code]);
                redirect(url('admin/districts.php'));
            } catch (Throwable $e) {
                error_log('District create error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                $errors[] = 'Failed to create district. Please try again or contact support.';
            }
        }
    }
}

// --------------------------------------------------------
// Load district data for GET requests or after POST errors
// --------------------------------------------------------
if ($districtId > 0 && !$district) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM districts WHERE district_id = :id LIMIT 1");
        $stmt->execute([':id' => $districtId]);
        $district = $stmt->fetch();

        if (!$district) {
            redirect(url('admin/districts.php'));
        }
    } catch (Throwable $e) {
        $districtQueryError = 'District lookup failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        error_log($districtQueryError);
    }
}

$pageTitle = $districtId > 0 ? 'Edit District' : 'Add District';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('admin/dashboard.php')) ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= e(url('admin/districts.php')) ?>">Districts</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($pageTitle) ?></li>
            </ol>
        </nav>
        <h2 class="mb-0"><?= e($pageTitle) ?></h2>
    </div>

    <?php if ($districtQueryError): ?>
        <div class="alert alert-danger"><?= e($districtQueryError) ?></div>
    <?php endif; ?>

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
                    <div class="col-md-6">
                        <label for="district_name" class="form-label">District Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="district_name" name="district_name" required
                               value="<?= e($district['district_name'] ?? old('district_name')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="district_code" class="form-label">District Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="district_code" name="district_code" required
                               value="<?= e($district['district_code'] ?? old('district_code')) ?>">
                    </div>
                    <div class="col-12">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?= e($district['address'] ?? old('address')) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="contact_number" class="form-label">Contact Number</label>
                        <input type="text" class="form-control" id="contact_number" name="contact_number"
                               value="<?= e($district['contact_number'] ?? old('contact_number')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= e($district['email'] ?? old('email')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?= (!isset($district['status']) || $district['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= (isset($district['status']) && $district['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Save District
                    </button>
                    <a href="<?= e(url('admin/districts.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
