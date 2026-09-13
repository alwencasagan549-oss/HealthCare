<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

// ============================================================
// IPIHRS-CHC Seed Data
// ============================================================

$pdo = Database::getConnection();

// Use transaction so we can roll back on failure
$pdo->beginTransaction();

try {
    // --------------------------------------------------------
    // Helper
    // --------------------------------------------------------
    function password(string $raw): string {
        return password_hash($raw, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // --------------------------------------------------------
    // Districts
    // --------------------------------------------------------
    $districts = [
        ['Poblacion', 'POB', 'Poblacion Health Center, City Hall Compound', '091234567801', 'pob@chc.local'],
        ['West', 'WES', 'West District Health Center, West Avenue', '091234567802', 'wes@chc.local'],
        ['East', 'EAS', 'East District Health Center, East Boulevard', '091234567803', 'eas@chc.local'],
    ];

    $districtIds = [];
    $stmt = $pdo->prepare("
        INSERT INTO districts (district_name, district_code, address, contact_number, email, created_by)
        VALUES (:name, :code, :address, :contact, :email, :created_by)
    ");
    foreach ($districts as $d) {
        $stmt->execute([
            ':name'      => $d[0],
            ':code'      => $d[1],
            ':address'   => $d[2],
            ':contact'   => $d[3],
            ':email'     => $d[4],
            ':created_by'=> null,
        ]);
        $districtIds[] = (int)$pdo->lastInsertId();
    }

    // --------------------------------------------------------
    // Users: Admin + Nurses + DPWH
    // --------------------------------------------------------
    $users = [
        // Admin
        [
            'admin',
            password('Admin@123'),
            'admin@chc.local',
            'System Administrator',
            '091234567800',
            'admin',
            null,
            'active',
            0,
        ],
        // Nurses
        [
            'nurse.pob',
            password('Nurse@123'),
            'nurse.pob@chc.local',
            'Nurse Pob',
            '091234567811',
            'nurse',
            $districtIds[0],
            'active',
            0,
        ],
        [
            'nurse.wes',
            password('Nurse@123'),
            'nurse.wes@chc.local',
            'Nurse Wes',
            '091234567812',
            'nurse',
            $districtIds[1],
            'active',
            0,
        ],
        [
            'nurse.eas',
            password('Nurse@123'),
            'nurse.eas@chc.local',
            'Nurse Eas',
            '091234567813',
            'nurse',
            $districtIds[2],
            'active',
            0,
        ],
        // DPWH
        [
            'dpwh.pob',
            password('Dpwh@123'),
            'dpwh.pob@chc.local',
            'DPWH Pob',
            '091234567821',
            'dpwh',
            $districtIds[0],
            'active',
            1,
        ],
        [
            'dpwh.wes',
            password('Dpwh@123'),
            'dpwh.wes@chc.local',
            'DPWH Wes',
            '091234567822',
            'dpwh',
            $districtIds[1],
            'active',
            1,
        ],
        [
            'dpwh.eas',
            password('Dpwh@123'),
            'dpwh.eas@chc.local',
            'DPWH Eas',
            '091234567823',
            'dpwh',
            $districtIds[2],
            'active',
            1,
        ],
    ];

    // Resolve admin user_id for created_by references
    $adminId = null;
    $stmtUser = $pdo->prepare("
        INSERT INTO users
            (username, password_hash, email, full_name, contact_number, role, district_id, status, force_password_change, created_by)
        VALUES
            (:username, :password_hash, :email, :full_name, :contact_number, :role, :district_id, :status, :force_password_change, :created_by)
    ");

    foreach ($users as $u) {
        $stmtUser->execute([
            ':username'           => $u[0],
            ':password_hash'      => $u[1],
            ':email'              => $u[2],
            ':full_name'          => $u[3],
            ':contact_number'     => $u[4],
            ':role'               => $u[5],
            ':district_id'        => $u[6],
            ':status'             => $u[7],
            ':force_password_change'=> $u[8],
            ':created_by'         => null,
        ]);

        $userId = (int)$pdo->lastInsertId();
        if ($u[5] === 'admin') {
            $adminId = $userId;
        }
    }

    // --------------------------------------------------------
    // Patients (sample)
    // --------------------------------------------------------
    $patients = [
        [$districtIds[0], 'PAT-POB-0001', 'Juan', 'Dela', 'Cruz', null, '1985-03-12', 'male', 'Poblacion', '09180000001', 'A+', 'None', 'Hypertension', 'active', $adminId],
        [$districtIds[0], 'PAT-POB-0002', 'Maria', 'Santos', 'Reyes', null, '1990-07-05', 'female', 'Poblacion', '09180000002', 'O+', 'Penicillin', 'None', 'active', $adminId],
        [$districtIds[1], 'PAT-WES-0001', 'Jose', 'Ramos', 'Garcia', 'Jr.', '1978-11-22', 'male', 'West District', '09180000003', 'B+', 'None', 'Asthma', 'active', $adminId],
        [$districtIds[2], 'PAT-EAS-0001', 'Ana', 'Bautista', 'Mendoza', null, '1995-01-30', 'female', 'East District', '09180000004', 'AB+', 'None', 'None', 'active', $adminId],
    ];

    $stmtPatient = $pdo->prepare("
        INSERT INTO patients
            (district_id, patient_code, first_name, middle_name, last_name, suffix, date_of_birth, gender, address, contact, blood_type, allergies, medical_history, status, registered_by)
        VALUES
            (:district_id, :patient_code, :first_name, :middle_name, :last_name, :suffix, :date_of_birth, :gender, :address, :contact, :blood_type, :allergies, :medical_history, :status, :registered_by)
    ");

    foreach ($patients as $p) {
        $stmtPatient->execute([
            ':district_id'   => $p[0],
            ':patient_code'  => $p[1],
            ':first_name'    => $p[2],
            ':middle_name'   => $p[3],
            ':last_name'     => $p[4],
            ':suffix'        => $p[5],
            ':date_of_birth' => $p[6],
            ':gender'        => $p[7],
            ':address'       => $p[8],
            ':contact'       => $p[9],
            ':blood_type'    => $p[10],
            ':allergies'     => $p[11],
            ':medical_history'=>$p[12],
            ':status'        => $p[13],
            ':registered_by' => $p[14],
        ]);
    }

    // --------------------------------------------------------
    // Survey Template: General Health Check
    // --------------------------------------------------------
    $surveyId = null;
    $stmtSurvey = $pdo->prepare("
        INSERT INTO surveys (survey_name, description, survey_type, district_id, created_by, is_active)
        VALUES (:name, :desc, :type, :district_id, :created_by, :is_active)
    ");
    $stmtSurvey->execute([
        ':name'        => 'General Health Check',
        ':desc'        => 'Routine general health assessment for community patients.',
        ':type'        => 'General',
        ':district_id' => null,
        ':created_by'  => $adminId,
        ':is_active'   => 1,
    ]);
    $surveyId = (int)$pdo->lastInsertId();

    $questions = [
        ['Do you have any current symptoms?', 'textarea', null, 1, 1],
        ['On a scale of 1-10, how would you rate your overall health?', 'number', null, 1, 2],
        ['Do you have any known allergies?', 'text', null, 0, 3],
        ['Are you currently taking any medications?', 'radio', json_encode(['Yes','No']), 1, 4],
        ['When was your last check-up?', 'date', null, 0, 5],
    ];

    $stmtQ = $pdo->prepare("
        INSERT INTO survey_questions
            (survey_id, question_text, question_type, options, is_required, question_order)
        VALUES
            (:survey_id, :question_text, :question_type, :options, :is_required, :question_order)
    ");

    foreach ($questions as $q) {
        $stmtQ->execute([
            ':survey_id'     => $surveyId,
            ':question_text' => $q[0],
            ':question_type' => $q[1],
            ':options'       => $q[2],
            ':is_required'   => (int)$q[3],
            ':question_order'=> (int)$q[4],
        ]);
    }

    // --------------------------------------------------------
    // Sample survey result
    // --------------------------------------------------------
    $sampleAnswers = json_encode([
        'Do you have any current symptoms?'            => 'Mild headache and fatigue',
        'On a scale of 1-10, how would you rate your overall health?' => '7',
        'Do you have any known allergies?'             => 'None',
        'Are you currently taking any medications?'    => 'No',
        'When was your last check-up?'                 => '2025-12-15',
    ], JSON_UNESCAPED_UNICODE);

    $stmtResult = $pdo->prepare("
        INSERT INTO survey_results
            (survey_id, patient_id, district_id, conducted_by, answers, remarks, status)
        VALUES
            (:survey_id, :patient_id, :district_id, :conducted_by, :answers, :remarks, :status)
    ");

    // First patient in district 1
    $stmtPatientFirst = $pdo->prepare("SELECT patient_id FROM patients WHERE district_id = :district_id LIMIT 1");
    $stmtPatientFirst->execute([':district_id' => $districtIds[0]]);
    $firstPatientId = (int)$stmtPatientFirst->fetchColumn();

    // First dpwh user in district 1
    $stmtDpwh = $pdo->prepare("SELECT user_id FROM users WHERE role = 'dpwh' AND district_id = :district_id LIMIT 1");
    $stmtDpwh->execute([':district_id' => $districtIds[0]]);
    $dpwhId = (int)$stmtDpwh->fetchColumn();

    $stmtResult->execute([
        ':survey_id'    => $surveyId,
        ':patient_id'   => $firstPatientId,
        ':district_id'  => $districtIds[0],
        ':conducted_by' => $dpwhId,
        ':answers'      => $sampleAnswers,
        ':remarks'      => 'Routine screening completed.',
        ':status'       => 'pending',
    ]);

    $pdo->commit();

    echo "Seed data inserted successfully.\n";
    echo "Default accounts:\n";
    echo "  Admin   : admin / Admin@123\n";
    echo "  Nurses  : nurse.pob / Nurse@123 | nurse.wes / Nurse@123 | nurse.eas / Nurse@123\n";
    echo "  DPWH    : dpwh.pob / Dpwh@123 | dpwh.wes / Dpwh@123 | dpwh.eas / Dpwh@123\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    echo "Seed failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
