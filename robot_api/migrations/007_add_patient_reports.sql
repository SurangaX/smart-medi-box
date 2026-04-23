-- Migration: Create patient reports table
-- Version: 7.0
-- Created: 2026-04-23

CREATE TABLE IF NOT EXISTS patient_reports (
    id SERIAL PRIMARY KEY,
    patient_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    doctor_id INT NOT NULL REFERENCES users(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    notes TEXT,
    file_data BYTEA,
    file_mime VARCHAR(100),
    file_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add index for faster lookups
CREATE INDEX IF NOT EXISTS idx_patient_reports_patient ON patient_reports(patient_id);
CREATE INDEX IF NOT EXISTS idx_patient_reports_doctor ON patient_reports(doctor_id);

SELECT 'Table patient_reports created successfully' as status;
