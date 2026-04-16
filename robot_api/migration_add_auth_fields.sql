-- Migration: Add authentication fields to users table
-- Date: 2026-04-16
-- Purpose: Support email/password-based authentication with role-based access

-- Add missing columns to users table
ALTER TABLE users
ADD COLUMN IF NOT EXISTS email VARCHAR(100) UNIQUE,
ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255),
ADD COLUMN IF NOT EXISTS nic VARCHAR(20) UNIQUE,
ADD COLUMN IF NOT EXISTS dob DATE,
ADD COLUMN IF NOT EXISTS license_number VARCHAR(50) UNIQUE,
ADD COLUMN IF NOT EXISTS specialty VARCHAR(100),
ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'PATIENT';

-- Create auth_tokens table if it doesn't exist
CREATE TABLE IF NOT EXISTS auth_tokens (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create index for faster token lookups
CREATE INDEX IF NOT EXISTS idx_auth_tokens_user_id ON auth_tokens(user_id);
CREATE INDEX IF NOT EXISTS idx_auth_tokens_token ON auth_tokens(token);

-- Add indexes for new columns
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_nic ON users(nic);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- Insert sample test users (optional - comment out if not needed)
-- INSERT INTO users (user_id, name, email, password_hash, nic, age, phone, mac_address, role, status)
-- VALUES 
--   ('USER_20260416_TEST01', 'Test Patient', 'patient@test.com', '$2y$10$...', '123456789V', 30, '+94777000001', '00:00:00:00:00:01', 'PATIENT', 'ACTIVE'),
--   ('USER_20260416_TEST02', 'Test Doctor', 'doctor@test.com', '$2y$10$...', '987654321V', 40, '+94777000002', '00:00:00:00:00:02', 'DOCTOR', 'ACTIVE');

-- Verify schema
-- SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'users' ORDER BY ordinal_position;
