<?php
// Handles all building CRUD operations: list, add, edit, delete.
// Expects JSON body for POST/PUT/DELETE. Returns JSON.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

requireRole('manager', 'admin');

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET') {
    $rows = $pdo->query("SELECT * FROM buildings ORDER BY id ASC")->fetchAll();
    echo json_encode(['success' => true, 'buildings' => $rows]);
    exit;
}

if ($method === 'POST') {
    $name  = trim($body['name'] ?? '');
    $lat   = isset($body['lat'])  ? (float)$body['lat']  : null;
    $lng   = isset($body['lng'])  ? (float)$body['lng']  : null;
    $level = $body['pollution_level'] ?? 'moderate';
    $desc  = trim($body['description'] ?? '');

    if (!$name || $lat === null || $lng === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Name, lat, and lng are required']);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO buildings (name, lat, lng, pollution_level, description) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$name, $lat, $lng, $level, $desc]);
    $newId = $pdo->lastInsertId();

    logActivity($pdo, 'added_building', "Added building \"$name\" (lat: $lat, lng: $lng, level: $level)");

    echo json_encode(['success' => true, 'id' => $newId]);
    exit;
}

if ($method === 'PUT') {
    $id    = (int)($body['id'] ?? 0);
    $name  = trim($body['name'] ?? '');
    $lat   = isset($body['lat'])  ? (float)$body['lat']  : null;
    $lng   = isset($body['lng'])  ? (float)$body['lng']  : null;
    $level = $body['pollution_level'] ?? 'moderate';
    $desc  = trim($body['description'] ?? '');

    if (!$id || !$name || $lat === null || $lng === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'id, name, lat, and lng are required']);
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE buildings SET name=?, lat=?, lng=?, pollution_level=?, description=? WHERE id=?"
    );
    $stmt->execute([$name, $lat, $lng, $level, $desc, $id]);

    logActivity($pdo, 'edited_building', "Edited building \"$name\" (id: $id, level: $level)");

    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'DELETE') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'id is required']);
        exit;
    }

    $row = $pdo->prepare("SELECT name FROM buildings WHERE id = ?");
    $row->execute([$id]);
    $building = $row->fetch();

    $pdo->prepare("DELETE FROM buildings WHERE id = ?")->execute([$id]);

    if ($building) {
        logActivity($pdo, 'deleted_building', "Deleted building \"{$building['name']}\" (id: $id)");
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
