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