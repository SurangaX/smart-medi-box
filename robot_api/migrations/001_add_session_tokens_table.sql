-- Migration: Add Session Tokens Table
-- Version: 1.0
-- Created: 2026-04-16
-- Purpose: Create the session_tokens table for web authentication tokens

-- Create Session Tokens Table (for web authentication)
CREATE TABLE IF NOT EXISTS session_tokens (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_session_tokens_user_id ON session_tokens(user_id);
CREATE INDEX IF NOT EXISTS idx_session_tokens_token ON session_tokens(token);

-- Log the migration
SELECT 'Session tokens table created successfully' as status;
