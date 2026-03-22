<?php
// Returns historical light log data for a specific building and date range.
// Used by the user dashboard to generate the CSV download for approved requests.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

requireLogin();

$buildingId = (int)($_GET['building_id'] ?? 0);
$startDate  = $_GET['start'] ?? null;
$endDate    = $_GET['end']   ?? null;

if (!$buildingId || !$startDate || !$endDate) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'building_id, start, and end are required']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT ll.*, b.name AS building_name, b.lat, b.lng
     FROM light_logs ll
     JOIN buildings b ON b.id = ll.building_id
     WHERE ll.building_id = ?
       AND ll.recorded_at BETWEEN ? AND ?
     ORDER BY ll.recorded_at ASC
     LIMIT 500"
);
$stmt->execute([$buildingId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);

echo json_encode(['success' => true, 'logs' => $stmt->fetchAll()]);
