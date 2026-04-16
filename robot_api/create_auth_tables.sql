-- Smart Medi Box - Authentication & Device Pairing Tables
-- Created: 2026-04-16
-- These tables are required for the new authentication system

-- Drop existing tables if they exist (for clean setup)
-- DROP TABLE IF EXISTS pairing_tokens CASCADE;
-- DROP TABLE IF EXISTS device_registry CASCADE;
-- DROP TABLE IF EXISTS auth_tokens CASCADE;
-- DROP TABLE IF EXISTS patients CASCADE;
-- DROP TABLE IF EXISTS users CASCADE;

-- Users Table (Enhanced)
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'PATIENT', -- PATIENT, DOCTOR, ADMIN
    name VARCHAR(100),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Authentication Tokens Table
CREATE TABLE IF NOT EXISTS auth_tokens (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Patients Table (linked to Users)
CREATE TABLE IF NOT EXISTS patients (
    id SERIAL PRIMARY KEY,
    user_id INT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    nic VARCHAR(20),
    name VARCHAR(100),
    date_of_birth DATE,
    gender VARCHAR(10),
    blood_type VARCHAR(5),
    age INT,
    phone_number VARCHAR(20),
    transplanted_organ VARCHAR(50),
    transplantation_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Doctors Table (linked to Users)
CREATE TABLE IF NOT EXISTS doctors (
    id SERIAL PRIMARY KEY,
    user_id INT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(100),
    specialization VARCHAR(100),
    hospital VARCHAR(100),
    phone_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Pairing Tokens Table (temporary tokens for device pairing)
CREATE TABLE IF NOT EXISTS pairing_tokens (
    id SERIAL PRIMARY KEY,
    patient_id INT NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Device Registry Table (paired devices)
CREATE TABLE IF NOT EXISTS device_registry (
    id SERIAL PRIMARY KEY,
    device_id VARCHAR(50) UNIQUE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    mac_address VARCHAR(20) UNIQUE NOT NULL,
    device_name VARCHAR(100),
    device_type VARCHAR(50),
    status VARCHAR(20) DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Patient-Doctor Assignments Table
CREATE TABLE IF NOT EXISTS patient_doctor_assignments (
    id SERIAL PRIMARY KEY,
    patient_id INT NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
    doctor_id INT NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,
    notes TEXT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Articles Table (for doctors)
CREATE TABLE IF NOT EXISTS articles (
    id SERIAL PRIMARY KEY,
    doctor_id INT NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    summary TEXT,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_auth_tokens_token ON auth_tokens(token);
CREATE INDEX IF NOT EXISTS idx_auth_tokens_user_id ON auth_tokens(user_id);
CREATE INDEX IF NOT EXISTS idx_pairing_tokens_token ON pairing_tokens(token);
CREATE INDEX IF NOT EXISTS idx_pairing_tokens_patient_id ON pairing_tokens(patient_id);
CREATE INDEX IF NOT EXISTS idx_device_registry_user_id ON device_registry(user_id);
CREATE INDEX IF NOT EXISTS idx_device_registry_mac_address ON device_registry(mac_address);
CREATE INDEX IF NOT EXISTS idx_patients_user_id ON patients(user_id);
CREATE INDEX IF NOT EXISTS idx_doctors_user_id ON doctors(user_id);

-- Enable UUID extension (optional, for future use)
-- CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Insert test data (optional - uncomment to use)
/*
INSERT INTO users (email, password_hash, role, name, phone) 
VALUES ('test@example.com', '$2y$10$test', 'PATIENT', 'Test Patient', '1234567890')
ON CONFLICT (email) DO NOTHING;

INSERT INTO users (email, password_hash, role, name, phone) 
VALUES ('doctor@example.com', '$2y$10$test', 'DOCTOR', 'Test Doctor', '0987654321')
ON CONFLICT (email) DO NOTHING;
*/
