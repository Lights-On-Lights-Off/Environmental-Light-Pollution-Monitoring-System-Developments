<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// Redirect already logged-in users to their dashboard
if (isLoggedIn()) {
    $dest = match($_SESSION['role']) {
        'admin'   => url('admin/admin.php'),
        'manager' => url('manager/manager.php'),
        default   => url('user/user.php'),
    };
    header('Location: ' . $dest);
    exit;
}

$tab    = $_GET['tab'] ?? 'login';
$errors = [];
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $errors[] = 'Email and password are required.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role']       = $user['role'];

            $dest = match($user['role']) {
                'admin'   => url('admin/admin.php'),
                'manager' => url('manager/manager.php'),
                default   => url('user/user.php'),
            };
            header('Location: ' . $dest);
            exit;
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
    $tab = 'login';
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim(strtolower($_POST['email'] ?? ''));
    $org      = trim($_POST['org'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$name || !$email || !$password) {
        $errors[] = 'Name, email, and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins  = $pdo->prepare(
                "INSERT INTO users (name, email, password, org, role) VALUES (?, ?, ?, ?, 'user')"
            );
            $ins->execute([$name, $email, $hash, $org]);
            $success = 'Account created! You can now log in.';
            $tab = 'login';
        }
    }
    if ($errors) $tab = 'register';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login — NBSC Light Pollution Monitoring</title>
    <link rel="stylesheet" href="<?= url('assets/css/styles.css') ?>" />
</head>
<body class="page-login">
<canvas id="bg-canvas"></canvas>
<div class="login-page">
    <div class="login-card">
        <div class="login-card-header">
            <div class="badge-dot"></div>
            <h1 id="form-title"><?= $tab === 'register' ? 'Create Account' : 'Welcome Back' ?></h1>
            <p>NBSC Light Pollution Monitoring</p>
        </div>

        <?php if ($errors): ?>
            <div class="login-msg error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="login-msg success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($tab !== 'register'): ?>
        <!-- Login form -->
        <form method="POST" action="<?= url('login.php') ?>">
            <input type="hidden" name="action" value="login">
            <div class="login-form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required>
            </div>
            <div class="login-form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn-login">Login</button>
            <div class="login-footer">
                <p>Don't have an account? <a href="<?= url('login.php') ?>?tab=register">Register here</a></p>
                <div class="login-divider"></div>
                <p><a href="<?= url('index.php') ?>">← Back to Dashboard</a></p>
            </div>
        </form>
        <?php else: ?>
        <!-- Register form -->
        <form method="POST" action="<?= url('login.php') ?>?tab=register">
            <input type="hidden" name="action" value="register">
            <div class="login-form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" placeholder="Juan dela Cruz" required>
            </div>
            <div class="login-form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required>
            </div>
            <div class="login-form-group">
                <label>Organization <span style="color:#555;font-size:0.7rem;">(optional)</span></label>
                <input type="text" name="org" placeholder="e.g. NBSC">
            </div>
            <div class="login-form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Create a password" required>
            </div>
            <div class="login-form-group">
                <label for="confirm">Confirm Password</label>
                <input type="password" id="confirm" name="confirm" placeholder="Repeat your password" required>
            </div>
            <button type="submit" class="btn-login">Create Account</button>
            <div class="login-footer">
                <p>Already have an account? <a href="<?= url('login.php') ?>">Login here</a></p>
                <div class="login-divider"></div>
                <p><a href="<?= url('index.php') ?>">← Back to Dashboard</a></p>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script src="<?= url('assets/js/login.js') ?>"></script>
</body>
</html>
