<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

requireRole('manager');

$user = currentUser();

$buildings = $pdo->query("SELECT * FROM buildings ORDER BY name ASC")->fetchAll();

$requests = $pdo->query(
    "SELECT dr.*, u.name AS user_name, u.email AS user_email
     FROM data_requests dr
     JOIN users u ON u.id = dr.user_id
     WHERE dr.deleted = 0
     ORDER BY dr.submitted_at DESC"
)->fetchAll();

$recycleRequests = $pdo->query(
    "SELECT dr.*, u.name AS user_name, u.email AS user_email
     FROM data_requests dr
     JOIN users u ON u.id = dr.user_id
     WHERE dr.deleted = 1
     ORDER BY dr.deleted_at DESC"
)->fetchAll();

$pending  = array_filter($requests, fn($r) => $r['status'] === 'pending');
$approved = array_filter($requests, fn($r) => $r['status'] === 'approved');
$high     = array_filter($buildings, fn($b) => $b['pollution_level'] === 'high');

$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $user['name']), 0, 2)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manager — NBSC Light Pollution Monitoring</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="<?= url('assets/css/styles.css') ?>" />
</head>
<body class="page-manager">
<canvas id="bg-canvas"></canvas>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-dot"></div>
            <div class="brand-texts"><h2>NBSC Manager</h2><p>Light Pollution Management</p></div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section-lbl">Overview</div>
            <button class="nav-item active" data-section="dashboard">
                <svg width="15" height="15" fill="currentColor" viewBox="0 0 16 16"><path d="M0 1.5A1.5 1.5 0 0 1 1.5 0h2A1.5 1.5 0 0 1 5 1.5v2A1.5 1.5 0 0 1 3.5 5h-2A1.5 1.5 0 0 1 0 3.5v-2zm5.5 0A1.5 1.5 0 0 1 7 0h2a1.5 1.5 0 0 1 1.5 1.5v2A1.5 1.5 0 0 1 9 5H7A1.5 1.5 0 0 1 5.5 3.5v-2zm5.5 0A1.5 1.5 0 0 1 12.5 0h2A1.5 1.5 0 0 1 16 1.5v2A1.5 1.5 0 0 1 14.5 5h-2A1.5 1.5 0 0 1 11 3.5v-2z"/></svg>
                Dashboard
            </button>
            <div class="nav-section-lbl" style="margin-top:8px;">Manage</div>
            <button class="nav-item" data-section="requests"><svg width="15" height="15" fill="currentColor" viewBox="0 0 16 16"><path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1v-1z"/><path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5h3z"/></svg> Data Requests</button>
            <button class="nav-item" data-section="buildings"><svg width="15" height="15" fill="currentColor" viewBox="0 0 16 16"><path d="M14.763.075A.5.5 0 0 1 15 .5v15a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5V14h-1v1.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V10a.5.5 0 0 1 .342-.474L6 7.64V4.5a.5.5 0 0 1 .276-.447l8-4a.5.5 0 0 1 .487.022z"/></svg> Buildings</button>
            <button class="nav-item" data-section="map"><svg width="15" height="15" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M15.817.113A.5.5 0 0 1 16 .5v14a.5.5 0 0 1-.402.49l-5 1a.502.502 0 0 1-.196 0L5.5 15.01l-4.902.98A.5.5 0 0 1 0 15.5v-14a.5.5 0 0 1 .402-.49l5-1a.5.5 0 0 1 .196 0L10.5.99l4.902-.98a.5.5 0 0 1 .415.103z"/></svg> Campus Map</button>
            <button class="nav-item" data-section="recycle-bin"><svg width="15" height="15" fill="currentColor" viewBox="0 0 16 16"><path d="M11 1.5v1h3.5a.5.5 0 0 1 0 1h-.538l-.853 10.66A2 2 0 0 1 11.115 16h-6.23a2 2 0 0 1-1.994-1.84L2.038 3.5H1.5a.5.5 0 0 1 0-1H5v-1A1.5 1.5 0 0 1 6.5 0h3A1.5 1.5 0 0 1 11 1.5Z"/></svg> Recycle Bin</button>
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
                <div><div id="page-title">Dashboard Overview</div><div class="topbar-sub">Monitor and manage light pollution across NBSC campus</div></div>
            </div>
            <div class="admin-pill">
                <div class="admin-avatar"><?= htmlspecialchars($initials) ?></div>
                <span id="admin-name"><?= htmlspecialchars($user['name']) ?></span>
            </div>
        </div>

        <div class="content-area">

            <div class="stats-row">
                <div class="stat-card"><div class="stat-icon clr-orange"><svg width="18" height="18" fill="#f59e0b" viewBox="0 0 16 16"><path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/></svg></div><div><div class="stat-num"><?= count($pending) ?></div><div class="stat-lbl">Pending Requests</div></div></div>
                <div class="stat-card"><div class="stat-icon clr-green"><svg width="18" height="18" fill="#22c55e" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg></div><div><div class="stat-num"><?= count($approved) ?></div><div class="stat-lbl">Approved Requests</div></div></div>
                <div class="stat-card"><div class="stat-icon clr-red"><svg width="18" height="18" fill="#ef4444" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566z"/></svg></div><div><div class="stat-num"><?= count($high) ?></div><div class="stat-lbl">High Pollution Buildings</div></div></div>
                <div class="stat-card"><div class="stat-icon clr-blue"><svg width="18" height="18" fill="#0d6efd" viewBox="0 0 16 16"><path d="M14.763.075A.5.5 0 0 1 15 .5v15a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5V14h-1v1.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V10a.5.5 0 0 1 .342-.474L6 7.64V4.5a.5.5 0 0 1 .276-.447l8-4a.5.5 0 0 1 .487.022z"/></svg></div><div><div class="stat-num"><?= count($buildings) ?></div><div class="stat-lbl">Total Buildings</div></div></div>
            </div>

            <!-- Dashboard section -->
            <div class="section active" id="section-dashboard">
                <div class="dash-grid">
                    <div class="card-panel"><div class="card-head"><h3>Recent Requests</h3></div>
                        <div class="card-body">
                            <?php $recent = array_slice(array_reverse($requests), 0, 5); ?>
                            <?php if (!$recent): ?>
                                <div class="panel-empty-state"><div class="panel-empty-icon">📋</div><p>No requests yet</p></div>
                            <?php else: foreach ($recent as $r): ?>
                                <div class="mini-item">
                                    <div class="mini-item-left"><span class="mini-item-name"><?= htmlspecialchars($r['user_name']) ?></span><span class="mini-item-sub"><?= htmlspecialchars($r['user_email']) ?></span></div>
                                    <span class="badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                    <div class="card-panel"><div class="card-head"><h3>Building Pollution Status</h3></div>
                        <div class="card-body">
                            <?php foreach (['high','moderate','low'] as $level): $cnt = count(array_filter($buildings, fn($b) => $b['pollution_level'] === $level)); ?>
                            <div class="mini-item"><span><?= ucfirst($level) ?> Pollution Buildings</span><span class="badge <?= $level ?>"><?= $cnt ?></span></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Requests section -->
            <div class="section" id="section-requests">
                <div class="card-panel">
                    <div class="card-head"><h3>Data Requests Management</h3>
                        <select id="status-filter" class="panel-filter-select" onchange="filterRequests()">
                            <option value="all">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="denied">Denied</option>
                        </select>
                    </div>
                    <div class="table-wrap">
                        <table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Location</th><th>Purpose</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody id="requests-tbody">
                            <?php foreach ($requests as $r): ?>
                            <tr data-status="<?= $r['status'] ?>">
                                <td><span style="font-family:monospace;font-size:0.82rem;color:var(--accent);">REQ-<?= $r['id'] ?></span></td>
                                <td><strong><?= htmlspecialchars($r['user_name']) ?></strong></td>
                                <td style="color:var(--muted);"><?= htmlspecialchars($r['user_email']) ?></td>
                                <td><?= htmlspecialchars($r['location'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($r['purpose'] ?? '—') ?></td>
                                <td style="color:var(--muted);"><?= date('M d, Y', strtotime($r['submitted_at'])) ?></td>
                                <td><span class="badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                                <td><div class="td-actions">
                                    <?php if ($r['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-success" onclick="reviewRequest(<?= $r['id'] ?>,'approve')">✓ Approve</button>
                                    <button class="btn btn-sm btn-warn" onclick="reviewRequest(<?= $r['id'] ?>,'deny')">✕ Deny</button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-danger" onclick="softDeleteRequest(<?= $r['id'] ?>)">Delete</button>
                                </div></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div id="requests-empty" class="panel-empty-state" style="<?= !$requests ? '' : 'display:none;' ?>"><div class="panel-empty-icon">📭</div><p>No requests found</p></div>
                    </div>
                </div>
            </div>

            <!-- Buildings section -->
            <div class="section" id="section-buildings">
                <div class="card-panel">
                    <div class="card-head"><h3>Building Management</h3>
                        <div class="card-head-actions">
                            <select id="pollution-filter" class="panel-filter-select" onchange="filterBuildings()">
                                <option value="all">All Levels</option>
                                <option value="high">High</option>
                                <option value="moderate">Moderate</option>
                                <option value="low">Low</option>
                            </select>
                            <button class="btn btn-primary" onclick="openBuildingModal()">+ Add Building</button>
                        </div>
                    </div>
                    <div class="buildings-grid" id="buildings-grid">
                        <?php foreach ($buildings as $b): ?>
                        <div class="building-card" data-level="<?= $b['pollution_level'] ?>" id="bcard-<?= $b['id'] ?>">
                            <div class="bc-head"><h4><?= htmlspecialchars($b['name']) ?></h4><span class="badge <?= $b['pollution_level'] ?>"><?= ucfirst($b['pollution_level']) ?></span></div>
                            <div class="bc-desc"><?= htmlspecialchars($b['description'] ?? '—') ?></div>
                            <div class="bc-coords">📍 <?= number_format($b['lat'],6) ?>, <?= number_format($b['lng'],6) ?></div>
                            <div class="bc-actions">
                                <button class="btn btn-sm btn-ghost" onclick="editBuilding(<?= $b['id'] ?>, <?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>)">Edit</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteBuilding(<?= $b['id'] ?>, '<?= addslashes($b['name']) ?>')">Delete</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Campus map section -->
            <div class="section" id="section-map">
                <div class="card-panel">
                    <div class="card-head"><h3>Campus Map</h3>
                        <select id="map-filter" class="panel-filter-select" onchange="filterMapMarkers(this.value)">
                            <option value="all">All Buildings</option>
                            <option value="high">High Pollution</option>
                            <option value="moderate">Moderate Pollution</option>
                            <option value="low">Low Pollution</option>
                        </select>
                    </div>
                    <div id="manager-campus-map"></div>
                    <div class="panel-map-legend">
                        <div class="legend-item"><div class="legend-dot low"></div> Low Pollution</div>
                        <div class="legend-item"><div class="legend-dot moderate"></div> Moderate Pollution</div>
                        <div class="legend-item"><div class="legend-dot high"></div> High Pollution</div>
                    </div>
                </div>
            </div>

            <!-- Recycle bin section -->
            <div class="section" id="section-recycle-bin">
                <div class="card-panel">
                    <div class="card-head"><h3>Recycle Bin</h3>
                        <button class="btn btn-danger" onclick="emptyBin()">Empty Bin</button>
                    </div>
                    <div class="table-wrap">
                        <table id="recycle-table"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Date</th><th>Status</th><th>Deleted On</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($recycleRequests as $r): ?>
                            <tr id="rbin-<?= $r['id'] ?>">
                                <td><span style="font-family:monospace;font-size:0.82rem;color:var(--accent);">REQ-<?= $r['id'] ?></span></td>
                                <td><?= htmlspecialchars($r['user_name']) ?></td>
                                <td style="color:var(--muted);"><?= htmlspecialchars($r['user_email']) ?></td>
                                <td><?= date('M d, Y', strtotime($r['submitted_at'])) ?></td>
                                <td><span class="badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                                <td style="color:var(--muted);"><?= $r['deleted_at'] ? date('M d, Y', strtotime($r['deleted_at'])) : '—' ?></td>
                                <td><div class="td-actions">
                                    <button class="btn btn-sm btn-success" onclick="restoreRequest(<?= $r['id'] ?>)">↩ Restore</button>
                                    <button class="btn btn-sm btn-danger" onclick="permanentDelete(<?= $r['id'] ?>)">Delete Forever</button>
                                </div></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (!$recycleRequests): ?>
                        <div class="panel-empty-state"><div class="panel-empty-icon">🗑️</div><p>Recycle bin is empty</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- Building modal -->
<div class="modal-overlay" id="building-modal">
    <div class="modal-box">
        <div class="modal-head"><h3 id="modal-title">Add Building</h3><button class="modal-close" onclick="closeBuildingModal()">✕</button></div>
        <div class="modal-body">
            <div class="form-group"><label>Building Name *</label><input type="text" id="b-name" class="form-input" placeholder="e.g. SWDC Building" required></div>
            <div class="form-group"><label>Location — click map to pin *</label>
                <div id="location-picker-map"></div>
                <div class="location-display" id="loc-display"><span id="loc-text">No location selected</span></div>
                <input type="hidden" id="b-lat"><input type="hidden" id="b-lng">
            </div>
            <div class="form-group"><label>Pollution Level *</label>
                <select id="b-level"><option value="low">Low</option><option value="moderate" selected>Moderate</option><option value="high">High</option></select>
            </div>
            <div class="form-group"><label>Description</label><input type="text" id="b-desc" class="form-input" placeholder="Brief description…"></div>
            <div class="form-actions">
                <button class="btn btn-primary" onclick="saveBuilding()">Save Building</button>
                <button class="btn btn-ghost" onclick="closeBuildingModal()">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Buildings data for JS -->
<script>
window.BASE_URL = "<?= url('') ?>";
let BUILDINGS = <?= json_encode(array_map(fn($b) => [
    'id'             => (int)$b['id'],
    'name'           => $b['name'],
    'lat'            => (float)$b['lat'],
    'lng'            => (float)$b['lng'],
    'pollution_level'=> $b['pollution_level'],
    'description'    => $b['description'] ?? '',
], $buildings), JSON_HEX_TAG) ?>;
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?= url('assets/js/manager.js') ?>"></script>
</body>
</html>
