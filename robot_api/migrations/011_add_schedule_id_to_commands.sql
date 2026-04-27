-- Migration: Add schedule_id to arduino_commands
-- Purpose: Track which schedule a command belongs to, allowing for cleanup of old triggers

DO $$ 
BEGIN 
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'arduino_commands' AND column_name = 'schedule_id') THEN
        ALTER TABLE arduino_commands ADD COLUMN schedule_id INT REFERENCES schedules(id) ON DELETE CASCADE;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_arduino_commands_schedule_id ON arduino_commands(schedule_id);
