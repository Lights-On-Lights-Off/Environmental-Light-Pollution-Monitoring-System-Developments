<?php
// Returns notifications for the logged-in user and lets them mark all as read.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

requireLogin();

$user   = currentUser();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20"
    );
    $stmt->execute([$user['id']]);
    echo json_encode(['success' => true, 'notifications' => $stmt->fetchAll()]);
    exit;
}

if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    if ($action === 'mark_read') {
        $pdo->prepare(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ?"
        )->execute([$user['id']]);
        echo json_encode(['success' => true]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
