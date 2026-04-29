<?php
// Receives sensor readings directly from the ESP32 device via HTTP POST.
// Accepts application/x-www-form-urlencoded (ESP32 default format).
// Saves to light_logs and updates the buildings table.
// Does NOT require login - uses a device token instead.

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// ─── Simple device token check ────────────────────────────────────────────────
// Must match the token in the ESP32 sketch
define('IOT_TOKEN', 'elpms_iot_2026');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Validate token
$token = $_POST['token'] ?? '';
if ($token !== IOT_TOKEN) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ─── Validate required fields ─────────────────────────────────────────────────
$buildingId = isset($_POST['building_id']) ? (int)$_POST['building_id']  : 0;
$lux        = isset($_POST['lux'])         ? (float)$_POST['lux']        : null;
$deviceId   = isset($_POST['device_id'])   ? trim($_POST['device_id'])   : '';

if (!$buildingId || $lux === null || !$deviceId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'building_id, lux, and device_id are required']);
    exit;
}

if ($lux < 0 || $lux > 100000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid lux value']);
    exit;
}

// ─── Classify pollution level ─────────────────────────────────────────────────
if ($lux <= 50) {
    $pollutionLevel = 'low';
} elseif ($lux <= 150) {
    $pollutionLevel = 'moderate';
} else {
    $pollutionLevel = 'high';
}

// ─── Determine time of day (Philippine Standard Time) ─────────────────────────
// db.php already sets timezone to Asia/Manila
$hour      = (int)date('H');
$timeOfDay = ($hour >= 6 && $hour < 18) ? 'day' : 'night';

// ─── Save to database ─────────────────────────────────────────────────────────
try {
    // Insert into light_logs
    $pdo->prepare(
        "INSERT INTO light_logs (building_id, device_id, lux, pollution_level, time_of_day, online)
         VALUES (?, ?, ?, ?, ?, 1)"
    )->execute([$buildingId, $deviceId, $lux, $pollutionLevel, $timeOfDay]);

    // Update buildings table so dashboard reflects latest reading
    $pdo->prepare(
        "UPDATE buildings SET lux = ?, pollution_level = ?, online = 1 WHERE id = ?"
    )->execute([$lux, $pollutionLevel, $buildingId]);

    echo json_encode([
        'success' => true,
        'message' => 'Reading saved',
        'data'    => [
            'building_id'     => $buildingId,
            'device_id'       => $deviceId,
            'lux'             => $lux,
            'pollution_level' => $pollutionLevel,
            'time_of_day'     => $timeOfDay,
            'timestamp'       => date('Y-m-d H:i:s'),
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
