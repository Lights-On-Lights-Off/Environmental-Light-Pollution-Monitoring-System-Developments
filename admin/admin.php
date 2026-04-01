<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

requireRole('admin');

$user = currentUser();

$totalUsers    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalManagers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='manager'")->fetchColumn();
$totalRequests = (int)$pdo->query("SELECT COUNT(*) FROM data_requests WHERE deleted=0")->fetchColumn();
$pending       = (int)$pdo->query("SELECT COUNT(*) FROM data_requests WHERE status='pending' AND deleted=0")->fetchColumn();

$allUsers = $pdo->query(
    "SELECT id, name, email, org, role, created_at FROM users WHERE role != 'admin' ORDER BY created_at DESC"
)->fetchAll();

$activityLog = $pdo->query(
    "SELECT al.*, u.name AS actor_name, u.role AS actor_role
     FROM activity_log al
     JOIN users u ON u.id = al.user_id
     ORDER BY al.created_at DESC LIMIT 200"
)->fetchAll();

$recentUsers = array_slice($allUsers, 0, 5);

function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)    return 'Just now';
    if ($diff < 3600)  return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin — NBSC Light Pollution Monitoring</title>
    <link rel="stylesheet" href="<?= url('assets/css/styles.css') ?>" />
</head>
<body class="page-admin">
<canvas id="bg-canvas"></canvas>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="shell">
    <aside class="sidebar" id="adminSidebar">
        <div class="sidebar-brand">
            <div class="brand-dot"></div>
            <div class="brand-texts"><h2>NBSC Admin</h2><p>System Administrator</p></div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section-lbl">Overview</div>
            <button class="nav-item active" data-section="dashboard">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 4a.5.5 0 0 1 .5.5V6a.5.5 0 0 1-1 0V4.5A.5.5 0 0 1 8 4zM3.732 5.732a.5.5 0 0 1 .707 0l.915.914a.5.5 0 1 1-.708.708l-.914-.915a.5.5 0 0 1 0-.707zM2 10a.5.5 0 0 1 .5-.5h1.586a.5.5 0 0 1 0 1H2.5A.5.5 0 0 1 2 10zm9.5 0a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 0 1H12a.5.5 0 0 1-.5-.5z"/><path fill-rule="evenodd" d="M8 1a9 9 0 1 0 0 18A9 9 0 0 0 8 1z"/></svg>
                Dashboard
            </button>
            <div class="nav-section-lbl" style="margin-top:8px;">Management</div>
            <button class="nav-item" data-section="users">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8z"/><path d="M6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816zM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275zM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/></svg>
                Users Management
            </button>
            <div class="nav-section-lbl" style="margin-top:8px;">System</div>
            <button class="nav-item" data-section="activity">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M5 4a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1H5zm-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5zM5 8a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1H5zm0 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1H5z"/><path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1z"/></svg>
                Activity Log
            </button>
            <button class="nav-item" data-section="settings">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492z"/><path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319z"/></svg>
                System Settings
            </button>
        </nav>
        <div class="sidebar-footer">
            <a href="<?= url('logout.php') ?>" class="logout-btn" style="text-decoration:none;">
                <svg width="15" height="15" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/><path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/></svg>
                Logout
            </a>
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="menu-toggle" id="menuToggle">☰</button>
                <span class="topbar-elpms">ELPMS</span>
                <div><div id="page-title">Dashboard</div><div class="topbar-sub" id="topbar-sub">System overview</div></div>
            </div>
            <div class="admin-pill">
                <div class="admin-avatar">AD</div>
                <span><?= htmlspecialchars($user['name']) ?></span>
            </div>
        </div>

        <div class="content-area">

            <div class="stats-row">
                <div class="stat-card"><div class="stat-icon clr-blue"><svg width="20" height="20" fill="#0d6efd" viewBox="0 0 16 16"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4z"/></svg></div><div><div class="stat-num"><?= $totalUsers ?></div><div class="stat-lbl">Total Users</div></div></div>
                <div class="stat-card"><div class="stat-icon clr-orange"><svg width="20" height="20" fill="#f59e0b" viewBox="0 0 16 16"><path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8z"/></svg></div><div><div class="stat-num"><?= $totalManagers ?></div><div class="stat-lbl">Managers</div></div></div>
                <div class="stat-card"><div class="stat-icon clr-green"><svg width="20" height="20" fill="#22c55e" viewBox="0 0 16 16"><path d="M14 1a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H4.414A2 2 0 0 0 3 11.586l-2 2V2a1 1 0 0 1 1-1h12z"/></svg></div><div><div class="stat-num"><?= $totalRequests ?></div><div class="stat-lbl">Total Requests</div></div></div>
                <div class="stat-card"><div class="stat-icon clr-red"><svg width="20" height="20" fill="#ef4444" viewBox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg></div><div><div class="stat-num"><?= $pending ?></div><div class="stat-lbl">Pending Requests</div></div></div>
            </div>

            <!-- Dashboard section -->
            <div class="section active" id="section-dashboard">
                <div class="dash-grid">
                    <div class="card-panel">
                        <div class="card-head"><h3>Recent Accounts</h3><button class="btn btn-ghost btn-sm" onclick="navigate('users')">View All</button></div>
                        <div class="card-body">
                            <?php if (!$recentUsers): ?>
                                <div class="panel-empty-state"><div class="panel-empty-icon">👤</div><p>No accounts yet</p></div>
                            <?php else: foreach ($recentUsers as $u): ?>
                            <div class="mini-item">
                                <div class="mini-item-left"><span class="mini-item-name"><?= htmlspecialchars($u['name']) ?></span><span class="mini-item-sub"><?= htmlspecialchars($u['email']) ?></span></div>
                                <span class="badge <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                    <div class="card-panel">
                        <div class="card-head"><h3>Recent Activity</h3><button class="btn btn-ghost btn-sm" onclick="navigate('activity')">View All</button></div>
                        <div class="card-body">
                            <?php if (!$activityLog): ?>
                                <div class="panel-empty-state"><div class="panel-empty-icon">📋</div><p>No activity yet</p></div>
                            <?php else: foreach (array_slice($activityLog,0,10) as $e):
                                $dotClass = match($e['action'] ?? '') {
                                    'approved_request','added_building','added_user' => 'approved',
                                    'denied_request','deleted_building','deleted_user','permanent_deleted_request' => 'denied',
                                    default => 'info'
                                };
                            ?>
                            <div class="activity-item">
                                <div class="activity-dot <?= $dotClass ?>"></div>
                                <div>
                                    <div class="activity-text">
                                        <strong><?= htmlspecialchars($e['actor_name']) ?></strong>
                                        <span class="badge <?= $e['actor_role'] ?>" style="font-size:0.65rem;padding:1px 6px;margin:0 4px;"><?= ucfirst($e['actor_role']) ?></span>
                                        — <?= htmlspecialchars($e['detail'] ?? '') ?>
                                    </div>
                                    <div class="activity-time"><?= timeAgo($e['created_at']) ?></div>
                                </div>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users section -->
            <div class="section" id="section-users">
                <div class="card-panel">
                    <div class="card-head"><h3>All Accounts</h3>
                        <div class="card-head-actions">
                            <select id="role-filter" class="panel-filter-select" onchange="filterUsers()">
                                <option value="all">All Roles</option>
                                <option value="user">User</option>
                                <option value="manager">Manager</option>
                            </select>
                            <button class="btn btn-primary btn-sm" onclick="openAddUserModal()">+ Add Account</button>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead>
                            <tbody id="users-tbody">
                            <?php foreach ($allUsers as $u): ?>
                            <tr data-role="<?= $u['role'] ?>">
                                <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                                <td style="color:var(--muted);"><?= htmlspecialchars($u['email']) ?></td>
                                <td><span class="badge <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                                <td><div class="td-actions">
                                    <button class="btn btn-ghost btn-sm" onclick="openRoleModal(<?= $u['id'] ?>, '<?= addslashes($u['name']) ?>', '<?= $u['role'] ?>')">Edit Role</button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteUser(<?= $u['id'] ?>, '<?= addslashes($u['name']) ?>')">Delete</button>
                                </div></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Activity Log section -->
            <div class="section" id="section-activity">
                <div class="card-panel">
                    <div class="card-head">
                        <h3>Activity Log</h3>
                        <div class="card-head-actions">
                            <select id="log-role-filter" class="panel-filter-select" onchange="filterActivityLog()">
                                <option value="all">All Roles</option>
                                <option value="user">Users Only</option>
                                <option value="manager">Managers Only</option>
                                <option value="admin">Admin Only</option>
                            </select>
                            <select id="log-type-filter" class="panel-filter-select" onchange="filterActivityLog()">
                                <option value="all">All Actions</option>
                                <option value="request">Requests</option>
                                <option value="building">Buildings</option>
                                <option value="user">Accounts</option>
                                <option value="system">System</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Time</th><th>Actor</th><th>Role</th><th>Action</th><th>Detail</th><th>Page</th></tr></thead>
                            <tbody id="activity-tbody">
                            <?php foreach ($activityLog as $e):
                                $actionGroup = match(true) {
                                    str_contains($e['action'], 'request')      => 'request',
                                    str_contains($e['action'], 'building')     => 'building',
                                    str_contains($e['action'], 'user') || str_contains($e['action'], 'role') => 'user',
                                    default                                    => 'system'
                                };
                                $dotClass = match($e['action'] ?? '') {
                                    'approved_request','added_building','added_user','restored_request' => 'approved',
                                    'denied_request','deleted_building','deleted_user','permanent_deleted_request','cleared_requests','cleared_notifications' => 'denied',
                                    default => 'info'
                                };
                                $actionLabel = ucwords(str_replace('_', ' ', $e['action']));
                            ?>
                            <tr data-role="<?= $e['actor_role'] ?>" data-type="<?= $actionGroup ?>">
                                <td style="white-space:nowrap;color:var(--muted);font-size:0.8rem;">
                                    <?= date('M d, H:i', strtotime($e['created_at'])) ?>
                                </td>
                                <td><strong><?= htmlspecialchars($e['actor_name']) ?></strong></td>
                                <td><span class="badge <?= $e['actor_role'] ?>"><?= ucfirst($e['actor_role']) ?></span></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <div class="activity-dot <?= $dotClass ?>" style="flex-shrink:0;"></div>
                                        <span style="font-size:0.8rem;"><?= htmlspecialchars($actionLabel) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($e['detail'])): ?>
                                    <button class="btn btn-ghost btn-sm" onclick="showDetail('<?= addslashes(htmlspecialchars($e['detail'])) ?>')">View</button>
                                    <?php else: ?>
                                    <span style="color:var(--muted);font-size:0.8rem;">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (!$activityLog): ?>
                            <div class="panel-empty-state"><div class="panel-empty-icon">📋</div><p>No activity recorded yet</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Settings section -->
            <div class="section" id="section-settings">
                <div class="card-panel">
                    <div class="card-head"><h3>System Settings</h3></div>
                    <div class="settings-grid">
                        <div class="setting-item"><div class="setting-info"><h4>Clear All Requests</h4><p>Permanently delete all soft-deleted requests from the recycle bin. Cannot be undone.</p></div><button class="btn btn-danger btn-sm" onclick="clearRequests()">Clear</button></div>
                        <div class="setting-item"><div class="setting-info"><h4>Clear Notifications</h4><p>Remove all user notifications stored in the system.</p></div><button class="btn btn-warn btn-sm" onclick="clearNotifications()">Clear</button></div>
                        <div class="setting-item"><div class="setting-info"><h4>Reset Buildings to Default</h4><p>Restore the campus buildings list back to the original default configuration.</p></div><button class="btn btn-warn btn-sm" onclick="resetBuildings()">Reset</button></div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- Edit Role Modal -->
<div class="modal-overlay" id="role-modal">
    <div class="modal-box">
        <div class="modal-head"><h3>Edit Role</h3><button class="modal-close" onclick="document.getElementById('role-modal').classList.remove('open')">✕</button></div>
        <div class="modal-body">
            <p>Change the role for <strong id="role-modal-name"></strong>.</p>
            <div class="form-group"><label>Role</label><select id="role-select"><option value="user">User</option><option value="manager">Manager</option></select></div>
            <div class="form-actions">
                <button class="btn btn-primary" onclick="saveRole()">Save</button>
                <button class="btn btn-ghost" onclick="document.getElementById('role-modal').classList.remove('open')">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="add-user-modal">
    <div class="modal-box">
        <div class="modal-head"><h3>Add Account</h3><button class="modal-close" onclick="document.getElementById('add-user-modal').classList.remove('open')">✕</button></div>
        <div class="modal-body">
            <div class="form-group"><label>Full Name</label><input type="text" id="add-name" class="form-input" placeholder="Juan Dela Cruz"></div>
            <div class="form-group"><label>Email</label><input type="email" id="add-email" class="form-input" placeholder="juan@nbsc.edu.ph"></div>
            <div class="form-group"><label>Password</label><input type="password" id="add-password" class="form-input" placeholder="Min 6 characters"></div>
            <div class="form-group"><label>Role</label><select id="add-role"><option value="user">User</option><option value="manager">Manager</option></select></div>
            <div id="add-user-error" style="font-size:0.82rem;color:#f87171;margin-bottom:10px;display:none;"></div>
            <div class="form-actions">
                <button class="btn btn-primary" onclick="submitAddUser()">Create Account</button>
                <button class="btn btn-ghost" onclick="document.getElementById('add-user-modal').classList.remove('open')">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal-overlay" id="detail-modal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Action Detail</h3>
            <button class="modal-close" onclick="document.getElementById('detail-modal').classList.remove('open')">✕</button>
        </div>
        <div class="modal-body">
            <p id="detail-modal-text" style="font-size:0.9rem;line-height:1.6;word-break:break-word;"></p>
        </div>
    </div>
</div>

<script>
window.BASE_URL = "<?= url('') ?>";
</script>
<script src="<?= url('assets/js/admin.js') ?>?v=<?= time() ?>"></script>
</body>
</html>
