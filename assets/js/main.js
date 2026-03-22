// BASE_URL is injected by PHP so fetch paths work in any subfolder
const API = (path) => (window.BASE_URL || '') + '/' + path.replace(/^\//, '');

// CAMPUS_DATA is provided by index.php as a PHP-generated JSON array
const campusBuildings = BUILDINGS_DATA.map(b => ({ ...b }));

const CAMPUS_CENTER  = [8.359999, 124.868103];
const CAMPUS_BOUNDS  = L.latLngBounds([8.355000, 124.860000], [8.365000, 124.876000]);

const POLLUTION_COLORS = { low: '#22c55e', moderate: '#f59e0b', high: '#ef4444' };
const cap = s => s ? s.charAt(0).toUpperCase() + s.slice(1) : '';

// Initialize the Leaflet map with campus bounds and rotation support
const map = L.map('map', {
    center: CAMPUS_CENTER,
    zoom: 18,
    minZoom: 16,
    maxZoom: 20,
    maxBounds: CAMPUS_BOUNDS,
    maxBoundsViscosity: 1.0,
});

L.rectangle(CAMPUS_BOUNDS, {
    color: '#0d6efd', weight: 2, fillOpacity: 0.05
}).addTo(map).bindPopup('Northern Bukidnon State College');

const colored = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}).addTo(map);

const satellite = L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    { maxZoom: 19 }
);

L.control.layers(
    { 'Colored Map': colored, 'Satellite View': satellite },
    null,
    { position: 'bottomright' }
).addTo(map);

map.on('baselayerchange', e => {
    map.getContainer().classList.toggle('satellite-active', e.name === 'Satellite View');
});

// Build popup HTML from a building object
function buildPopupHTML(b) {
    const color = POLLUTION_COLORS[b.pollutionLevel];
    return `
        <div style="font-family:'Outfit',sans-serif;min-width:180px;">
            <div style="font-weight:700;font-size:0.95rem;margin-bottom:6px;">${b.name}</div>
            <div style="margin-bottom:4px;">
                <span style="color:#888;font-size:0.78rem;">Pollution Level:</span>
                <span style="display:inline-block;margin-left:6px;padding:2px 8px;border-radius:10px;
                    font-size:0.75rem;font-weight:600;background:${color}33;color:${color};border:1px solid ${color}66;">
                    ${cap(b.pollutionLevel)}
                </span>
            </div>
            <div style="font-size:0.8rem;color:#777;margin-top:4px;">${b.description}</div>
        </div>`;
}

// Place a circle marker for each building and store a reference by building id
const lightMarkers = {};
campusBuildings.forEach(b => {
    const marker = L.circleMarker(b.coordinates, {
        radius: 10,
        fillColor: POLLUTION_COLORS[b.pollutionLevel],
        color: '#fff',
        weight: 2,
        fillOpacity: 0.9,
    }).addTo(map);
    marker.bindPopup(buildPopupHTML(b));
    lightMarkers[b.id] = marker;
});

// Status distribution pie chart
const statusChart = new Chart(document.getElementById('statusChart'), {
    type: 'pie',
    data: {
        labels: ['Low', 'Moderate', 'High'],
        datasets: [{ data: [0, 0, 0], backgroundColor: [POLLUTION_COLORS.low, POLLUTION_COLORS.moderate, POLLUTION_COLORS.high] }],
    },
    options: { plugins: { legend: { labels: { color: '#fff' } } } },
});

function updateKPIsAndStatusChart() {
    const total   = campusBuildings.length;
    const online  = campusBuildings.filter(b => b.online).length;
    const counts  = { low: 0, moderate: 0, high: 0 };
    campusBuildings.forEach(b => counts[b.pollutionLevel]++);

    document.getElementById('kpiTotal').textContent   = total;
    document.getElementById('kpiOnline').textContent  = online;
    document.getElementById('kpiOffline').textContent = total - online;

    statusChart.data.datasets[0].data = [counts.low, counts.moderate, counts.high];
    statusChart.update();
}

updateKPIsAndStatusChart();

// Light trend line chart showing the last 10 readings per building
const lightTrendChart = new Chart(document.getElementById('flowChart'), {
    type: 'line',
    data: {
        labels: [],
        datasets: campusBuildings.map(b => ({
            label: b.name, data: [], borderWidth: 2, fill: false,
            borderColor: POLLUTION_COLORS[b.pollutionLevel],
        })),
    },
    options: {
        plugins: { legend: { labels: { color: '#fff', font: { size: 10 } } } },
        scales: {
            y: { title: { display: true, text: 'Light Intensity (lux)', color: '#aaa' }, ticks: { color: '#aaa' }, grid: { color: 'rgba(255,255,255,0.05)' } },
            x: { ticks: { color: '#aaa' }, grid: { color: 'rgba(255,255,255,0.05)' } },
        },
    },
});

function getLevelFromLux(lux) {
    if (lux < 30)  return 'low';
    if (lux < 80)  return 'moderate';
    return 'high';
}

function getPollutionLabel(level) {
    return { low: 'Low', moderate: 'Moderate', high: 'High (Light Pollution)' }[level] || level;
}

function getLogTimestamp() {
    const now = new Date();
    return now.getFullYear() + '-'
        + String(now.getMonth() + 1).padStart(2, '0') + '-'
        + String(now.getDate()).padStart(2, '0') + ' '
        + String(now.getHours()).padStart(2, '0') + ':'
        + String(now.getMinutes()).padStart(2, '0') + ':'
        + String(now.getSeconds()).padStart(2, '0');
}

const logBody = document.getElementById('logBody');

function addLightLogEntry(b) {
    if (!logBody) return;
    const color = POLLUTION_COLORS[b.pollutionLevel];
    const row = document.createElement('tr');
    row.innerHTML = `
        <td>${b.name}</td>
        <td>
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${color};margin-right:6px;"></span>
            ${cap(b.pollutionLevel)}
        </td>
        <td>${b.lux.toFixed(1)}</td>
        <td>${getPollutionLabel(b.pollutionLevel)}</td>
        <td>${b.online ? 'Online' : 'Offline'}</td>
        <td>${getLogTimestamp()}</td>`;
    logBody.prepend(row);
    if (logBody.children.length > 50) logBody.removeChild(logBody.lastChild);
}

// Accumulate log entries for the batch that saves to the database every minute
let pendingLogEntries = [];

// Simulate sensor updates every 10 seconds; batch-save to DB every 60 seconds
let secondsSinceLastSave = 0;

setInterval(() => {
    const time = new Date().toLocaleTimeString();
    lightTrendChart.data.labels.push(time);
    if (lightTrendChart.data.labels.length > 10) lightTrendChart.data.labels.shift();

    campusBuildings.forEach((b, index) => {
        b.lux            = Math.min(150, Math.max(5, b.lux + (Math.random() - 0.5) * 15));
        b.pollutionLevel = getLevelFromLux(b.lux);

        lightTrendChart.data.datasets[index].data.push(b.lux.toFixed(1));
        if (lightTrendChart.data.datasets[index].data.length > 10) {
            lightTrendChart.data.datasets[index].data.shift();
        }

        lightMarkers[b.id].setStyle({ fillColor: POLLUTION_COLORS[b.pollutionLevel] });
        lightMarkers[b.id].setPopupContent(buildPopupHTML(b));
        addLightLogEntry(b);

        pendingLogEntries.push({
            building_id:     b.id,
            lux:             parseFloat(b.lux.toFixed(2)),
            pollution_level: b.pollutionLevel,
            online:          b.online ? 1 : 0,
        });
    });

    lightTrendChart.update();
    updateKPIsAndStatusChart();

    secondsSinceLastSave += 10;
    if (secondsSinceLastSave >= 60) {
        saveLogsToDatabase();
        secondsSinceLastSave = 0;
    }
}, 10000);

// POST the accumulated entries to the server and clear the local batch
function saveLogsToDatabase() {
    if (!pendingLogEntries.length) return;
    const payload = [...pendingLogEntries];
    pendingLogEntries = [];

    fetch(API('api/log_readings.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ entries: payload }),
    }).catch(() => {});
}

// Dropdown toggle
const userMenuToggle = document.getElementById('userMenuToggle');
const userDropdown   = document.getElementById('userDropdown');

userMenuToggle.addEventListener('click', e => {
    e.stopPropagation();
    userDropdown.classList.toggle('open');
});

document.addEventListener('click', () => userDropdown.classList.remove('open'));

// Filter markers by pollution level
document.getElementById('pollutionFilter').addEventListener('change', function () {
    const val = this.value;
    campusBuildings.forEach(b => {
        if (val === 'all' || b.pollutionLevel === val) {
            lightMarkers[b.id].addTo(map);
        } else {
            map.removeLayer(lightMarkers[b.id]);
        }
    });
});
