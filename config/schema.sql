-- LPMS Database Schema
-- Run this once to set up all required tables.

CREATE DATABASE IF NOT EXISTS lpms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lpms_db;

-- Stores all registered users including their role (user, manager, admin)
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120)  NOT NULL,
    email      VARCHAR(180)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    org        VARCHAR(120)  DEFAULT NULL,
    role       ENUM('user','manager','admin') NOT NULL DEFAULT 'user',
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Campus buildings being monitored for light pollution
CREATE TABLE IF NOT EXISTS buildings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(160)  NOT NULL,
    lat             DECIMAL(10,8) NOT NULL,
    lng             DECIMAL(11,8) NOT NULL,
    pollution_level ENUM('low','moderate','high') NOT NULL DEFAULT 'moderate',
    description     TEXT          DEFAULT NULL,
    lux             DECIMAL(7,2)  NOT NULL DEFAULT 50.00,
    online          TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Light sensor readings recorded every minute by the simulation
CREATE TABLE IF NOT EXISTS light_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    building_id     INT           NOT NULL,
    lux             DECIMAL(7,2)  NOT NULL,
    pollution_level ENUM('low','moderate','high') NOT NULL,
    online          TINYINT(1)    NOT NULL DEFAULT 1,
    recorded_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE
);

-- Data requests submitted by users and reviewed by managers
CREATE TABLE IF NOT EXISTS data_requests (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT          NOT NULL,
    building_id    INT          DEFAULT NULL,
    data_type      VARCHAR(80)  DEFAULT NULL,
    location       VARCHAR(160) DEFAULT NULL,
    start_date     DATE         DEFAULT NULL,
    end_date       DATE         DEFAULT NULL,
    purpose        TEXT         DEFAULT NULL,
    notes          TEXT         DEFAULT NULL,
    organization   VARCHAR(120) DEFAULT NULL,
    status         ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
    deleted        TINYINT(1)   NOT NULL DEFAULT 0,
    deleted_at     DATETIME     DEFAULT NULL,
    reviewed_by    INT          DEFAULT NULL,
    reviewed_at    DATETIME     DEFAULT NULL,
    submitted_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Manager actions logged for admin visibility
CREATE TABLE IF NOT EXISTS activity_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    action     VARCHAR(80)  NOT NULL,
    detail     TEXT         DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications sent to users when their request status changes
CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    message    TEXT         NOT NULL,
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Default accounts. Passwords: admin123 / manager1 / user1234
INSERT IGNORE INTO users (name, email, password, role) VALUES
('NBSC Admin',   'admin@example.com',   '$2y$12$Cheg4CcXdyGQ0iLgOX35SOw6naoBZGFg1TxH2sw0uNEz8bz3zP3oi', 'admin'),
('NBSC Manager', 'manager@example.com', '$2y$12$uPuPo9dvpGA7StFuCdp0oO.cKD0ReQqUNCe781xnGFcaMyr2Pj0me', 'manager'),
('Default User', 'user@example.com',    '$2y$12$Z1M.O5.2HrOjVWlOtF4rJej8HZLWQAL9dVWd.Q1rpMIv/qYk0Ntyi', 'user');

-- Default campus buildings
INSERT IGNORE INTO buildings (id, name, lat, lng, pollution_level, description, lux) VALUES
(1, 'SWDC Building',                          8.36030910, 124.86777742, 'moderate', 'Main administrative offices',              55.00),
(2, 'Northern Bukidnon State College Covered Court', 8.36012237, 124.86894170, 'moderate', 'Sports and events facility',               62.00),
(3, 'NBSC Library',                           8.35926403, 124.86789449, 'low',      'Main library and study center',             18.00),
(4, 'NBSC Clinic',                            8.35915760, 124.86817955, 'moderate', 'Medical services and health center',        47.00),
(5, 'BSBA Building',                          8.35909641, 124.86842964, 'high',     'Business and administration classrooms',    130.00),
(6, 'ICS Laboratory',                         8.35922146, 124.86905085, 'moderate', 'Computer science and IT laboratory',        70.00),
(7, 'Cafeteria',                              8.35890000, 124.86820000, 'moderate', 'Student dining facility',                   58.00);
