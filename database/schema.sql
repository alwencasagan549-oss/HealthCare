-- ============================================================
-- Database: ipihrs_chc
-- ============================================================

CREATE DATABASE IF NOT EXISTS ipihrs_chc
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ipihrs_chc;

-- ============================================================
-- districts
-- ============================================================
CREATE TABLE IF NOT EXISTS districts (
    district_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    district_name VARCHAR(120) NOT NULL,
    district_code VARCHAR(30) NOT NULL UNIQUE,
    address TEXT NULL,
    contact_number VARCHAR(50) NULL,
    email VARCHAR(120) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_districts_status (status),
    INDEX idx_districts_code (district_code)
) ENGINE=InnoDB;

-- ============================================================
-- users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(180) NULL,
    full_name VARCHAR(150) NOT NULL,
    contact_number VARCHAR(50) NULL,
    role ENUM('admin','nurse','dpwh') NOT NULL,
    district_id INT UNSIGNED NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    force_password_change TINYINT(1) NOT NULL DEFAULT 0,
    last_login DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_users_role (role),
    INDEX idx_users_district (district_id),
    INDEX idx_users_status (status),

    CONSTRAINT fk_users_district
        FOREIGN KEY (district_id) REFERENCES districts(district_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- patients
-- ============================================================
CREATE TABLE IF NOT EXISTS patients (
    patient_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_code VARCHAR(40) NOT NULL UNIQUE,
    district_id INT UNSIGNED NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    suffix VARCHAR(20) NULL,
    date_of_birth DATE NULL,
    gender ENUM('male','female','other') NULL,
    address TEXT NULL,
    contact VARCHAR(50) NULL,
    blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','unknown') NULL DEFAULT 'unknown',
    allergies TEXT NULL,
    medical_history TEXT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    registered_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- PhilPen patient demographics
    phic_no VARCHAR(60) NULL,
    civil_status ENUM('single','married','widowed','separated','divorced','others') NULL,
    religion VARCHAR(120) NULL,
    pwd_id_card_no VARCHAR(80) NULL,
    ip_non_ip ENUM('IP','Non-IP') NULL,
    employment_status VARCHAR(120) NULL,
    ethnicity VARCHAR(120) NULL,

    INDEX idx_patients_district (district_id),
    INDEX idx_patients_status (status),
    INDEX idx_patients_code (patient_code),
    INDEX idx_patients_name (last_name, first_name),

    FULLTEXT idx_patients_search (first_name, middle_name, last_name, patient_code),

    CONSTRAINT fk_patients_district
        FOREIGN KEY (district_id) REFERENCES districts(district_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_patients_registered_by
        FOREIGN KEY (registered_by) REFERENCES users(user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- surveys
-- ============================================================
CREATE TABLE IF NOT EXISTS surveys (
    survey_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    survey_type VARCHAR(80) NULL,
    district_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_surveys_district (district_id),
    INDEX idx_surveys_active (is_active),

    CONSTRAINT fk_surveys_district
        FOREIGN KEY (district_id) REFERENCES districts(district_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_surveys_created_by
        FOREIGN KEY (created_by) REFERENCES users(user_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- survey_questions
-- ============================================================
CREATE TABLE IF NOT EXISTS survey_questions (
    question_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('text','number','date','select','multiselect','radio','checkbox','textarea') NOT NULL DEFAULT 'text',
    options JSON NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    question_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_survey_questions_survey (survey_id),
    INDEX idx_survey_questions_order (survey_id, question_order),

    CONSTRAINT fk_survey_questions_survey
        FOREIGN KEY (survey_id) REFERENCES surveys(survey_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- survey_results
-- ============================================================
CREATE TABLE IF NOT EXISTS survey_results (
    result_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id INT UNSIGNED NOT NULL,
    patient_id INT UNSIGNED NOT NULL,
    district_id INT UNSIGNED NOT NULL,
    conducted_by INT UNSIGNED NOT NULL,
    answers JSON NOT NULL,
    remarks TEXT NULL,
    status ENUM('pending','reviewed','flagged') NOT NULL DEFAULT 'pending',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_survey_results_district (district_id),
    INDEX idx_survey_results_patient (patient_id),
    INDEX idx_survey_results_survey (survey_id),
    INDEX idx_survey_results_status (status),
    INDEX idx_survey_results_conducted_by (conducted_by),
    INDEX idx_survey_results_created (created_at),

    CONSTRAINT fk_survey_results_survey
        FOREIGN KEY (survey_id) REFERENCES surveys(survey_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_survey_results_patient
        FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_survey_results_district
        FOREIGN KEY (district_id) REFERENCES districts(district_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_survey_results_conducted_by
        FOREIGN KEY (conducted_by) REFERENCES users(user_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_survey_results_reviewed_by
        FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- risk_assessments
-- ============================================================
CREATE TABLE IF NOT EXISTS risk_assessments (
    assessment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    district_id INT UNSIGNED NOT NULL,
    conducted_by INT UNSIGNED NOT NULL,
    assessment_date DATE NOT NULL,
    status ENUM('pending','reviewed','flagged') NOT NULL DEFAULT 'pending',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Section II: Red Flags
    red_flag_chest_pain TINYINT(1) NOT NULL DEFAULT 0,
    red_flag_difficulty_breathing TINYINT(1) NOT NULL DEFAULT 0,
    red_flag_loss_of_consciousness TINYINT(1) NOT NULL DEFAULT 0,
    red_flag_slurred_speech TINYINT(1) NOT NULL DEFAULT 0,
    red_flag_facial_asymmetry TINYINT(1) NOT NULL DEFAULT 0,
    red_flag_weakness_numbness_one_side TINYINT(1) NOT NULL DEFAULT 0,
    red_flag_disoriented TINYINT(1) NOT NULL DEFAULT 0,
    red_flag_chest_retractions TINYINT(1) NOT NULL DEFAULT 0,
    red_flag_seizure TINYINT(1) NOT NULL DEFAULT 0,
    red_flag_self_harm TINYINT(1) NOT NULL DEFAULT 0,
    red_flag_agitated TINYINT(1) NOT NULL DEFAULT 0,
    red_flag_eye_injury TINYINT(1) NOT NULL DEFAULT 0,
    red_flag_severe_injuries TINYINT(1) NOT NULL DEFAULT 0,

    -- Section III: Past Medical History
    pmh_hypertension TINYINT(1) NOT NULL DEFAULT 0,
    pmh_heart_diseases TINYINT(1) NOT NULL DEFAULT 0,
    pmh_diabetes TINYINT(1) NOT NULL DEFAULT 0,
    pmh_cancer TINYINT(1) NOT NULL DEFAULT 0,
    pmh_copd TINYINT(1) NOT NULL DEFAULT 0,
    pmh_asthma TINYINT(1) NOT NULL DEFAULT 0,
    pmh_allergies TINYINT(1) NOT NULL DEFAULT 0,
    pmh_mental_neuro_substance_disorders TINYINT(1) NOT NULL DEFAULT 0,
    pmh_vision_problems TINYINT(1) NOT NULL DEFAULT 0,
    pmh_surgical_history TINYINT(1) NOT NULL DEFAULT 0,
    pmh_thyroid TINYINT(1) NOT NULL DEFAULT 0,
    pmh_kidney TINYINT(1) NOT NULL DEFAULT 0,

    -- Section IV: Family History
    fh_hypertension TINYINT(1) NOT NULL DEFAULT 0,
    fh_stroke TINYINT(1) NOT NULL DEFAULT 0,
    fh_heart_disease TINYINT(1) NOT NULL DEFAULT 0,
    fh_diabetes TINYINT(1) NOT NULL DEFAULT 0,
    fh_asthma TINYINT(1) NOT NULL DEFAULT 0,
    fh_cancer TINYINT(1) NOT NULL DEFAULT 0,
    fh_kidney_disease TINYINT(1) NOT NULL DEFAULT 0,
    fh_premature_cvd TINYINT(1) NOT NULL DEFAULT 0,
    fh_tb_last_5_years TINYINT(1) NOT NULL DEFAULT 0,
    fh_mental_neuro_substance TINYINT(1) NOT NULL DEFAULT 0,
    fh_copd TINYINT(1) NOT NULL DEFAULT 0,

    -- Section V: NCD Risk Factors
    ncd_tobacco_use TINYINT(1) NOT NULL DEFAULT 0,
    ncd_alcohol_intake TINYINT(1) NOT NULL DEFAULT 0,
    ncd_physical_activity TINYINT(1) NOT NULL DEFAULT 0,
    ncd_nutrition TEXT NULL,
    ncd_weight DECIMAL(5,2) NULL,
    ncd_height DECIMAL(5,2) NULL,
    ncd_bmi DECIMAL(4,2) NULL,
    ncd_waist_circumference DECIMAL(5,2) NULL,
    bp_first_systolic INT NULL,
    bp_first_diastolic INT NULL,
    bp_second_systolic INT NULL,
    bp_second_diastolic INT NULL,

    -- Section VI: Risk Screening
    screening_blood_sugar FLOAT NULL,
    screening_dm_symptoms TINYINT(1) NOT NULL DEFAULT 0,
    screening_lipid_profile TEXT NULL,
    screening_urinalysis TEXT NULL,
    screening_chronic_respiratory_symptoms TINYINT(1) NOT NULL DEFAULT 0,
    screening_pefr FLOAT NULL,

    -- Section VII: Management
    management_lifestyle_modification TEXT NULL,
    management_medications TEXT NULL,
    follow_up_date DATE NULL,
    remarks TEXT NULL,
    assessor_name VARCHAR(150) NULL,
    assessor_signature VARCHAR(255) NULL,

    INDEX idx_risk_assessments_district (district_id),
    INDEX idx_risk_assessments_patient (patient_id),
    INDEX idx_risk_assessments_conducted_by (conducted_by),
    INDEX idx_risk_assessments_status (status),
    INDEX idx_risk_assessments_date (assessment_date),

    CONSTRAINT fk_risk_assessments_patient
        FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_risk_assessments_district
        FOREIGN KEY (district_id) REFERENCES districts(district_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_risk_assessments_conducted_by
        FOREIGN KEY (conducted_by) REFERENCES users(user_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_risk_assessments_reviewed_by
        FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- audit_logs
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action ENUM('CREATE','UPDATE','DELETE','LOGIN','LOGOUT','VIEW','OTHER') NOT NULL DEFAULT 'OTHER',
    table_affected VARCHAR(120) NULL,
    record_id VARCHAR(120) NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_audit_logs_user (user_id),
    INDEX idx_audit_logs_table (table_affected),
    INDEX idx_audit_logs_created (created_at),
    INDEX idx_audit_logs_action (action),

    CONSTRAINT fk_audit_logs_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Default Data
-- ============================================================

INSERT INTO districts (district_name, district_code, address, contact_number, email, created_by)
VALUES
    ('Poblacion', 'POB', 'Poblacion Health Center, City Hall Compound', '091234567801', 'pob@chc.local', NULL),
    ('West',      'WES', 'West District Health Center, West Avenue',      '091234567802', 'wes@chc.local', NULL),
    ('East',      'EAS', 'East District Health Center, East Boulevard',   '091234567803', 'eas@chc.local', NULL);

INSERT INTO users (username, password_hash, email, full_name, contact_number, role, district_id, status, force_password_change, created_by)
VALUES
    ('admin',     '$2y$12$Kp9JeRoPK2gsH2KhvjO7ZeJ7zq.KNlxlr6c8kwZNW4nXfFhOptbtW', 'admin@chc.local',     'System Administrator',        '091234567800', 'admin', NULL,     'active', 0, NULL),
    ('nurse.pob', '$2y$12$k1Vw0Wk0rrSy5tRVf9VEseVDtC3lnB0OynESgBdOHcETAh/b1Z5xa', 'nurse.pob@chc.local', 'Nurse Pob',                   '091234567811', 'nurse', 1,        'active', 0, NULL),
    ('nurse.wes', '$2y$12$k1Vw0Wk0rrSy5tRVf9VEseVDtC3lnB0OynESgBdOHcETAh/b1Z5xa', 'nurse.wes@chc.local', 'Nurse Wes',                   '091234567812', 'nurse', 2,        'active', 0, NULL),
    ('nurse.eas', '$2y$12$k1Vw0Wk0rrSy5tRVf9VEseVDtC3lnB0OynESgBdOHcETAh/b1Z5xa', 'nurse.eas@chc.local', 'Nurse Eas',                   '091234567813', 'nurse', 3,        'active', 0, NULL),
    ('dpwh.pob',  '$2y$12$15VUxwudYYvotOtQD.jqg.rUN6jv7KeZEyXdl8oll./Wk7/k7CcR2', 'dpwh.pob@chc.local',  'DPWH Pob',                    '091234567821', 'dpwh',  1,        'active', 1, NULL),
    ('dpwh.wes',  '$2y$12$15VUxwudYYvotOtQD.jqg.rUN6jv7KeZEyXdl8oll./Wk7/k7CcR2', 'dpwh.wes@chc.local',  'DPWH Wes',                    '091234567822', 'dpwh',  2,        'active', 1, NULL),
    ('dpwh.eas',  '$2y$12$15VUxwudYYvotOtQD.jqg.rUN6jv7KeZEyXdl8oll./Wk7/k7CcR2', 'dpwh.eas@chc.local',  'DPWH Eas',                    '091234567823', 'dpwh',  3,        'active', 1, NULL);

INSERT INTO patients (district_id, patient_code, first_name, middle_name, last_name, suffix, date_of_birth, gender, address, contact, blood_type, allergies, medical_history, status, registered_by)
VALUES
    (1, 'PAT-POB-0001', 'Juan',  'Dela',  'Cruz',   NULL, '1985-03-12', 'male',   'Poblacion',      '09180000001', 'A+',  'None',     'Hypertension', 'active', 1),
    (1, 'PAT-POB-0002', 'Maria', 'Santos','Reyes',   NULL, '1990-07-05', 'female', 'Poblacion',      '09180000002', 'O+',  'Penicillin','None',        'active', 1),
    (2, 'PAT-WES-0001', 'Jose',  'Ramos', 'Garcia',  'Jr.', '1978-11-22', 'male',   'West District',  '09180000003', 'B+',  'None',     'Asthma',      'active', 1),
    (3, 'PAT-EAS-0001', 'Ana',   'Bautista','Mendoza',NULL,'1995-01-30', 'female', 'East Boulevard', '09180000004', 'AB+', 'None',     'None',        'active', 1);

INSERT INTO surveys (survey_name, description, survey_type, district_id, created_by, is_active)
VALUES ('General Health Check', 'Routine general health assessment for community patients.', 'General', NULL, 1, 1);

INSERT INTO survey_questions (survey_id, question_text, question_type, options, is_required, question_order)
VALUES
    (1, 'Do you have any current symptoms?',            'textarea', NULL,                                                              1, 1),
    (1, 'On a scale of 1-10, how would you rate your overall health?', 'number', NULL,                                                  1, 2),
    (1, 'Do you have any known allergies?',             'text',     NULL,                                                              0, 3),
    (1, 'Are you currently taking any medications?',    'radio',    'Yes,No',                                                           1, 4),
    (1, 'When was your last check-up?',                 'date',     NULL,                                                              0, 5);

INSERT INTO survey_results (survey_id, patient_id, district_id, conducted_by, answers, remarks, status)
VALUES (
    1,
    1,
    1,
    5,
    JSON_OBJECT(
        'Do you have any current symptoms?', 'Mild headache and fatigue',
        'On a scale of 1-10, how would you rate your overall health?', '7',
        'Do you have any known allergies?', 'None',
        'Are you currently taking any medications?', 'No',
        'When was your last check-up?', '2025-12-15'
    ),
    'Routine screening completed.',
    'pending'
);
