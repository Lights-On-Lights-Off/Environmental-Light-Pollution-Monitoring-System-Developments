<?php
// Starts the session and provides helper functions for auth checks.
// Include this at the top of every protected PHP page.

if (session_status() === PHP_SESSION_NONE) {
    // lifetime 0 means the session cookie is deleted when the browser tab closes
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

// Builds an absolute URL to a file relative to the project root.
// Works regardless of whether the app lives at / or /lpms/ or any subfolder.
function rootUrl(string $path): string {
    $docRoot  = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $appRoot  = str_replace('\\', '/', dirname(__DIR__));
    $relative = str_replace($docRoot, '', $appRoot);
    $relative = '/' . ltrim($relative, '/');
    return rtrim($relative, '/') . '/' . ltrim($path, '/');
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . rootUrl('login.php'));
        exit;
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        header('Location: ' . rootUrl('index.php'));
        exit;
    }
}

function currentUser(): array {
    return [
        'id'    => $_SESSION['user_id']    ?? null,
        'name'  => $_SESSION['user_name']  ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['role']       ?? '',
    ];
}
