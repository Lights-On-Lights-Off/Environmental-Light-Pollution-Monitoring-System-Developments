<?php
// Returns the current lux and pollution level for every building.
// Reads from the buildings table which is kept up to date by log_readings.php.
// Falls back to light_logs if needed. Requires login since it is dashboard data.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

requireLogin();

// Primary source: buildings table (always current, updated every 60s by main.js)
$rows = $pdo->query(
    "SELECT id AS building_id, lux, pollution_level, online,
            name, lat, lng, description
     FROM buildings
     ORDER BY id ASC"
)->fetchAll();

echo json_encode(['success' => true, 'readings' => $rows]);
