-- ============================================================
-- Database: ipihrs_chc
-- ============================================================

-- ============================================================
-- districts
-- ============================================================
CREATE TABLE IF NOT EXISTS districts (
    district_id SERIAL PRIMARY KEY,
    district_name VARCHAR(120) NOT NULL,
    district_code VARCHAR(30) NOT NULL UNIQUE,
    address TEXT NULL,
    contact_number VARCHAR(50) NULL,
    email VARCHAR(120) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active'
        CHECK (status IN ('active','inactive')),
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_districts_status ON districts(status);
CREATE INDEX IF NOT EXISTS idx_districts_code ON districts(district_code);

-- ============================================================
-- users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    user_id SERIAL PRIMARY KEY,
    username VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(180) NULL,
    full_name VARCHAR(150) NOT NULL,
    contact_number VARCHAR(50) NULL,
    role VARCHAR(20) NOT NULL
        CHECK (role IN ('admin','nurse','dpwh')),
    district_id INT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active'
        CHECK (status IN ('active','inactive')),
    force_password_change BOOLEAN NOT NULL DEFAULT FALSE,
    last_login TIMESTAMP NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_users_district
        FOREIGN KEY (district_id) REFERENCES districts(district_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_district ON users(district_id);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);

-- ============================================================
-- patients
-- ============================================================
CREATE TABLE IF NOT EXISTS patients (
    patient_id SERIAL PRIMARY KEY,
    patient_code VARCHAR(40) NOT NULL UNIQUE,
    district_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    suffix VARCHAR(20) NULL,
    date_of_birth DATE NULL,
    gender VARCHAR(20) NULL
        CHECK (gender IN ('male','female','other')),
    address TEXT NULL,
    contact VARCHAR(50) NULL,
    blood_type VARCHAR(20) NULL
        CHECK (blood_type IN ('A+','A-','B+','B-','AB+','AB-','O+','O-','unknown') OR blood_type IS NULL),
    allergies TEXT NULL,
    medical_history TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active'
        CHECK (status IN ('active','inactive')),
    registered_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- PhilPen patient demographics
    phic_no VARCHAR(60) NULL,
    civil_status VARCHAR(20) NULL
        CHECK (civil_status IN ('single','married','widowed','separated','divorced','others') OR civil_status IS NULL),
    religion VARCHAR(120) NULL,
    pwd_id_card_no VARCHAR(80) NULL,
    ip_non_ip VARCHAR(10) NULL
        CHECK (ip_non_ip IN ('IP','Non-IP') OR ip_non_ip IS NULL),
    employment_status VARCHAR(120) NULL,
    ethnicity VARCHAR(120) NULL,

    CONSTRAINT fk_patients_district
        FOREIGN KEY (district_id) REFERENCES districts(district_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_patients_registered_by
        FOREIGN KEY (registered_by) REFERENCES users(user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_patients_district ON patients(district_id);
CREATE INDEX IF NOT EXISTS idx_patients_status ON patients(status);
CREATE INDEX IF NOT EXISTS idx_patients_code ON patients(patient_code);
CREATE INDEX IF NOT EXISTS idx_patients_name ON patients(last_name, first_name);

-- ============================================================
-- surveys
-- ============================================================
CREATE TABLE IF NOT EXISTS surveys (
    survey_id SERIAL PRIMARY KEY,
    survey_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    survey_type VARCHAR(80) NULL,
    district_id INT NULL,
    created_by INT NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_surveys_district
        FOREIGN KEY (district_id) REFERENCES districts(district_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_surveys_created_by
        FOREIGN KEY (created_by) REFERENCES users(user_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_surveys_district ON surveys(district_id);
CREATE INDEX IF NOT EXISTS idx_surveys_active ON surveys(is_active);

-- ============================================================
-- survey_questions
-- ============================================================
CREATE TABLE IF NOT EXISTS survey_questions (
    question_id SERIAL PRIMARY KEY,
    survey_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type VARCHAR(40) NOT NULL DEFAULT 'text'
        CHECK (question_type IN ('text','number','date','select','multiselect','radio','checkbox','textarea')),
    options JSONB NULL,
    is_required BOOLEAN NOT NULL DEFAULT TRUE,
    question_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_survey_questions_survey
        FOREIGN KEY (survey_id) REFERENCES surveys(survey_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_survey_questions_survey ON survey_questions(survey_id);
CREATE INDEX IF NOT EXISTS idx_survey_questions_order ON survey_questions(survey_id, question_order);

-- ============================================================
-- survey_results
-- ============================================================
CREATE TABLE IF NOT EXISTS survey_results (
    result_id SERIAL PRIMARY KEY,
    survey_id INT NOT NULL,
    patient_id INT NOT NULL,
    district_id INT NOT NULL,
    conducted_by INT NOT NULL,
    answers JSONB NOT NULL,
    remarks TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending','reviewed','flagged')),
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

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
);

CREATE INDEX IF NOT EXISTS idx_survey_results_district ON survey_results(district_id);
CREATE INDEX IF NOT EXISTS idx_survey_results_patient ON survey_results(patient_id);
CREATE INDEX IF NOT EXISTS idx_survey_results_survey ON survey_results(survey_id);
CREATE INDEX IF NOT EXISTS idx_survey_results_status ON survey_results(status);
CREATE INDEX IF NOT EXISTS idx_survey_results_conducted_by ON survey_results(conducted_by);
CREATE INDEX IF NOT EXISTS idx_survey_results_created ON survey_results(created_at);

-- ============================================================
-- risk_assessments
-- ============================================================
CREATE TABLE IF NOT EXISTS risk_assessments (
    assessment_id SERIAL PRIMARY KEY,
    patient_id INT NOT NULL,
    district_id INT NOT NULL,
    conducted_by INT NOT NULL,
    assessment_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending','reviewed','flagged')),
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Section II: Red Flags
    red_flag_chest_pain BOOLEAN NOT NULL DEFAULT FALSE,
    red_flag_difficulty_breathing BOOLEAN NOT NULL DEFAULT FALSE,
    red_flag_loss_of_consciousness BOOLEAN NOT NULL DEFAULT FALSE,
    red_flag_slurred_speech BOOLEAN NOT NULL DEFAULT FALSE,
    red_flag_facial_asymmetry BOOLEAN NOT NULL DEFAULT FALSE,
    red_flag_weakness_numbness_one_side BOOLEAN NOT NULL DEFAULT FALSE,
    red_flag_disoriented BOOLEAN NOT NULL DEFAULT FALSE,
    red_flag_chest_retractions BOOLEAN NOT NULL DEFAULT FALSE,
    red_flag_seizure BOOLEAN NOT NULL DEFAULT FALSE,
    red_flag_self_harm BOOLEAN NOT NULL DEFAULT FALSE,
    red_flag_agitated BOOLEAN NOT NULL DEFAULT FALSE,
    red_flag_eye_injury BOOLEAN NOT NULL DEFAULT FALSE,
    red_flag_severe_injuries BOOLEAN NOT NULL DEFAULT FALSE,

    -- Section III: Past Medical History
    pmh_hypertension BOOLEAN NOT NULL DEFAULT FALSE,
    pmh_heart_diseases BOOLEAN NOT NULL DEFAULT FALSE,
    pmh_diabetes BOOLEAN NOT NULL DEFAULT FALSE,
    pmh_cancer BOOLEAN NOT NULL DEFAULT FALSE,
    pmh_copd BOOLEAN NOT NULL DEFAULT FALSE,
    pmh_asthma BOOLEAN NOT NULL DEFAULT FALSE,
    pmh_allergies BOOLEAN NOT NULL DEFAULT FALSE,
    pmh_mental_neuro_substance_disorders BOOLEAN NOT NULL DEFAULT FALSE,
    pmh_vision_problems BOOLEAN NOT NULL DEFAULT FALSE,
    pmh_surgical_history BOOLEAN NOT NULL DEFAULT FALSE,
    pmh_thyroid BOOLEAN NOT NULL DEFAULT FALSE,
    pmh_kidney BOOLEAN NOT NULL DEFAULT FALSE,

    -- Section IV: Family History
    fh_hypertension BOOLEAN NOT NULL DEFAULT FALSE,
    fh_stroke BOOLEAN NOT NULL DEFAULT FALSE,
    fh_heart_disease BOOLEAN NOT NULL DEFAULT FALSE,
    fh_diabetes BOOLEAN NOT NULL DEFAULT FALSE,
    fh_asthma BOOLEAN NOT NULL DEFAULT FALSE,
    fh_cancer BOOLEAN NOT NULL DEFAULT FALSE,
    fh_kidney_disease BOOLEAN NOT NULL DEFAULT FALSE,
    fh_premature_cvd BOOLEAN NOT NULL DEFAULT FALSE,
    fh_tb_last_5_years BOOLEAN NOT NULL DEFAULT FALSE,
    fh_mental_neuro_substance BOOLEAN NOT NULL DEFAULT FALSE,
    fh_copd BOOLEAN NOT NULL DEFAULT FALSE,

    -- Section V: NCD Risk Factors
    ncd_tobacco_use BOOLEAN NOT NULL DEFAULT FALSE,
    ncd_alcohol_intake BOOLEAN NOT NULL DEFAULT FALSE,
    ncd_physical_activity BOOLEAN NOT NULL DEFAULT FALSE,
    ncd_nutrition TEXT NULL,
    ncd_weight NUMERIC(5,2) NULL,
    ncd_height NUMERIC(5,2) NULL,
    ncd_bmi NUMERIC(4,2) NULL,
    ncd_waist_circumference NUMERIC(5,2) NULL,
    bp_first_systolic INT NULL,
    bp_first_diastolic INT NULL,
    bp_second_systolic INT NULL,
    bp_second_diastolic INT NULL,

    -- Section VI: Risk Screening
    screening_blood_sugar NUMERIC NULL,
    screening_dm_symptoms BOOLEAN NOT NULL DEFAULT FALSE,
    screening_lipid_profile TEXT NULL,
    screening_urinalysis TEXT NULL,
    screening_chronic_respiratory_symptoms BOOLEAN NOT NULL DEFAULT FALSE,
    screening_pefr NUMERIC NULL,

    -- Section VII: Management
    management_lifestyle_modification TEXT NULL,
    management_medications TEXT NULL,
    follow_up_date DATE NULL,
    remarks TEXT NULL,
    assessor_name VARCHAR(150) NULL,
    assessor_signature VARCHAR(255) NULL,

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
);

CREATE INDEX IF NOT EXISTS idx_risk_assessments_district ON risk_assessments(district_id);
CREATE INDEX IF NOT EXISTS idx_risk_assessments_patient ON risk_assessments(patient_id);
CREATE INDEX IF NOT EXISTS idx_risk_assessments_conducted_by ON risk_assessments(conducted_by);
CREATE INDEX IF NOT EXISTS idx_risk_assessments_status ON risk_assessments(status);
CREATE INDEX IF NOT EXISTS idx_risk_assessments_date ON risk_assessments(assessment_date);

-- ============================================================
-- audit_logs
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    log_id BIGSERIAL PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(20) NOT NULL DEFAULT 'OTHER'
        CHECK (action IN ('CREATE','UPDATE','DELETE','LOGIN','LOGOUT','VIEW','OTHER')),
    table_affected VARCHAR(120) NULL,
    record_id VARCHAR(120) NULL,
    old_values JSONB NULL,
    new_values JSONB NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_user ON audit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_table ON audit_logs(table_affected);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action);

-- ============================================================
-- Updated-at trigger helper
-- ============================================================
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_districts_updated_at
    BEFORE UPDATE ON districts
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TRIGGER trg_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TRIGGER trg_patients_updated_at
    BEFORE UPDATE ON patients
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TRIGGER trg_surveys_updated_at
    BEFORE UPDATE ON surveys
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TRIGGER trg_survey_questions_updated_at
    BEFORE UPDATE ON survey_questions
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TRIGGER trg_survey_results_updated_at
    BEFORE UPDATE ON survey_results
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
-- Default Data
-- ============================================================

INSERT INTO districts (district_name, district_code, address, contact_number, email, created_by)
VALUES
    ('Poblacion', 'POB', 'Poblacion Health Center, City Hall Compound', '091234567801', 'pob@chc.local', NULL),
    ('West',      'WES', 'West District Health Center, West Avenue',      '091234567802', 'wes@chc.local', NULL),
    ('East',      'EAS', 'East District Health Center, East Boulevard',   '091234567803', 'eas@chc.local', NULL)
ON CONFLICT (district_code) DO NOTHING;

INSERT INTO users (username, password_hash, email, full_name, contact_number, role, district_id, status, force_password_change, created_by)
VALUES
    ('admin',     '$2y$12$Kp9JeRoPK2gsH2KhvjO7ZeJ7zq.KNlxlr6c8kwZNW4nXfFhOptbtW', 'admin@chc.local',     'System Administrator',        '091234567800', 'admin', NULL,     'active', FALSE, NULL),
    ('nurse.pob', '$2y$12$k1Vw0Wk0rrSy5tRVf9VEseVDtC3lnB0OynESgBdOHcETAh/b1Z5xa', 'nurse.pob@chc.local', 'Nurse Pob',                   '091234567811', 'nurse', 1,        'active', FALSE, NULL),
    ('nurse.wes', '$2y$12$k1Vw0Wk0rrSy5tRVf9VEseVDtC3lnB0OynESgBdOHcETAh/b1Z5xa', 'nurse.wes@chc.local', 'Nurse Wes',                   '091234567812', 'nurse', 2,        'active', FALSE, NULL),
    ('nurse.eas', '$2y$12$k1Vw0Wk0rrSy5tRVf9VEseVDtC3lnB0OynESgBdOHcETAh/b1Z5xa', 'nurse.eas@chc.local', 'Nurse Eas',                   '091234567813', 'nurse', 3,        'active', FALSE, NULL),
    ('dpwh.pob',  '$2y$12$15VUxwudYYvotOtQD.jqg.rUN6jv7KeZEyXdl8oll./Wk7/k7CcR2', 'dpwh.pob@chc.local',  'DPWH Pob',                    '091234567821', 'dpwh',  1,        'active', TRUE,  NULL),
    ('dpwh.wes',  '$2y$12$15VUxwudYYvotOtQD.jqg.rUN6jv7KeZEyXdl8oll./Wk7/k7CcR2', 'dpwh.wes@chc.local',  'DPWH Wes',                    '091234567822', 'dpwh',  2,        'active', TRUE,  NULL),
    ('dpwh.eas',  '$2y$12$15VUxwudYYvotOtQD.jqg.rUN6jv7KeZEyXdl8oll./Wk7/k7CcR2', 'dpwh.eas@chc.local',  'DPWH Eas',                    '091234567823', 'dpwh',  3,        'active', TRUE,  NULL)
ON CONFLICT (username) DO NOTHING;

INSERT INTO patients (district_id, patient_code, first_name, middle_name, last_name, suffix, date_of_birth, gender, address, contact, blood_type, allergies, medical_history, status, registered_by)
VALUES
    (1, 'PAT-POB-0001', 'Juan',  'Dela',  'Cruz',   NULL, '1985-03-12', 'male',   'Poblacion',      '09180000001', 'A+',  'None',     'Hypertension', 'active', 1),
    (1, 'PAT-POB-0002', 'Maria', 'Santos','Reyes',   NULL, '1990-07-05', 'female', 'Poblacion',      '09180000002', 'O+',  'Penicillin','None',        'active', 1),
    (2, 'PAT-WES-0001', 'Jose',  'Ramos', 'Garcia',  'Jr.', '1978-11-22', 'male',   'West District',  '09180000003', 'B+',  'None',     'Asthma',      'active', 1),
    (3, 'PAT-EAS-0001', 'Ana',   'Bautista','Mendoza',NULL,'1995-01-30', 'female', 'East Boulevard', '09180000004', 'AB+', 'None',     'None',        'active', 1)
ON CONFLICT (patient_code) DO NOTHING;

INSERT INTO surveys (survey_name, description, survey_type, district_id, created_by, is_active)
VALUES ('General Health Check', 'Routine general health assessment for community patients.', 'General', NULL, 1, TRUE)
ON CONFLICT DO NOTHING;

INSERT INTO survey_questions (survey_id, question_text, question_type, options, is_required, question_order)
VALUES
    (1, 'Do you have any current symptoms?',            'textarea', NULL,                                                              TRUE,  1),
    (1, 'On a scale of 1-10, how would you rate your overall health?', 'number', NULL,                                                  TRUE,  2),
    (1, 'Do you have any known allergies?',             'text',     NULL,                                                              FALSE, 3),
    (1, 'Are you currently taking any medications?',    'radio',    '["Yes","No"]'::jsonb,                                            TRUE,  4),
    (1, 'When was your last check-up?',                 'date',     NULL,                                                              FALSE, 5);

INSERT INTO survey_results (survey_id, patient_id, district_id, conducted_by, answers, remarks, status)
VALUES (
    1,
    1,
    1,
    5,
    '{"Do you have any current symptoms?":"Mild headache and fatigue","On a scale of 1-10, how would you rate your overall health?":"7","Do you have any known allergies?":"None","Are you currently taking any medications?":"No","When was your last check-up?":"2025-12-15"}'::jsonb,
    'Routine screening completed.',
    'pending'
);