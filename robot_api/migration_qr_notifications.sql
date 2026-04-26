-- Smart Medi Box - Database Migration for QR Auth, Notifications, and RFID
-- Run this after the main schema is created

-- Device Sessions Table (for tracking active Arduino sessions)
CREATE TABLE IF NOT EXISTS device_sessions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    device_mac VARCHAR(20) NOT NULL UNIQUE,
    session_token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Device Pairings Table (for new device pairing)
CREATE TABLE IF NOT EXISTS device_pairings (
    id SERIAL PRIMARY KEY,
    device_mac VARCHAR(20) NOT NULL UNIQUE,
    pairing_token VARCHAR(255) NOT NULL UNIQUE,
    device_type VARCHAR(50) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    schedule_id INT REFERENCES schedules(id) ON DELETE SET NULL,
    type VARCHAR(50) NOT NULL, -- MEDICINE_REMINDER, FOOD_REMINDER, BLOOD_CHECK_REMINDER, ALARM_MEDICINE, ALARM_FOOD, ALARM_BLOOD_CHECK
    message TEXT NOT NULL,
    phone VARCHAR(20),
    sms_sent BOOLEAN DEFAULT FALSE,
    sms_sent_at TIMESTAMP,
    app_sent BOOLEAN DEFAULT FALSE,
    app_sent_at TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    is_dismissed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Reminder Schedules Table (for recurring 5-min reminders)
CREATE TABLE IF NOT EXISTS reminder_schedules (
    id SERIAL PRIMARY KEY,
    alarm_id INT NOT NULL REFERENCES alarm_logs(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    interval_minutes INT DEFAULT 5,
    next_reminder_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User RFID Tags Table
CREATE TABLE IF NOT EXISTS user_rfid_tags (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    rfid_tag VARCHAR(50) NOT NULL UNIQUE,
    tag_name VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- RFID Access Logs Table
CREATE TABLE IF NOT EXISTS rfid_access_logs (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    rfid_tag VARCHAR(50) NOT NULL,
    access_type VARCHAR(20), -- UNLOCK, LOCK, AUTHENTICATE
    authorized BOOLEAN,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create Indexes for Performance
CREATE INDEX IF NOT EXISTS idx_device_sessions_user ON device_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_device_sessions_mac ON device_sessions(device_mac);
CREATE INDEX IF NOT EXISTS idx_device_sessions_token ON device_sessions(session_token);

CREATE INDEX IF NOT EXISTS idx_device_pairings_mac ON device_pairings(device_mac);
CREATE INDEX IF NOT EXISTS idx_device_pairings_token ON device_pairings(pairing_token);

CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_schedule ON notifications(schedule_id);
CREATE INDEX IF NOT EXISTS idx_notifications_type ON notifications(type);
CREATE INDEX IF NOT EXISTS idx_notifications_sms ON notifications(sms_sent);

CREATE INDEX IF NOT EXISTS idx_reminder_schedules_alarm ON reminder_schedules(alarm_id);
CREATE INDEX IF NOT EXISTS idx_reminder_schedules_user ON reminder_schedules(user_id);
CREATE INDEX IF NOT EXISTS idx_reminder_schedules_active ON reminder_schedules(is_active);

CREATE INDEX IF NOT EXISTS idx_rfid_tags_user ON user_rfid_tags(user_id);
CREATE INDEX IF NOT EXISTS idx_rfid_tags_tag ON user_rfid_tags(rfid_tag);

CREATE INDEX IF NOT EXISTS idx_rfid_logs_user ON rfid_access_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_rfid_logs_timestamp ON rfid_access_logs(timestamp);

-- Update existing tables if needed
ALTER TABLE alarm_logs ADD COLUMN IF NOT EXISTS forced_open BOOLEAN DEFAULT FALSE;

-- Create function to auto-update reminder schedules
CREATE OR REPLACE FUNCTION update_reminder_schedule()
RETURNS TRIGGER AS $$
BEGIN
    -- When alarm is dismissed, stop reminders
    IF NEW.status = 'DISMISSED' AND OLD.status = 'TRIGGERED' THEN
        UPDATE reminder_schedules SET is_active = FALSE WHERE alarm_id = NEW.id;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create trigger for automatic reminder deactivation
DROP TRIGGER IF EXISTS alarm_reminder_trigger ON alarm_logs;
CREATE TRIGGER alarm_reminder_trigger
AFTER UPDATE ON alarm_logs
FOR EACH ROW
EXECUTE FUNCTION update_reminder_schedule();

