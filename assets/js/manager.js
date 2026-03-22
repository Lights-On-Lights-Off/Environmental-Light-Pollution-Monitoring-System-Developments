// BASE_URL is injected by PHP so fetch paths work in any subfolder
const API = (path) => (window.BASE_URL || '') + '/' + path.replace(/^\//, '');

const POLLUTION_COLORS = { low: '#22c55e', moderate: '#f59e0b', high: '#ef4444' };
const cap = s => s ? s.charAt(0).toUpperCase() + s.slice(1) : '';

let editingId    = null;
let pickerMap    = null;
let pickerMarker = null;
let adminMap     = null;
let adminMarkers = [];

// Sidebar section navigation
document.querySelectorAll('.nav-item[data-section]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.nav-item').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
        document.getElementById('section-' + btn.dataset.section)?.classList.add('active');
        document.getElementById('page-title').textContent = {
            dashboard:     'Dashboard Overview',
            requests:      'Data Requests',
            buildings:     'Building Management',
            map:           'Campus Map',
            'recycle-bin': 'Recycle Bin',
        }[btn.dataset.section] || 'Dashboard';
        if (btn.dataset.section === 'map') initManagerMap();
    });
});

document.getElementById('menuToggle').addEventListener('click', () => {
    document.querySelector('.sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
});

document.getElementById('sidebarOverlay').addEventListener('click', () => {
    document.querySelector('.sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
});

function filterRequests() {
    const val = document.getElementById('status-filter').value;
    document.querySelectorAll('#requests-tbody tr').forEach(row => {
        row.style.display = (val === 'all' || row.dataset.status === val) ? '' : 'none';
    });
}

function filterBuildings() {
    const val = document.getElementById('pollution-filter').value;
    document.querySelectorAll('.building-card').forEach(card => {
        card.style.display = (val === 'all' || card.dataset.level === val) ? '' : 'none';
    });
}

async function reviewRequest(id, action) {
    if (!confirm(`${cap(action)} this request?`)) return;
    const res  = await fetch(API('api/requests.php'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action, id }) });
    const data = await res.json();
    if (data.success) location.reload();
}

async function softDeleteRequest(id) {
    if (!confirm('Move this request to the recycle bin?')) return;
    const res  = await fetch(API('api/requests.php'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'delete', id }) });
    const data = await res.json();
    if (data.success) location.reload();
}

async function restoreRequest(id) {
    const res  = await fetch(API('api/requests.php'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'restore', id }) });
    const data = await res.json();
    if (data.success) document.getElementById('rbin-' + id)?.remove();
}

async function permanentDelete(id) {
    if (!confirm('Permanently delete? This cannot be undone.')) return;
    const res  = await fetch(API('api/requests.php'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'permanent_delete', id }) });
    const data = await res.json();
    if (data.success) document.getElementById('rbin-' + id)?.remove();
}

async function emptyBin() {
    if (!confirm('Permanently delete ALL items in the recycle bin?')) return;
    document.querySelectorAll('[id^="rbin-"]').forEach(async row => {
        const id = parseInt(row.id.replace('rbin-', ''));
        await fetch(API('api/requests.php'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'permanent_delete', id }) });
        row.remove();
    });
}

function openBuildingModal(isEdit = false) {
    editingId = isEdit ? editingId : null;
    document.getElementById('modal-title').textContent = isEdit ? 'Edit Building' : 'Add Building';
    document.getElementById('building-modal').classList.add('open');
    setTimeout(initPickerMap, 80);
}

function closeBuildingModal() {
    document.getElementById('building-modal').classList.remove('open');
    document.getElementById('b-name').value    = '';
    document.getElementById('b-desc').value    = '';
    document.getElementById('b-lat').value     = '';
    document.getElementById('b-lng').value     = '';
    document.getElementById('b-level').value   = 'moderate';
    document.getElementById('loc-text').textContent = 'No location selected';
    document.getElementById('loc-display').classList.remove('selected');
    editingId = null;
    if (pickerMap) { pickerMap.remove(); pickerMap = null; pickerMarker = null; }
}

function editBuilding(id, data) {
    editingId = id;
    document.getElementById('b-name').value  = data.name;
    document.getElementById('b-lat').value   = data.lat;
    document.getElementById('b-lng').value   = data.lng;
    document.getElementById('b-level').value = data.pollution_level;
    document.getElementById('b-desc').value  = data.description;
    openBuildingModal(true);
}

function initPickerMap() {
    if (pickerMap) return;

    pickerMap = L.map('location-picker-map', { center: [8.3595, 124.8675], zoom: 18, attributionControl: false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, subdomains: 'abc' }).addTo(pickerMap);

    const existLat = parseFloat(document.getElementById('b-lat').value);
    const existLng = parseFloat(document.getElementById('b-lng').value);
    if (!isNaN(existLat) && !isNaN(existLng)) {
        placePin(existLat, existLng);
        pickerMap.setView([existLat, existLng], 18);
    }

    pickerMap.on('click', e => placePin(e.latlng.lat, e.latlng.lng));
}

function placePin(lat, lng) {
    document.getElementById('b-lat').value = lat;
    document.getElementById('b-lng').value = lng;
    document.getElementById('loc-text').textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    document.getElementById('loc-display').classList.add('selected');

    const icon = L.divIcon({
        className: '',
        html: `<div style="width:18px;height:18px;border-radius:50%;background:#0d6efd;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.5);"></div>`,
        iconSize: [18, 18], iconAnchor: [9, 9],
    });

    if (pickerMarker) {
        pickerMarker.setLatLng([lat, lng]);
    } else {
        pickerMarker = L.marker([lat, lng], { icon, draggable: true }).addTo(pickerMap);
        pickerMarker.on('dragend', e => { const { lat, lng } = e.target.getLatLng(); placePin(lat, lng); });
    }
}

async function saveBuilding() {
    const lat = parseFloat(document.getElementById('b-lat').value);
    const lng = parseFloat(document.getElementById('b-lng').value);
    if (isNaN(lat) || isNaN(lng)) { alert('Please pin a location on the map.'); return; }

    const payload = {
        name:            document.getElementById('b-name').value.trim(),
        lat, lng,
        pollution_level: document.getElementById('b-level').value,
        description:     document.getElementById('b-desc').value.trim(),
    };

    if (!payload.name) { alert('Building name is required.'); return; }

    const method = editingId ? 'PUT' : 'POST';
    if (editingId) payload.id = editingId;

    const res  = await fetch(API('api/buildings.php'), { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    const data = await res.json();

    if (data.success) { closeBuildingModal(); location.reload(); }
    else alert(data.message || 'Save failed');
}

async function deleteBuilding(id, name) {
    if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;
    const res  = await fetch(API('api/buildings.php'), { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) });
    const data = await res.json();
    if (data.success) document.getElementById('bcard-' + id)?.remove();
}

function initManagerMap() {
    if (adminMap) return;

    adminMap = L.map('manager-campus-map', { center: [8.3595, 124.8675], zoom: 18, minZoom: 14, maxZoom: 19 });
    const std = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(adminMap);
    const sat = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19 });

    L.control.layers({ 'Standard': std, 'Satellite': sat }, null, { position: 'bottomright' }).addTo(adminMap);
    adminMap.on('baselayerchange', e => adminMap.getContainer().classList.toggle('satellite-active', e.name === 'Satellite'));

    // Sync from DB immediately when the map section is opened so markers are current
    syncBuildingLevels().then(() => renderManagerMarkers('all'));
}

function renderManagerMarkers(filter) {
    adminMarkers.forEach(m => adminMap.removeLayer(m));
    adminMarkers = [];

    BUILDINGS.filter(b => filter === 'all' || b.pollution_level === filter).forEach(b => {
        const color = POLLUTION_COLORS[b.pollution_level];
        const m = L.circleMarker([b.lat, b.lng], { radius: 14, fillColor: color, color: '#fff', weight: 2, fillOpacity: 0.85 }).addTo(adminMap);
        m.bindPopup(`
            <div style="font-family:'Outfit',sans-serif;min-width:180px;">
                <div style="font-weight:700;font-size:14px;">${b.name}</div>
                <div style="font-size:13px;margin-top:4px;">Pollution: <span style="color:${color};font-weight:600;">${cap(b.pollution_level)}</span></div>
                <div style="font-size:12px;color:#64748b;margin-top:4px;">${b.description}</div>
            </div>`);
        adminMarkers.push(m);
    });
}

function filterMapMarkers(val) { if (adminMap) renderManagerMarkers(val); }

document.getElementById('building-modal').addEventListener('click', e => { if (e.target === e.currentTarget) closeBuildingModal(); });

// Animated background
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

// Fetches the latest building levels from DB and updates building cards and map markers.
// Runs every 30 seconds so the manager sees live pollution data without refreshing.
async function syncBuildingLevels() {
    try {
        const res  = await fetch(API('api/latest_readings.php'));
        const data = await res.json();
        if (!data.success || !data.readings.length) return;

        data.readings.forEach(row => {
            const id    = parseInt(row.building_id);
            const level = row.pollution_level;
            const color = POLLUTION_COLORS[level];

            // Update the local BUILDINGS array so map re-renders use fresh data
            const b = BUILDINGS.find(x => x.id === id);
            if (b) {
                b.pollution_level = level;
                b.lux = parseFloat(row.lux);
            }

            // Update the badge on the building card
            const card  = document.getElementById('bcard-' + id);
            if (card) {
                const badge = card.querySelector('.badge');
                if (badge) {
                    badge.className  = 'badge ' + level;
                    badge.textContent = level.charAt(0).toUpperCase() + level.slice(1);
                }
                card.dataset.level = level;
            }
        });

        // Re-render map markers if the map is open so colors update
        if (adminMap) {
            const currentFilter = document.getElementById('map-filter')?.value || 'all';
            renderManagerMarkers(currentFilter);
        }
    } catch (e) {}
}

// Run once immediately on page load so data is current from the first render,
// then keep syncing every 30 seconds
syncBuildingLevels();
setInterval(syncBuildingLevels, 30000);
