<?php
// Provides activity log and user management endpoints used by the admin dashboard.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

requireRole('admin');

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'users';

    if ($action === 'users') {
        $role = $_GET['role'] ?? 'all';
        if ($role !== 'all') {
            $stmt = $pdo->prepare(
                "SELECT id, name, email, org, role, created_at FROM users WHERE role = ? ORDER BY created_at DESC"
            );
            $stmt->execute([$role]);
        } else {
            $stmt = $pdo->query(
                "SELECT id, name, email, org, role, created_at FROM users WHERE role != 'admin' ORDER BY created_at DESC"
            );
        }
        echo json_encode(['success' => true, 'users' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'activity') {
        $stmt = $pdo->query(
            "SELECT al.*, u.name AS actor_name, u.role AS actor_role
             FROM activity_log al
             JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC
             LIMIT 50"
        );
        echo json_encode(['success' => true, 'log' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'activity_export') {
        $stmt = $pdo->query(
            "SELECT al.created_at, u.name AS actor_name, u.role AS actor_role,
                    al.action, al.detail, al.page, al.ip_address, al.browser
             FROM activity_log al
             JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC"
        );
        echo json_encode(['success' => true, 'log' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'stats') {
        $totalUsers    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
        $totalManagers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='manager'")->fetchColumn();
        $totalRequests = $pdo->query("SELECT COUNT(*) FROM data_requests WHERE deleted=0")->fetchColumn();
        $pending       = $pdo->query("SELECT COUNT(*) FROM data_requests WHERE status='pending' AND deleted=0")->fetchColumn();

        echo json_encode([
            'success'        => true,
            'total_users'    => (int)$totalUsers,
            'total_managers' => (int)$totalManagers,
            'total_requests' => (int)$totalRequests,
            'pending'        => (int)$pending,
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

if ($method === 'POST') {
    $action = $body['action'] ?? '';

    if ($action === 'add_user') {
        $name     = trim($body['name'] ?? '');
        $email    = strtolower(trim($body['email'] ?? ''));
        $password = $body['password'] ?? '';
        $role     = $body['role'] ?? 'user';

        if (!$name || !$email || strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Name, email, and a 6+ char password are required']);
            exit;
        }

        $exists = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $exists->execute([$email]);
        if ($exists->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Email already in use']);
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare(
            "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)"
        )->execute([$name, $email, $hash, $role]);

        logActivity($pdo, 'added_user', "Created account for \"$name\" ($email) with role: $role");

        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    if ($action === 'change_role') {
        $id   = (int)($body['id'] ?? 0);
        $role = $body['role'] ?? '';
        if (!$id || !in_array($role, ['user', 'manager'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid id and role required']);
            exit;
        }
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ? AND role != 'admin'")->execute([$role, $id]);

        $changed = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $changed->execute([$id]);
        $changedUser = $changed->fetch();
        $uname = $changedUser['name'] ?? "User #$id";
        logActivity($pdo, 'changed_role', "Changed role of \"$uname\" to $role");

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_user') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'id required']); exit; }

        $target = $pdo->prepare("SELECT name, email, role FROM users WHERE id = ?");
        $target->execute([$id]);
        $targetUser = $target->fetch();

        $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([$id]);

        if ($targetUser) {
            logActivity($pdo, 'deleted_user', "Deleted account \"{$targetUser['name']}\" ({$targetUser['email']}, role: {$targetUser['role']})");
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'clear_requests') {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM data_requests WHERE deleted = 1")->fetchColumn();
        $pdo->query("DELETE FROM data_requests WHERE deleted = 1");
        logActivity($pdo, 'cleared_requests', "Permanently deleted $count soft-deleted request(s) from the recycle bin");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'clear_notifications') {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
        $pdo->query("DELETE FROM notifications");
        logActivity($pdo, 'cleared_notifications', "Cleared all notifications ($count total)");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'reset_buildings') {
        $pdo->query("DELETE FROM buildings");
        $defaults = [
            ['SWDC Building', 8.36030910, 124.86777742, 'moderate', 'Main administrative offices', 55],
            ['NBSC Covered Court', 8.36012237, 124.86894170, 'moderate', 'Sports and events facility', 62],
            ['NBSC Library', 8.35926403, 124.86789449, 'low', 'Main library and study center', 18],
            ['NBSC Clinic', 8.35915760, 124.86817955, 'moderate', 'Medical services and health center', 47],
            ['BSBA Building', 8.35909641, 124.86842964, 'high', 'Business and administration classrooms', 130],
            ['ICS Laboratory', 8.35922146, 124.86905085, 'moderate', 'Computer science and IT laboratory', 70],
            ['Cafeteria', 8.35890000, 124.86820000, 'moderate', 'Student dining facility', 58],
        ];
        $ins = $pdo->prepare(
            "INSERT INTO buildings (name, lat, lng, pollution_level, description, lux) VALUES (?,?,?,?,?,?)"
        );
        foreach ($defaults as $d) {
            $ins->execute($d);
        }
        logActivity($pdo, 'reset_buildings', "Reset all campus buildings to the default configuration (7 buildings)");
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
