-- Migration: move data from device_registry -> devices + device_user_map
-- Run this on the production database after taking a backup.
BEGIN;

-- Create new tables if they don't exist (safe to run multiple times)
CREATE TABLE IF NOT EXISTS devices (
    id SERIAL PRIMARY KEY,
    device_id VARCHAR(50) UNIQUE,
    mac_address VARCHAR(20) UNIQUE NOT NULL,
    device_name VARCHAR(100),
    device_type VARCHAR(50),
    status VARCHAR(20) DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS device_user_map (
    id SERIAL PRIMARY KEY,
    device_id INT NOT NULL REFERENCES devices(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id)
);

-- Insert distinct devices from device_registry into devices
INSERT INTO devices (device_id, mac_address, device_name, device_type, status, created_at, updated_at)
SELECT DISTINCT
       dr.device_id,
       dr.mac_address,
       COALESCE(dr.device_name, 'Smart Medi Box')::VARCHAR,
       COALESCE(dr.device_type, 'SMART_BOX')::VARCHAR,
       COALESCE(dr.status, 'ACTIVE')::VARCHAR,
       dr.created_at,
       dr.updated_at
FROM device_registry dr
WHERE dr.mac_address IS NOT NULL
ON CONFLICT (mac_address) DO NOTHING;

-- Map existing device_registry rows to device_user_map
INSERT INTO device_user_map (device_id, user_id, assigned_at)
SELECT d.id AS device_id, dr.user_id AS user_id, dr.created_at AS assigned_at
FROM device_registry dr
JOIN devices d ON d.mac_address = dr.mac_address
WHERE dr.user_id IS NOT NULL
ON CONFLICT (user_id) DO NOTHING;

-- Optional: keep a backup of the old table before dropping
-- CREATE TABLE device_registry_backup AS TABLE device_registry;

-- If you want to drop the old table uncomment the following (be careful):
-- DROP TABLE device_registry;

COMMIT;

-- After running: verify devices and device_user_map contents.
