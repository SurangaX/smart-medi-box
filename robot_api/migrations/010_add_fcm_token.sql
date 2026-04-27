-- Migration: Add fcm_token to users table
-- Adds a new column to store the native Firebase Cloud Messaging (FCM) registration token from the native Android app.

ALTER TABLE users ADD COLUMN IF NOT EXISTS fcm_token VARCHAR(255);

COMMENT ON COLUMN users.fcm_token IS 'Native FCM registration token for direct-to-device push notifications.';
