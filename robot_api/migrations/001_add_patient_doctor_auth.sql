-- Smart Medi Box - Patient/Doctor Authentication Migration
-- Version: 2.0.0
-- This migration adds support for patient and doctor user types

-- Create ENUM for user roles
CREATE TYPE user_role AS ENUM ('PATIENT', 'DOCTOR');

-- Create ENUM for gender
CREATE TYPE gender_type AS ENUM ('MALE', 'FEMALE', 'OTHER');

-- Create ENUM for organ transplant types
CREATE TYPE organ_type AS ENUM (
    'KIDNEY',
    'LIVER',
    'HEART',
    'LUNG',
    'PANCREAS',
    'INTESTINE',
    'CORNEA',
    'BONE_MARROW',
    'TISSUE',
    'NONE'
);

-- Create ENUM for blood types
CREATE TYPE blood_type AS ENUM ('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'UNKNOWN');

-- Drop old users table and related dependencies
ALTER TABLE schedules DROP CONSTRAINT schedules_user_id_fkey;
ALTER TABLE temperature_logs DROP CONSTRAINT temperature_logs_user_id_fkey;
ALTER TABLE temperature_settings DROP CONSTRAINT temperature_settings_user_id_fkey;
ALTER TABLE alarm_logs DROP CONSTRAINT alarm_logs_user_id_fkey;
ALTER TABLE arduino_commands DROP CONSTRAINT arduino_commands_user_id_fkey;
ALTER TABLE device_registry DROP CONSTRAINT device_registry_user_id_fkey;
ALTER TABLE qr_tokens DROP CONSTRAINT qr_tokens_user_id_fkey;
ALTER TABLE auth_logs DROP CONSTRAINT auth_logs_user_id_fkey;
ALTER TABLE schedule_logs DROP CONSTRAINT schedule_logs_user_id_fkey;
ALTER TABLE sms_notifications DROP CONSTRAINT sms_notifications_user_id_fkey;
ALTER TABLE rfid_cards DROP CONSTRAINT rfid_cards_user_id_fkey;
ALTER TABLE system_logs DROP CONSTRAINT system_logs_user_id_fkey;

DROP TABLE IF EXISTS users CASCADE;

-- New Users Table (Base)
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role user_role NOT NULL DEFAULT 'PATIENT',
    status user_status DEFAULT 'ACTIVE',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- Patients Table
CREATE TABLE patients (
    id SERIAL PRIMARY KEY,
    user_id INT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    nic VARCHAR(50) UNIQUE NOT NULL,  -- Primary identifier (National ID)
    name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    age INT GENERATED ALWAYS AS (EXTRACT(YEAR FROM AGE(date_of_birth))::INT) STORED,
    gender gender_type,
    blood_type blood_type,
    transplanted_organ organ_type DEFAULT 'NONE',
    transplantation_date DATE NULL,
    phone_number VARCHAR(20),
    emergency_contact VARCHAR(20),
    medical_history TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Doctors Table
CREATE TABLE doctors (
    id SERIAL PRIMARY KEY,
    user_id INT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    nic VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    age INT GENERATED ALWAYS AS (EXTRACT(YEAR FROM AGE(date_of_birth))::INT) STORED,
    specialization VARCHAR(100) NOT NULL,
    hospital VARCHAR(100) NOT NULL,
    license_number VARCHAR(50) UNIQUE,
    phone_number VARCHAR(20),
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Patient-Doctor Assignment Table
CREATE TABLE patient_doctor_assignments (
    id SERIAL PRIMARY KEY,
    patient_id INT NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
    doctor_id INT NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    UNIQUE(patient_id, doctor_id)
);

-- Articles Table (for doctors to post)
CREATE TABLE articles (
    id SERIAL PRIMARY KEY,
    doctor_id INT NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    summary VARCHAR(500),
    category VARCHAR(100),
    is_published BOOLEAN DEFAULT TRUE,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Device Pairing Tokens
CREATE TABLE pairing_tokens (
    id SERIAL PRIMARY KEY,
    patient_id INT NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
    token VARCHAR(255) UNIQUE NOT NULL,
    device_mac_address VARCHAR(20) UNIQUE,
    device_name VARCHAR(100),
    is_used BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Session Tokens Table
CREATE TABLE session_tokens (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(500) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Re-add foreign keys to old tables with cascade behavior
ALTER TABLE schedules ADD CONSTRAINT schedules_user_id_fkey 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE temperature_logs ADD CONSTRAINT temperature_logs_user_id_fkey 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE temperature_settings ADD CONSTRAINT temperature_settings_user_id_fkey 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE alarm_logs ADD CONSTRAINT alarm_logs_user_id_fkey 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE arduino_commands ADD CONSTRAINT arduino_commands_user_id_fkey 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE device_registry ADD CONSTRAINT device_registry_user_id_fkey 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE qr_tokens ADD CONSTRAINT qr_tokens_user_id_fkey 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE auth_logs ADD CONSTRAINT auth_logs_user_id_fkey 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE schedule_logs ADD CONSTRAINT schedule_logs_user_id_fkey 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE sms_notifications ADD CONSTRAINT sms_notifications_user_id_fkey 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE rfid_cards ADD CONSTRAINT rfid_cards_user_id_fkey 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE system_logs ADD CONSTRAINT system_logs_user_id_fkey 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Create Indexes
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_patients_user_id ON patients(user_id);
CREATE INDEX idx_patients_nic ON patients(nic);
CREATE INDEX idx_doctors_user_id ON doctors(user_id);
CREATE INDEX idx_doctors_nic ON doctors(nic);
CREATE INDEX idx_patient_doctor_assignments_patient_id ON patient_doctor_assignments(patient_id);
CREATE INDEX idx_patient_doctor_assignments_doctor_id ON patient_doctor_assignments(doctor_id);
CREATE INDEX idx_articles_doctor_id ON articles(doctor_id);
CREATE INDEX idx_articles_created_at ON articles(created_at);
CREATE INDEX idx_pairing_tokens_patient_id ON pairing_tokens(patient_id);
CREATE INDEX idx_pairing_tokens_token ON pairing_tokens(token);
CREATE INDEX idx_session_tokens_user_id ON session_tokens(user_id);
CREATE INDEX idx_session_tokens_token ON session_tokens(token);

-- Create trigger for auto-login timestamp
CREATE OR REPLACE FUNCTION update_user_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER users_update_timestamp
BEFORE UPDATE ON users
FOR EACH ROW
EXECUTE FUNCTION update_user_timestamp();

CREATE TRIGGER patients_update_timestamp
BEFORE UPDATE ON patients
FOR EACH ROW
EXECUTE FUNCTION update_user_timestamp();

CREATE TRIGGER doctors_update_timestamp
BEFORE UPDATE ON doctors
FOR EACH ROW
EXECUTE FUNCTION update_user_timestamp();

CREATE TRIGGER articles_update_timestamp
BEFORE UPDATE ON articles
FOR EACH ROW
EXECUTE FUNCTION update_user_timestamp();
