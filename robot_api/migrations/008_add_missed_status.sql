-- Add 'MISSED' to schedule_status and alarm_status enums
-- PostgreSQL requires specific syntax for adding to an ENUM type

-- Check if 'MISSED' exists in schedule_status before adding
DO $$ 
BEGIN 
    IF NOT EXISTS (SELECT 1 FROM pg_type t JOIN pg_enum e ON t.oid = e.enumtypid WHERE t.typname = 'schedule_status' AND e.enumlabel = 'MISSED') THEN
        ALTER TYPE schedule_status ADD VALUE 'MISSED';
    END IF;
END $$;

-- Check if 'MISSED' exists in alarm_status before adding
DO $$ 
BEGIN 
    IF NOT EXISTS (SELECT 1 FROM pg_type t JOIN pg_enum e ON t.oid = e.enumtypid WHERE t.typname = 'alarm_status' AND e.enumlabel = 'MISSED') THEN
        ALTER TYPE alarm_status ADD VALUE 'MISSED';
    END IF;
END $$;
