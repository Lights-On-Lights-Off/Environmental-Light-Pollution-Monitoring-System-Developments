<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// Logged-in users who navigate to the landing page are sent to their dashboard
if (isLoggedIn()) {
    $dest = match($_SESSION['role']) {
        'admin'   => url('admin/admin.php'),
        'manager' => url('manager/manager.php'),
        default   => url('user/user.php'),
    };
    header('Location: ' . $dest);
    exit;
}

$user = null;

// Load all buildings from the database for map markers and the log table
$buildings = $pdo->query("SELECT * FROM buildings ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Environmental Light Pollution Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="<?= url('assets/css/styles.css') ?>" />
</head>
<body class="page-index">

<nav class="navbar">
    <div class="nav-brand">
        <span class="brand-badge"></span>
        Environmental Light Pollution Monitoring System
    </div>
    <div class="nav-links">
        <a href="#" class="nav-btn active" onclick="showSection('home', this)">HOME</a>
        <a href="#" class="nav-btn" onclick="showSection('about', this)">ABOUT</a>
        <a href="#" class="nav-btn" onclick="showSection('purpose', this)">PURPOSE</a>

        <div class="user-menu-wrapper">
            <button class="user-icon-btn" id="userMenuToggle" aria-label="User menu">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                    <path d="M2 14s-1 0-1-1 1-4 7-4 7 3 7 4-1 1-1 1H2z"/>
                </svg>
            </button>

            <div class="user-dropdown" id="userDropdown">
                <?php if ($user): ?>
                    <?php
                    $dashboard = match($user['role']) {
                        'admin'   => '/admin/admin.php',
                        'manager' => '/manager/manager.php',
                        default   => '/user/user.php',
                    };
                    ?>
                    <div class="user-dropdown-header">
                        <span class="user-status-dot"></span> <?= htmlspecialchars($user['name']) ?>
                    </div>
                    <a href="<?= $dashboard ?>" class="user-dropdown-item">Go to Dashboard</a>
                    <a href="<?= url('logout.php') ?>" class="user-dropdown-item">Logout</a>
                <?php else: ?>
                    <div class="user-dropdown-header">
                        <span class="user-status-dot"></span> USER
                    </div>
                    <a href="<?= url('login.php') ?>" class="user-dropdown-item">Login Here</a>
                    <a href="<?= url('login.php') ?>?tab=register" class="user-dropdown-item">Register Here</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div id="section-home">
    <div id="app">

        <div class="filter-bar">
            <div class="filter-group">
                <label class="filter-label" for="pollutionFilter">Filter by Pollution Level:</label>
                <select id="pollutionFilter" class="filter-select">
                    <option value="all">All Buildings</option>
                    <option value="low">Low</option>
                    <option value="moderate">Moderate</option>
                    <option value="high">High (Light Pollution)</option>
                </select>
            </div>
            <a href="<?= $user ? ($dashboard ?? url('user/user.php')) : url('login.php') ?>" class="request-data-btn">Request Data</a>
        </div>

        <div class="top-row">
            <!-- Map container. Leaflet targets this div by id -->
            <div id="map"></div>

            <div class="card-stack">
                <div class="card p-3">
                    <h6>Light Sensor KPIs</h6>
                    <div class="kpis">
                        <div class="kpi"><div class="label">Total Sensors</div><div id="kpiTotal" class="value">0</div></div>
                        <div class="kpi"><div class="label">Online</div><div id="kpiOnline" class="value">0</div></div>
                        <div class="kpi"><div class="label">Offline</div><div id="kpiOffline" class="value">0</div></div>
                    </div>
                </div>
                <div class="card p-3">
                    <h6>Status Distribution</h6>
                    <canvas id="statusChart" style="max-height:200px;"></canvas>
                </div>
                <div class="card p-3">
                    <h6>Campus Status Overview</h6>
                    <canvas id="flowChart" style="max-height:150px;"></canvas>
                </div>
            </div>
        </div>

        <div class="bottom-panel">
            <div id="logTable" class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Light Sensor</th>
                            <th>Status</th>
                            <th>Light Intensity (lux)</th>
                            <th>Pollution Level</th>
                            <th>Sensor State</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody id="logBody"></tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<div id="section-about" class="info-section" style="display:none;">
    <div class="info-page">
        <div class="info-hero">
            <div class="info-hero-badge">ABOUT</div>
            <h1>Light Pollution &amp; This System</h1>
            <p class="info-hero-sub">Understanding the environmental impact of artificial light at Northern Bukidnon State College</p>
        </div>
        <div class="info-grid">
            <div class="info-card">
                <div class="info-card-icon">💡</div>
                <h3>What is Light Pollution?</h3>
                <p>Light pollution is the unwanted or misdirected artificial light that brightens areas where it isn't needed, disturbing the natural darkness. It can affect wildlife, disrupt human sleep and health, and even hide the beauty of the night sky.</p>
            </div>
            <div class="info-card">
                <div class="info-card-icon">🌃</div>
                <h3>Sky Glow</h3>
                <p>The night sky brightens over populated areas, hiding stars and diminishing our connection to the cosmos. Urban and campus lighting contributes significantly to this phenomenon.</p>
            </div>
            <div class="info-card">
                <div class="info-card-icon">🔦</div>
                <h3>Light Trespass</h3>
                <p>Light spilling into spaces where it doesn't belong — from streetlamps entering bedroom windows to floodlights crossing property boundaries — a common but solvable problem.</p>
            </div>
            <div class="info-card">
                <div class="info-card-icon">⚡</div>
                <h3>Over-Illumination</h3>
                <p>Using more light than necessary wastes energy, increases operating costs, and adds unnecessary brightness to the environment. Smart sensing helps identify these inefficiencies.</p>
            </div>
            <div class="info-card">
                <div class="info-card-icon">🗺️</div>
                <h3>About This System</h3>
                <p>The NBSC Light Pollution Monitoring System measures and maps light pollution across campus in real time, helping the community make smarter energy choices and create a greener environment.</p>
            </div>
        </div>
    </div>
</div>

<div id="section-purpose" class="info-section" style="display:none;">
    <div class="info-page">
        <div class="info-hero">
            <div class="info-hero-badge">PURPOSE</div>
            <h1>Why This System Exists</h1>
            <p class="info-hero-sub">Our goals, mission, and the community impact we aim to create at NBSC</p>
        </div>
        <div class="info-grid">
            <div class="info-card">
                <div class="info-card-icon">🎯</div>
                <h3>Our Mission</h3>
                <p>To provide real-time, data-driven insight into campus light pollution so administrators, students, and faculty can make informed decisions that reduce environmental impact and improve campus life.</p>
            </div>
            <div class="info-card">
                <div class="info-card-icon">🌿</div>
                <h3>Environmental Responsibility</h3>
                <p>Excessive lighting harms nocturnal wildlife, disrupts ecosystems, and wastes energy. By monitoring and reducing unnecessary light output, NBSC moves toward a more sustainable future.</p>
            </div>
            <div class="info-card">
                <div class="info-card-icon">📊</div>
                <h3>Data-Driven Decisions</h3>
                <p>Rather than guessing where lighting improvements are needed, this system provides precise lux readings per building — empowering campus management with actionable, evidence-based data.</p>
            </div>
            <div class="info-card">
                <div class="info-card-icon">💰</div>
                <h3>Energy Savings</h3>
                <p>Identifying over-illuminated areas leads directly to reduced electricity consumption. Smarter lighting schedules and targeted adjustments can yield significant cost savings for the institution.</p>
            </div>
            <div class="info-card">
                <div class="info-card-icon">🎓</div>
                <h3>Academic Value</h3>
                <p>This system serves as a living laboratory for students in environmental science, IT, and engineering — offering hands-on exposure to IoT sensing, data visualization, and environmental monitoring.</p>
            </div>
            <div class="info-card">
                <div class="info-card-icon">🌟</div>
                <h3>Community Awareness</h3>
                <p>By making light pollution data visible and accessible, we raise awareness among the broader NBSC community — inspiring a culture of environmental consciousness that extends beyond the campus.</p>
            </div>
        </div>
    </div>
</div>

<!-- Buildings data passed to JavaScript as a JSON array -->
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

<script>
function showSection(name, btn) {
    document.getElementById('section-home').style.display    = 'none';
    document.getElementById('section-about').style.display   = 'none';
    document.getElementById('section-purpose').style.display = 'none';
    document.getElementById('section-' + name).style.display = 'block';
    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    window.scrollTo(0, 0);
}
</script>

<!-- Load Leaflet before main.js so the L global is available -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/main.js') ?>"></script>
</body>
</html>
