-- Migration: Add expo_push_token to users table
-- Description: Stores the Expo push notification token for Android/iOS devices.

ALTER TABLE users ADD COLUMN IF NOT EXISTS expo_push_token VARCHAR(255);

-- Create an index for faster lookup when sending notifications
CREATE INDEX IF NOT EXISTS idx_users_expo_push_token ON users(expo_push_token);
