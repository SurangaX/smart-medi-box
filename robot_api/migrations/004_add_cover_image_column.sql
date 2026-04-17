-- Migration: Add cover_image column to articles
-- Version: 4.0
-- Created: 2026-04-17
-- Purpose: Ensure `cover_image` column exists on `articles` table

ALTER TABLE articles
  ADD COLUMN IF NOT EXISTS cover_image TEXT;

SELECT 'Added cover_image column to articles (if not exists)' as status;
