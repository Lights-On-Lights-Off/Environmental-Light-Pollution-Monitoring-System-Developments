<?php
// Handles all data request operations.
// Users submit and delete their own requests.
// Managers approve, deny, soft-delete, and restore requests.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$user   = currentUser();

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        if (in_array($user['role'], ['manager', 'admin'], true)) {
            $status = $_GET['status'] ?? 'all';
            if ($status !== 'all') {
                $stmt = $pdo->prepare(
                    "SELECT dr.*, u.name AS user_name, u.email AS user_email
                     FROM data_requests dr
                     JOIN users u ON u.id = dr.user_id
                     WHERE dr.status = ? AND dr.deleted = 0
                     ORDER BY dr.submitted_at DESC"
                );
                $stmt->execute([$status]);
            } else {
                $stmt = $pdo->query(
                    "SELECT dr.*, u.name AS user_name, u.email AS user_email
                     FROM data_requests dr
                     JOIN users u ON u.id = dr.user_id
                     WHERE dr.deleted = 0
                     ORDER BY dr.submitted_at DESC"
                );
            }
        } else {
            $stmt = $pdo->prepare(
                "SELECT dr.*, u.name AS user_name, u.email AS user_email
                 FROM data_requests dr
                 JOIN users u ON u.id = dr.user_id
                 WHERE dr.user_id = ? AND dr.deleted = 0
                 ORDER BY dr.submitted_at DESC"
            );
            $stmt->execute([$user['id']]);
        }
        echo json_encode(['success' => true, 'requests' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'recycle' && in_array($user['role'], ['manager', 'admin'], true)) {
        $stmt = $pdo->query(
            "SELECT dr.*, u.name AS user_name, u.email AS user_email
             FROM data_requests dr
             JOIN users u ON u.id = dr.user_id
             WHERE dr.deleted = 1
             ORDER BY dr.deleted_at DESC"
        );
        echo json_encode(['success' => true, 'requests' => $stmt->fetchAll()]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

if ($method === 'POST') {
    $action = $body['action'] ?? 'submit';

    if ($action === 'submit') {
        $location = trim($body['location'] ?? '');
        $dataType = trim($body['data_type'] ?? '');
        $purpose  = trim($body['purpose'] ?? '');
        $start    = $body['start_date'] ?? null;
        $end      = $body['end_date']   ?? null;
        $notes    = trim($body['notes'] ?? '');
        $org      = trim($body['organization'] ?? '');

        if (!$location || !$dataType || !$purpose || !$start || !$end) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Required fields missing']);
            exit;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO data_requests
             (user_id, location, data_type, purpose, start_date, end_date, notes, organization, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );
        $stmt->execute([$user['id'], $location, $dataType, $purpose, $start, $end, $notes, $org]);
        $newId = $pdo->lastInsertId();

        logActivity($pdo, 'submitted_request',
            "Submitted data request #$newId for \"$location\" ($dataType)");

        echo json_encode(['success' => true, 'id' => $newId]);
        exit;
    }

    if ($action === 'approve' || $action === 'deny') {
        requireRole('manager', 'admin');
        $id = (int)($body['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'id required']); exit; }

        $status = $action === 'approve' ? 'approved' : 'denied';
        $pdo->prepare(
            "UPDATE data_requests SET status=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?"
        )->execute([$status, $user['id'], $id]);

        $req = $pdo->prepare("SELECT dr.*, u.id AS uid, u.name AS requester_name FROM data_requests dr JOIN users u ON u.id=dr.user_id WHERE dr.id=?");
        $req->execute([$id]);
        $req = $req->fetch();

        if ($req) {
            $msg = "Your data request #{$id} has been {$status} by the manager.";
            $pdo->prepare(
                "INSERT INTO notifications (user_id, message) VALUES (?, ?)"
            )->execute([$req['uid'], $msg]);

            logActivity($pdo, "{$action}d_request",
                "Request #{$id} ({$req['data_type']} at {$req['location']}) {$status} for {$req['requester_name']}");
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'id required']); exit; }

        if (in_array($user['role'], ['manager', 'admin'], true)) {
            $pdo->prepare(
                "UPDATE data_requests SET deleted=1, deleted_at=NOW() WHERE id=?"
            )->execute([$id]);
            logActivity($pdo, 'moved_request_to_bin',
                "Moved request #$id to recycle bin");
        } else {
            $pdo->prepare(
                "UPDATE data_requests SET deleted=1, deleted_at=NOW() WHERE id=? AND user_id=?"
            )->execute([$id, $user['id']]);
            logActivity($pdo, 'deleted_own_request',
                "Deleted own request #$id");
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'restore') {
        requireRole('manager', 'admin');
        $id = (int)($body['id'] ?? 0);
        $pdo->prepare("UPDATE data_requests SET deleted=0, deleted_at=NULL WHERE id=?")->execute([$id]);
        logActivity($pdo, 'restored_request', "Restored request #$id from recycle bin");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'permanent_delete') {
        requireRole('manager', 'admin');
        $id = (int)($body['id'] ?? 0);
        $row = $pdo->prepare("SELECT location, data_type FROM data_requests WHERE id=?");
        $row->execute([$id]);
        $req = $row->fetch();
        $pdo->prepare("DELETE FROM data_requests WHERE id=?")->execute([$id]);
        $detail = $req ? "Permanently deleted request #$id ({$req['data_type']} at {$req['location']})" : "Permanently deleted request #$id";
        logActivity($pdo, 'permanent_deleted_request', $detail);
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
