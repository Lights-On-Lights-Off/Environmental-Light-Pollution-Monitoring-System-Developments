// BASE_URL is injected by PHP so fetch paths work in any subfolder
const API = (path) => (window.BASE_URL || '') + '/' + path.replace(/^\//, '');

const POLLUTION_COLORS = { low: '#22c55e', moderate: '#f59e0b', high: '#ef4444' };
const cap = s => s ? s.charAt(0).toUpperCase() + s.slice(1) : '';

let userMap    = null;
let mapMarkers = {};

// Local working copy of building data, updated on each simulation tick
const buildings = BUILDINGS_DATA.map(b => ({ ...b }));

function getLevelFromLux(lux) {
    if (lux < 30) return 'low';
    if (lux < 80) return 'moderate';
    return 'high';
}

function buildUserPopup(b) {
    const color = POLLUTION_COLORS[b.pollutionLevel];
    return `
        <div style="font-family:'Outfit',sans-serif;min-width:170px;">
            <div style="font-weight:700;font-size:0.92rem;margin-bottom:6px;">${b.name}</div>
            <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:0.72rem;font-weight:600;background:${color}33;color:${color};border:1px solid ${color}66;">
                ${cap(b.pollutionLevel)}
            </span>
            <div style="font-size:0.78rem;color:#777;margin-top:4px;">${b.description}</div>
            <div style="font-size:0.75rem;color:#aaa;margin-top:4px;">Lux: ${typeof b.lux === 'number' ? b.lux.toFixed(1) : b.lux}</div>
        </div>`;
}

function initUserMap() {
    if (userMap) return;

    const center = [8.359999, 124.868103];
    const bounds = L.latLngBounds([8.355000, 124.860000], [8.365000, 124.876000]);

    userMap = L.map('user-campus-map', {
        center, zoom: 18, minZoom: 16, maxZoom: 20,
        maxBounds: bounds, maxBoundsViscosity: 1.0,
    });

    const colored   = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(userMap);
    const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19 });

    L.control.layers({ 'Colored Map': colored, 'Satellite View': satellite }, null, { position: 'bottomright' }).addTo(userMap);
    userMap.on('baselayerchange', e => userMap.getContainer().classList.toggle('satellite-active', e.name === 'Satellite View'));

    L.rectangle(bounds, { color: '#0d6efd', weight: 2, fillOpacity: 0.05 })
        .addTo(userMap)
        .bindPopup('Northern Bukidnon State College');

    // Place initial markers keyed by building id
    buildings.forEach(b => {
        const color  = POLLUTION_COLORS[b.pollutionLevel];
        const marker = L.circleMarker(b.coordinates, {
            radius: 10, fillColor: color, color: '#fff', weight: 2, fillOpacity: 0.9,
        }).addTo(userMap);
        marker.bindPopup(buildUserPopup(b));
        mapMarkers[b.id] = marker;
    });

    // Sync immediately from DB on load, then every 30 seconds
    syncFromDatabase();
    setInterval(syncFromDatabase, 30000);
}

// Fetches the latest reading per building from the DB and updates all markers.
// Falls back to local random simulation if the DB has no entries yet.
async function syncFromDatabase() {
    try {
        const res  = await fetch(API('api/latest_readings.php'));
        const data = await res.json();

        if (data.success && data.readings.length > 0) {
            const filter = document.getElementById('user-map-filter')?.value || 'all';

            data.readings.forEach(row => {
                const b = buildings.find(x => x.id === parseInt(row.building_id));
                if (!b) return;

                b.lux            = parseFloat(row.lux);
                b.pollutionLevel = row.pollution_level;
                b.online         = !!parseInt(row.online);

                const marker = mapMarkers[b.id];
                if (!marker) return;

                marker.setStyle({ fillColor: POLLUTION_COLORS[b.pollutionLevel] });
                marker.setPopupContent(buildUserPopup(b));

                if (filter === 'all' || b.pollutionLevel === filter) {
                    if (!userMap.hasLayer(marker)) marker.addTo(userMap);
                } else {
                    if (userMap.hasLayer(marker)) userMap.removeLayer(marker);
                }
            });
        } else {
            // No DB data yet — run local simulation so the map still shows live-looking data
            runLocalSimulation();
        }
    } catch (e) {
        // Network error — fall back to local simulation
        runLocalSimulation();
    }
}

// Local fallback simulation used only when the DB has no readings yet
function runLocalSimulation() {
    const filter = document.getElementById('user-map-filter')?.value || 'all';

    buildings.forEach(b => {
        b.lux            = Math.min(150, Math.max(5, b.lux + (Math.random() - 0.5) * 15));
        b.pollutionLevel = getLevelFromLux(b.lux);

        const marker = mapMarkers[b.id];
        if (!marker) return;

        marker.setStyle({ fillColor: POLLUTION_COLORS[b.pollutionLevel] });
        marker.setPopupContent(buildUserPopup(b));

        if (filter === 'all' || b.pollutionLevel === filter) {
            if (!userMap.hasLayer(marker)) marker.addTo(userMap);
        } else {
            if (userMap.hasLayer(marker)) userMap.removeLayer(marker);
        }
    });
}

function applyMapFilter(filter) {
    buildings.forEach(b => {
        const marker = mapMarkers[b.id];
        if (!marker) return;
        if (filter === 'all' || b.pollutionLevel === filter) {
            if (!userMap.hasLayer(marker)) marker.addTo(userMap);
        } else {
            if (userMap.hasLayer(marker)) userMap.removeLayer(marker);
        }
    });
}

document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        const pane = document.getElementById(btn.dataset.tab + '-tab');
        if (pane) pane.classList.add('active');
        if (btn.dataset.tab === 'map') initUserMap();
    });
});

document.getElementById('user-map-filter').addEventListener('change', e => applyMapFilter(e.target.value));
document.getElementById('refresh-user-map-btn').addEventListener('click', async () => {
    await syncFromDatabase();
    applyMapFilter(document.getElementById('user-map-filter').value);
});
document.getElementById('reset-user-map-btn').addEventListener('click', () => userMap?.setView([8.359999, 124.868103], 18));

const pill     = document.getElementById('userPillToggle');
const dropdown = document.getElementById('userDropdown');
pill.addEventListener('click', e => { e.stopPropagation(); dropdown.classList.toggle('open'); });
document.addEventListener('click', () => dropdown.classList.remove('open'));

document.getElementById('data-request-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);

    const payload = {
        action:       'submit',
        location:     fd.get('location'),
        data_type:    fd.get('dataType'),
        purpose:      fd.get('purpose'),
        start_date:   fd.get('startDate'),
        end_date:     fd.get('endDate'),
        notes:        fd.get('additionalNotes'),
        organization: fd.get('organization'),
    };

    const res  = await fetch(API('api/requests.php'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    const data = await res.json();
    const msg  = document.getElementById('form-msg');

    if (data.success) {
        msg.innerHTML = '<div class="alert-msg success">Request submitted successfully!</div>';
        this.reset();
        setTimeout(() => { msg.innerHTML = ''; location.reload(); }, 1500);
    } else {
        msg.innerHTML = `<div class="alert-msg error">${data.message || 'Submission failed'}</div>`;
    }
});

async function deleteRequest(id) {
    if (!confirm('Delete this request?')) return;
    const res  = await fetch(API('api/requests.php'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'delete', id }) });
    const data = await res.json();
    if (data.success) document.getElementById('req-' + id)?.remove();
}

async function downloadCSV(reqId, buildingId, location, startDate, endDate, dataType) {
    const res  = await fetch(API(`api/light_logs.php?building_id=${buildingId}&start=${startDate}&end=${endDate}`));
    const data = await res.json();

    const headers = ['Timestamp', 'Building', 'Lux', 'Pollution Level', 'Online', 'Data Type'];
    const rows    = [headers];

    const logRows = (data.logs && data.logs.length)
        ? data.logs
        : [{ recorded_at: new Date().toISOString(), building_name: location, lux: '—', pollution_level: '—', online: '—' }];

    logRows.forEach(l => rows.push([l.recorded_at, l.building_name, l.lux, l.pollution_level, l.online ? 'Online' : 'Offline', dataType]));

    const meta = [
        `# NBSC Light Pollution Monitoring System`,
        `# Request ID: REQ-${reqId}`,
        `# Location: ${location}`,
        `# Date Range: ${startDate} to ${endDate}`,
        `#`,
    ].join('\n');

    const csv  = meta + '\n' + rows.map(r => r.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const a    = Object.assign(document.createElement('a'), {
        href:     URL.createObjectURL(blob),
        download: `NBSC_LightData_REQ${reqId}.csv`,
    });
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// Animated particle background
(function () {
    const canvas = document.getElementById('bg-canvas');
    const ctx    = canvas.getContext('2d');
    let W, H, particles = [];

    function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
    resize();
    window.addEventListener('resize', resize);

    class P {
        constructor() { this.reset(true); }
        reset(i) {
            this.x = Math.random() * W; this.y = i ? Math.random() * H : H + 10;
            this.r = Math.random() * 1.4 + 0.3; this.vy = -(Math.random() * 0.35 + 0.08);
            this.vx = (Math.random() - 0.5) * 0.12; this.alpha = Math.random() * 0.45 + 0.08;
            this.color = Math.random() > 0.6 ? `rgba(13,110,253,${this.alpha})` : `rgba(255,255,255,${this.alpha * 0.5})`;
        }
        update() { this.x += this.vx; this.y += this.vy; if (this.y < -10) this.reset(false); }
        draw()   { ctx.beginPath(); ctx.arc(this.x, this.y, this.r, 0, Math.PI * 2); ctx.fillStyle = this.color; ctx.fill(); }
    }

    for (let i = 0; i < 100; i++) particles.push(new P());

    function loop() {
        ctx.clearRect(0, 0, W, H);
        ctx.fillStyle = '#252324';
        ctx.fillRect(0, 0, W, H);
        const g = ctx.createRadialGradient(W / 2, H / 2, 0, W / 2, H / 2, W * 0.5);
        g.addColorStop(0, 'rgba(13,110,253,0.05)');
        g.addColorStop(1, 'rgba(37,35,36,0)');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);
        particles.forEach(p => { p.update(); p.draw(); });
        requestAnimationFrame(loop);
    }

    loop();
})();

initUserMap();
