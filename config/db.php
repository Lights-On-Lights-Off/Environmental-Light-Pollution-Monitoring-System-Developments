<?php
// Central database connection using PDO.
// All other PHP files include this to get the $pdo variable.

// Derive BASE_URL from the server environment so the app works at any path
// (localhost/, localhost/IAS---ELPMS/, or a live domain subfolder).
if (!defined('BASE_URL')) {
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    $appRoot = str_replace('\\', '/', dirname(__DIR__));
    $relative = $docRoot ? str_replace($docRoot, '', $appRoot) : '';
    $relative = '/' . ltrim($relative, '/');
    define('BASE_URL', rtrim($relative, '/'));
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'u442411629_hayag');
define('DB_USER', 'u442411629_dev_hayag');
define('DB_PASS', 'FI6Qr2mmS4v{');
define('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
}

// Get the real IP address of the visitor.
// Source: codexworld.com/how-to/get-user-ip-address-php
function getUserIpAddr() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

// Detects browser name from User-Agent string.
// Source: php.net/manual/en/function.get-browser.php 
function get_browser_name($user_agent) {
//from brave github repo to detect the brave atempting to fix issue
    $clientHints = $_SERVER['HTTP_SEC_CH_UA'] ?? '';
    if (strpos($clientHints, 'Brave') !== false) return 'Brave';

    if (strpos($user_agent, 'Opera') || strpos($user_agent, 'OPR/')) return 'Opera';
    elseif (strpos($user_agent, 'Edg'))     return 'Edge';
    elseif (strpos($user_agent, 'Chrome'))  return 'Chrome';
    elseif (strpos($user_agent, 'Safari'))  return 'Safari';
    elseif (strpos($user_agent, 'Firefox')) return 'Firefox';
    elseif (strpos($user_agent, 'MSIE') || strpos($user_agent, 'Trident/7')) return 'Internet Explorer';
    return 'Other';
}

// Shared activity logger - call this from any API file after $pdo is available.
// Requires session to be started so $_SESSION['user_id'] is accessible.
function logActivity(PDO $pdo, string $action, string $detail): void {
    $userId  = $_SESSION['user_id'] ?? null;
    if (!$userId) return;
    $page    = $_SERVER['HTTP_REFERER'] ?? ($_SERVER['REQUEST_URI'] ?? null);
    $ip      = getUserIpAddr();
    $browser = get_browser_name($_SERVER['HTTP_USER_AGENT'] ?? '');
    try {
        $pdo->prepare(
            "INSERT INTO activity_log (user_id, action, detail, page, ip_address, browser) VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$userId, $action, $detail, $page, $ip, $browser]);
    } catch (Throwable $e) {}
}