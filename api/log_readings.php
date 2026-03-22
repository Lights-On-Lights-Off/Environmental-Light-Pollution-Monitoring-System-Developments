<?php
// Receives a JSON batch of sensor readings from main.js and saves them to light_logs.
// Also updates the buildings table so all other pages reflect the latest levels.
// Called automatically by the frontend every 60 seconds.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

if (empty($body['entries']) || !is_array($body['entries'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No entries provided']);
    exit;
}

$insertLog = $pdo->prepare(
    "INSERT INTO light_logs (building_id, lux, pollution_level, online) VALUES (?, ?, ?, ?)"
);

// Also update the buildings table so manager and other pages see current levels
$updateBuilding = $pdo->prepare(
    "UPDATE buildings SET lux = ?, pollution_level = ?, online = ? WHERE id = ?"
);

// Keep only the latest entry per building for the buildings table update
$latest = [];
foreach ($body['entries'] as $entry) {
    $latest[(int)$entry['building_id']] = $entry;
}

$pdo->beginTransaction();
try {
    foreach ($body['entries'] as $entry) {
        $insertLog->execute([
            (int)$entry['building_id'],
            (float)$entry['lux'],
            $entry['pollution_level'],
            (int)$entry['online'],
        ]);
    }

    foreach ($latest as $id => $entry) {
        $updateBuilding->execute([
            (float)$entry['lux'],
            $entry['pollution_level'],
            (int)$entry['online'],
            $id,
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'saved' => count($body['entries'])]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
