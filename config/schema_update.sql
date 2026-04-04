-- Add soft-delete support to data_requests.
-- Run this if you already created the tables from schema.sql.

ALTER TABLE data_requests
    ADD COLUMN deleted    TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN deleted_at DATETIME   DEFAULT NULL AFTER deleted,
    ADD COLUMN notes      TEXT       DEFAULT NULL AFTER purpose,
    ADD COLUMN organization VARCHAR(120) DEFAULT NULL AFTER notes;

ALTER TABLE activity_log
    ADD COLUMN page VARCHAR(255) DEFAULT NULL AFTER detail;

ALTER TABLE activity_log
    ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL AFTER page;

ALTER TABLE activity_log
    ADD COLUMN browser VARCHAR(45) DEFAULT NULL AFTER ip_address;