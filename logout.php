<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';

if (isLoggedIn()) {
    logActivity($pdo, 'logout', "Logged out");
}

session_destroy();
header('Location: ' . rootUrl('index.php'));
exit;
