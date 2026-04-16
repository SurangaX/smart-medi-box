-- Migration: Add schedule_date column to schedules table
-- Version: 2.0
-- Created: 2026-04-16
-- Purpose: Add missing schedule_date column for date-based filtering

ALTER TABLE schedules ADD COLUMN IF NOT EXISTS schedule_date DATE NOT NULL DEFAULT CURRENT_DATE;

-- Update existing records to have proper schedule_date based on created_at
UPDATE schedules SET schedule_date = DATE(created_at) WHERE schedule_date = CURRENT_DATE AND created_at IS NOT NULL;

-- Create index for performance on schedule_date queries
CREATE INDEX IF NOT EXISTS idx_schedules_schedule_date ON schedules(schedule_date);

SELECT 'schedule_date column added and indexed' as status;
