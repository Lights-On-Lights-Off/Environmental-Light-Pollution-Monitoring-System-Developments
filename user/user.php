<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

requireRole('user');

$user = currentUser();

// Load buildings for the map and the location dropdown in the request form
$buildings = $pdo->query("SELECT * FROM buildings ORDER BY name ASC")->fetchAll();

// Load this user's requests
$reqStmt = $pdo->prepare(
    "SELECT dr.*, b.name AS building_name
     FROM data_requests dr
     LEFT JOIN buildings b ON b.id = dr.building_id
     WHERE dr.user_id = ? AND dr.deleted = 0
     ORDER BY dr.submitted_at DESC"
);
$reqStmt->execute([$user['id']]);
$requests = $reqStmt->fetchAll();

// Load notifications for this user
$notifStmt = $pdo->prepare(
    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20"
);
$notifStmt->execute([$user['id']]);
$notifications = $notifStmt->fetchAll();

// Mark all notifications read on page load
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['id']]);

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)   return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>User Dashboard — NBSC Light Pollution Monitoring</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="<?= url('assets/css/styles.css') ?>" />
</head>
<body class="page-user">
<canvas id="bg-canvas"></canvas>

<nav class="user-navbar">
    <a href="<?= url('index.php') ?>" class="nav-brand">
        <span class="brand-badge"></span>
        Environmental Light Pollution Monitoring System
    </a>
    <div style="position:relative;">
        <div class="user-pill" id="userPillToggle">
            <div class="user-avatar-circle"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <div>
                <div class="user-pill-name"><?= htmlspecialchars($user['name']) ?></div>
                <div class="user-pill-email"><?= htmlspecialchars($user['email']) ?></div>
            </div>
        </div>
        <div class="user-pill-dropdown" id="userDropdown" class="user-pill-dropdown">
            <div class="dropdown-user-info">
                <div class="dropdown-user-name"><?= htmlspecialchars($user['name']) ?></div>
                <div class="dropdown-user-email"><?= htmlspecialchars($user['email']) ?></div>
            </div>
            <a href="<?= url('user/user.php') ?>" class="dropdown-item-btn" style="text-decoration:none;">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16"><path d="M8.354 1.146a.5.5 0 0 0-.708 0l-6 6A.5.5 0 0 0 1.5 7.5v7a.5.5 0 0 0 .5.5h4a.5.5 0 0 0 .5-.5v-4h3v4a.5.5 0 0 0 .5.5h4a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.146-.354L8.354 1.146z"/></svg>
                Back to Home
            </a>
            <a href="<?= url('logout.php') ?>" class="dropdown-item-btn danger" style="text-decoration:none;">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/><path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/></svg>
                Logout
            </a>
        </div>
    </div>
</nav>

<div class="dashboard-wrapper">

    <div class="tab-bar">
        <button class="tab-btn active" data-tab="map">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M15.817.113A.5.5 0 0 1 16 .5v14a.5.5 0 0 1-.402.49l-5 1a.5.5 0 0 1-.196 0L5.5 15.01l-4.902.98A.5.5 0 0 1 0 15.5v-14a.5.5 0 0 1 .402-.49l5-1a.502.502 0 0 1 .196 0L10.5.99l4.902-.98a.5.5 0 0 1 .415.103z"/></svg>
            Campus Map
        </button>
        <button class="tab-btn" data-tab="requests">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M5 10.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5zm0-2a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm0-2a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm0-2a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5z"/><path d="M3 0h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2zm0 1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H3z"/></svg>
            My Requests
        </button>
        <button class="tab-btn" data-tab="new-request">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/><path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"/></svg>
            New Request
        </button>
        <button class="tab-btn" data-tab="notifications">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2zM8 1.918l-.797.161A4.002 4.002 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4.002 4.002 0 0 0-3.203-3.92L8 1.917zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6c0-2.42 1.72-4.44 4.005-4.901a1 1 0 1 1 1.99 0A5.002 5.002 0 0 1 13 6c0 .88.32 4.2 1.22 6z"/></svg>
            Notifications <?php if (count(array_filter($notifications, fn($n) => !$n['is_read']))): ?>
                <span style="background:#ef4444;color:#fff;border-radius:50%;width:16px;height:16px;font-size:0.65rem;display:inline-flex;align-items:center;justify-content:center;">
                    <?= count(array_filter($notifications, fn($n) => !$n['is_read'])) ?>
                </span>
            <?php endif; ?>
        </button>
    </div>

    <!-- Campus map tab -->
    <div class="tab-content active" id="map-tab">
        <div class="dash-card">
            <h4>Campus Light Pollution Map</h4>
            <p class="card-sub">Real-time light pollution data across the NBSC campus</p>
            <div class="map-controls-bar">
                <div class="map-filter-group">
                    <label for="user-map-filter">Filter:</label>
                    <select id="user-map-filter" class="user-filter-select">
                        <option value="all">All Buildings</option>
                        <option value="low">Low</option>
                        <option value="moderate">Moderate</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div style="display:flex;gap:8px;">
                    <button class="map-btn" id="refresh-user-map-btn">↻ Refresh</button>
                    <button class="map-btn" id="reset-user-map-btn">⊙ Reset View</button>
                </div>
            </div>
            <div id="user-campus-map"></div>
            <div class="map-legend">
                <div class="legend-item"><div class="legend-dot low"></div> Low</div>
                <div class="legend-item"><div class="legend-dot moderate"></div> Moderate</div>
                <div class="legend-item"><div class="legend-dot high"></div> High</div>
            </div>
        </div>
    </div>

    <!-- My requests tab -->
    <div class="tab-content" id="requests-tab">
        <div class="dash-card">
            <h4>My Data Requests</h4>
            <p class="card-sub">Track the status of your submitted requests</p>
            <?php if (!$requests): ?>
                <div class="empty-state"><div class="empty-icon">📋</div><p>You haven't submitted any requests yet.</p></div>
            <?php else: ?>
                <?php foreach ($requests as $r): ?>
                <div class="request-item" id="req-<?= $r['id'] ?>">
                    <div class="request-item-header">
                        <span class="request-id">REQ-<?= $r['id'] ?></span>
                        <span class="status-badge <?= htmlspecialchars($r['status']) ?>"><?= ucfirst($r['status']) ?></span>
                    </div>
                    <div class="request-meta">
                        <div class="meta-item"><label>Data Type</label><span><?= htmlspecialchars($r['data_type'] ?? '—') ?></span></div>
                        <div class="meta-item"><label>Location</label><span><?= htmlspecialchars($r['location'] ?? '—') ?></span></div>
                        <div class="meta-item"><label>Date Range</label><span><?= htmlspecialchars($r['start_date'] ?? '—') ?> → <?= htmlspecialchars($r['end_date'] ?? '—') ?></span></div>
                        <div class="meta-item"><label>Submitted</label><span><?= date('M d, Y', strtotime($r['submitted_at'])) ?></span></div>
                    </div>
                    <div class="request-item-actions">
                        <?php if ($r['status'] === 'approved'): ?>
                        <button class="btn-download" onclick="downloadCSV(<?= $r['id'] ?>, <?= $r['building_id'] ?? 'null' ?>, '<?= addslashes($r['location']) ?>', '<?= $r['start_date'] ?>', '<?= $r['end_date'] ?>', '<?= addslashes($r['data_type']) ?>')">
                            ↓ Download Data
                        </button>
                        <?php endif; ?>
                        <button class="btn-delete" onclick="deleteRequest(<?= $r['id'] ?>)">🗑 Delete</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- New request tab -->
    <div class="tab-content" id="new-request-tab">
        <div class="dash-card">
            <h4>Submit New Data Request</h4>
            <p class="card-sub">Request light pollution data for research or academic purposes</p>
            <div id="form-msg"></div>
            <form id="data-request-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="fullName" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address *</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Organization *</label>
                        <input type="text" name="organization" placeholder="e.g. NBSC" required>
                    </div>
                    <div class="form-group">
                        <label>Campus Location *</label>
                        <select name="location" required>
                            <option value="">Select Location</option>
                            <?php foreach ($buildings as $b): ?>
                            <option value="<?= htmlspecialchars($b['name']) ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Data Type *</label>
                        <select name="dataType" required>
                            <option value="">Select Type</option>
                            <option value="Historical Data">Historical Data</option>
                            <option value="Live Data">Live Data</option>
                            <option value="Comparison Data">Comparison Data</option>
                            <option value="Impact Report">Impact Report</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Purpose *</label>
                        <select name="purpose" required>
                            <option value="">Select Purpose</option>
                            <option value="Research Study">Research Study</option>
                            <option value="Academic Project">Academic Project</option>
                            <option value="Thesis/Dissertation">Thesis/Dissertation</option>
                            <option value="Environmental Assessment">Environmental Assessment</option>
                            <option value="Policy Development">Policy Development</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Date Range *</label>
                        <div class="date-row">
                            <div class="form-group"><label style="font-size:0.75rem;">From</label><input type="date" name="startDate" required></div>
                            <div class="form-group"><label style="font-size:0.75rem;">To</label><input type="date" name="endDate" required></div>
                        </div>
                    </div>
                    <div class="form-group full">
                        <label>Additional Notes</label>
                        <textarea name="additionalNotes" placeholder="Any additional information…"></textarea>
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:16px;">
                    <button type="submit" class="btn-primary-user">Submit Request</button>
                    <button type="reset" class="btn-secondary-user">Clear Form</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Notifications tab -->
    <div class="tab-content" id="notifications-tab">
        <div class="dash-card">
            <h4>Notifications</h4>
            <p class="card-sub">Updates on your requests and system announcements</p>
            <?php if (!$notifications): ?>
                <div class="notif-item">
                    <div class="notif-dot"></div>
                    <div>
                        <div class="notif-text">Welcome, <?= htmlspecialchars($user['name']) ?>! Your dashboard is ready.</div>
                        <div class="notif-time">Just now</div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                <div class="notif-item">
                    <div class="notif-dot" style="background:<?= $n['is_read'] ? '#a5a5a5' : 'var(--accent)' ?>"></div>
                    <div>
                        <div class="notif-text"><?= htmlspecialchars($n['message']) ?></div>
                        <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Buildings data for the Leaflet map -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
window.BASE_URL = "<?= url('') ?>";
const BUILDINGS_DATA = <?= json_encode(array_map(fn($b) => [
    'id'             => (int)$b['id'],
    'name'           => $b['name'],
    'coordinates'    => [(float)$b['lat'], (float)$b['lng']],
    'pollutionLevel' => $b['pollution_level'],
    'description'    => $b['description'] ?? '',
    'lux'            => (float)$b['lux'],
    'online'         => (bool)$b['online'],
], $buildings), JSON_HEX_TAG) ?>;
</script>
<script src="<?= url('assets/js/user.js') ?>"></script>
</body>