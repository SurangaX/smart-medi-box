-- Migration: Add articles system and profile photo
-- Version: 3.0
-- Created: 2026-04-16
-- Purpose: Add articles table for doctors to share health information

-- Add columns to users table for authentication and profile
ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(100);
ALTER TABLE users ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255);
ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'patient';
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo TEXT;
ALTER TABLE users ALTER COLUMN age DROP NOT NULL;

-- Create Articles Table (For doctors to share health tips, news, etc.)
CREATE TABLE IF NOT EXISTS articles (
    id SERIAL PRIMARY KEY,
    article_id VARCHAR(60) UNIQUE NOT NULL,
    doctor_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    cover_image TEXT,
    status VARCHAR(20) DEFAULT 'PUBLISHED',
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- Create indexes for articles
CREATE INDEX IF NOT EXISTS idx_articles_doctor_id ON articles(doctor_id);
CREATE INDEX IF NOT EXISTS idx_articles_created_at ON articles(created_at);
CREATE INDEX IF NOT EXISTS idx_articles_status ON articles(status);

SELECT 'Articles system and profile photo support added' as status;
