-- Add missing columns that may not exist yet.
-- Run this if you already created the tables from schema.sql.

-- Check and add missing columns to data_requests
ALTER TABLE data_requests 
ADD COLUMN IF NOT EXISTS deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
ADD COLUMN IF NOT EXISTS deleted_at DATETIME DEFAULT NULL AFTER deleted;

-- Check and add missing columns to activity_log  
ALTER TABLE activity_log
ADD COLUMN IF NOT EXISTS page VARCHAR(255) DEFAULT NULL AFTER detail,
ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) DEFAULT NULL AFTER page,
ADD COLUMN IF NOT EXISTS browser VARCHAR(45) DEFAULT NULL AFTER ip_address;

-- IoT Integration: add device and time_of_day tracking to light_logs
ALTER TABLE light_logs
ADD COLUMN IF NOT EXISTS device_id VARCHAR(50) DEFAULT NULL AFTER building_id,
ADD COLUMN IF NOT EXISTS time_of_day ENUM('day','night') NOT NULL DEFAULT 'day' AFTER pollution_level;

-- Update existing records based on their recorded_at time
-- Day: 6:00 AM to 5:59 PM | Night: 6:00 PM to 5:59 AM
UPDATE light_logs
SET time_of_day = CASE
    WHEN HOUR(recorded_at) >= 6 AND HOUR(recorded_at) < 18 THEN 'day'
    ELSE 'night'
END;