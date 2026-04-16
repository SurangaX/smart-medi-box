-- Smart Medi Box - PostgreSQL Database Schema
-- Version: 1.0.0
-- Created: 2026-04-13

-- Create database first (if needed)
-- CREATE DATABASE smart_medi_box WITH ENCODING 'UTF8';

-- Create ENUM types
CREATE TYPE schedule_type AS ENUM ('MEDICINE', 'FOOD', 'BLOOD_CHECK');
CREATE TYPE schedule_status AS ENUM ('ACTIVE', 'DELETED', 'COMPLETED');
CREATE TYPE user_status AS ENUM ('ACTIVE', 'INACTIVE', 'SUSPENDED');
CREATE TYPE command_status AS ENUM ('PENDING', 'SENT', 'EXECUTED', 'FAILED');
CREATE TYPE device_status AS ENUM ('ACTIVE', 'OFFLINE', 'ERROR');
CREATE TYPE alarm_status AS ENUM ('TRIGGERED', 'ACKNOWLEDGED', 'DISMISSED');
CREATE TYPE auth_status AS ENUM ('SUCCESS', 'FAILED', 'REGISTERED');

-- Users Table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    user_id VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    age INT NOT NULL,
    phone VARCHAR(20) UNIQUE,
    mac_address VARCHAR(20) UNIQUE NOT NULL,
    status user_status DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- Schedules Table
CREATE TABLE schedules (
    id SERIAL PRIMARY KEY,
    schedule_id VARCHAR(60) UNIQUE NOT NULL,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type schedule_type NOT NULL,
    schedule_date DATE NOT NULL,
    hour INT NOT NULL CHECK (hour >= 0 AND hour <= 23),
    minute INT NOT NULL CHECK (minute >= 0 AND minute <= 59),
    description TEXT,
    is_completed BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    status schedule_status DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- Temperature Logs Table
CREATE TABLE temperature_logs (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    internal_temp DECIMAL(5,2),
    external_humidity DECIMAL(5,2),
    target_temp DECIMAL(5,2),
    cooling_status VARCHAR(20),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Temperature Settings Table
CREATE TABLE temperature_settings (
    id SERIAL PRIMARY KEY,
    user_id INT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    target_temp DECIMAL(5,2) DEFAULT 4.0,
    min_temp DECIMAL(5,2) DEFAULT 2.0,
    max_temp DECIMAL(5,2) DEFAULT 8.0,
    cooling_mode VARCHAR(20) DEFAULT 'AUTO',
    hysteresis DECIMAL(5,2) DEFAULT 0.5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Alarm Logs Table
CREATE TABLE alarm_logs (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    schedule_id INT REFERENCES schedules(id) ON DELETE SET NULL,
    triggered_at TIMESTAMP NOT NULL,
    dismissed_at TIMESTAMP NULL,
    duration_seconds INT,
    door_opened BOOLEAN DEFAULT FALSE,
    sms_sent BOOLEAN DEFAULT FALSE,
    status alarm_status DEFAULT 'TRIGGERED'
);

-- Arduino Commands Table
CREATE TABLE arduino_commands (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    command VARCHAR(255) NOT NULL,
    response TEXT,
    status command_status DEFAULT 'PENDING',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    executed_at TIMESTAMP NULL
);

-- Device Registry Table
CREATE TABLE device_registry (
    id SERIAL PRIMARY KEY,
    device_id VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    device_name VARCHAR(100),
    device_type VARCHAR(50),
    firmware_version VARCHAR(20),
    mac_address VARCHAR(20),
    status device_status DEFAULT 'ACTIVE',
    last_sync TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- QR Tokens Table
CREATE TABLE qr_tokens (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(255) UNIQUE NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Authentication Logs Table
CREATE TABLE auth_logs (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    mac_address VARCHAR(20),
    status auth_status NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Schedule Logs Table
CREATE TABLE schedule_logs (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    schedule_id INT REFERENCES schedules(id) ON DELETE SET NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- SMS Notifications Table
CREATE TABLE sms_notifications (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'PENDING',
    sent_at TIMESTAMP NULL,
    response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- RFID Cards Table
CREATE TABLE rfid_cards (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    card_uid VARCHAR(50) UNIQUE NOT NULL,
    card_name VARCHAR(100),
    status VARCHAR(20) DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- System Logs Table
CREATE TABLE system_logs (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    event_type VARCHAR(100) NOT NULL,
    level VARCHAR(20) NOT NULL,
    message TEXT,
    details JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- CREATE INDEXES
CREATE INDEX idx_users_mac_address ON users(mac_address);
CREATE INDEX idx_users_user_id ON users(user_id);
CREATE INDEX idx_schedules_user_id ON schedules(user_id);
CREATE INDEX idx_schedules_created_at ON schedules(created_at);
CREATE INDEX idx_temperature_logs_user_id ON temperature_logs(user_id);
CREATE INDEX idx_temperature_logs_timestamp ON temperature_logs(timestamp);
CREATE INDEX idx_alarm_logs_user_id ON alarm_logs(user_id);
CREATE INDEX idx_arduino_commands_user_id ON arduino_commands(user_id);
CREATE INDEX idx_device_registry_user_id ON device_registry(user_id);
CREATE INDEX idx_device_registry_mac ON device_registry(mac_address);
CREATE INDEX idx_auth_logs_user_id ON auth_logs(user_id);
CREATE INDEX idx_auth_logs_created_at ON auth_logs(created_at);
CREATE INDEX idx_schedule_logs_user_id ON schedule_logs(user_id);
CREATE INDEX idx_sms_notifications_user_id ON sms_notifications(user_id);
CREATE INDEX idx_rfid_cards_user_id ON rfid_cards(user_id);
CREATE INDEX idx_system_logs_user_id ON system_logs(user_id);

-- CREATE FUNCTION for auto temperature_settings on user insert
CREATE OR REPLACE FUNCTION create_temperature_settings()
RETURNS TRIGGER AS $$
BEGIN
    INSERT INTO temperature_settings (user_id, target_temp, min_temp, max_temp)
    VALUES (NEW.id, 4.0, 2.0, 8.0);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- CREATE TRIGGER for temperature_settings
CREATE TRIGGER trigger_create_temperature_settings
AFTER INSERT ON users
FOR EACH ROW
EXECUTE FUNCTION create_temperature_settings();

-- Sample data for testing
INSERT INTO users (user_id, name, age, phone, mac_address, status)
VALUES ('USER_20260413_A1B2C3', 'John Doe', 45, '+94777154321', 'AA:BB:CC:DD:EE:FF', 'ACTIVE');

-- Verify trigger created temperature_settings automatically
-- SELECT * FROM temperature_settings WHERE user_id = (SELECT id FROM users WHERE user_id = 'USER_20260413_A1B2C3');
