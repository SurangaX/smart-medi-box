-- Migration: Store cover image binary and metadata
-- Version: 5.0
-- Created: 2026-04-17

ALTER TABLE articles
  ADD COLUMN IF NOT EXISTS cover_image_data BYTEA;

ALTER TABLE articles
  ADD COLUMN IF NOT EXISTS cover_image_mime VARCHAR(100);

ALTER TABLE articles
  ADD COLUMN IF NOT EXISTS cover_image_filename VARCHAR(255);

SELECT 'Added cover_image_data, cover_image_mime, cover_image_filename' as status;
