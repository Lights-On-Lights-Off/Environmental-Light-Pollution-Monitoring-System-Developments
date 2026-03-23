// BASE_URL is injected by PHP so fetch paths work in any subfolder
const API = (path) => (window.BASE_URL || '') + '/' + path.replace(/^\//, '');

let editingUserId = null;

// Section navigation
document.querySelectorAll('.nav-item[data-section]').forEach(btn => {
    btn.addEventListener('click', () => {
        navigate(btn.dataset.section);
        // Close sidebar drawer on mobile after nav item is tapped
        document.getElementById('adminSidebar')?.classList.remove('open');
        document.getElementById('sidebarOverlay')?.classList.remove('open');
    });
});

// Hamburger menu toggle for mobile
document.getElementById('menuToggle')?.addEventListener('click', () => {
    document.getElementById('adminSidebar')?.classList.toggle('open');
    document.getElementById('sidebarOverlay')?.classList.toggle('open');
});

document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
    document.getElementById('adminSidebar')?.classList.remove('open');
    document.getElementById('sidebarOverlay')?.classList.remove('open');
});

function navigate(section) {
    document.querySelectorAll('.nav-item').forEach(b => b.classList.remove('active'));
    document.querySelector(`[data-section="${section}"]`)?.classList.add('active');
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.getElementById('section-' + section)?.classList.add('active');

    const labels = { dashboard: 'Dashboard', users: 'Users Management', activity: 'Activity Log', settings: 'System Settings' };
    document.getElementById('page-title').textContent = labels[section] || section;
    const subs = { dashboard: 'System overview', users: 'Manage accounts', activity: 'All system events', settings: 'System configuration' };
    const subEl = document.getElementById('topbar-sub');
    if (subEl) subEl.textContent = subs[section] || '';
}

function filterUsers() {
    const val = document.getElementById('role-filter').value;
    document.querySelectorAll('#users-tbody tr').forEach(row => {
        row.style.display = (val === 'all' || row.dataset.role === val) ? '' : 'none';
    });
}

function filterActivityLog() {
    const role = document.getElementById('log-role-filter').value;
    const type = document.getElementById('log-type-filter').value;
    document.querySelectorAll('#activity-tbody tr').forEach(row => {
        const roleMatch = role === 'all' || row.dataset.role === role;
        const typeMatch = type === 'all' || row.dataset.type === type;
        row.style.display = (roleMatch && typeMatch) ? '' : 'none';
    });
}

function openRoleModal(id, name, role) {
    editingUserId = id;
    document.getElementById('role-modal-name').textContent = name;
    document.getElementById('role-select').value = role;
    document.getElementById('role-modal').classList.add('open');
}

async function saveRole() {
    if (!editingUserId) return;
    const role = document.getElementById('role-select').value;
    const res  = await fetch(API('api/admin.php'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'change_role', id: editingUserId, role }) });
    const data = await res.json();
    if (data.success) location.reload();
    else alert(data.message || 'Failed');
}

async function deleteUser(id, name) {
    if (!confirm(`Delete the account for "${name}"? This cannot be undone.`)) return;
    const res  = await fetch(API('api/admin.php'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'delete_user', id }) });
    const data = await res.json();
    if (data.success) location.reload();
}

function openAddUserModal() {
    document.getElementById('add-name').value     = '';
    document.getElementById('add-email').value    = '';
    document.getElementById('add-password').value = '';
    document.getElementById('add-role').value     = 'user';
    document.getElementById('add-user-error').style.display = 'none';
    document.getElementById('add-user-modal').classList.add('open');
}

async function submitAddUser() {
    const name     = document.getElementById('add-name').value.trim();
    const email    = document.getElementById('add-email').value.trim();
    const password = document.getElementById('add-password').value;
    const role     = document.getElementById('add-role').value;
    const errEl    = document.getElementById('add-user-error');

    if (!name || !email || password.length < 6) {
        errEl.textContent   = 'All fields required; password must be 6+ characters.';
        errEl.style.display = 'block';
        return;
    }

    const res  = await fetch(API('api/admin.php'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'add_user', name, email, password, role }) });
    const data = await res.json();

    if (data.success) location.reload();
    else { errEl.textContent = data.message || 'Failed'; errEl.style.display = 'block'; }
}

async function clearRequests() {
    if (!confirm('Permanently delete all soft-deleted requests?')) return;
    await fetch(API('api/admin.php'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'clear_requests' }) });
    alert('Done');
}

async function clearNotifications() {
    if (!confirm('Clear all notifications?')) return;
    await fetch(API('api/admin.php'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'clear_notifications' }) });
    alert('Done');
}

async function resetBuildings() {
    if (!confirm('Reset buildings to default? All custom buildings will be removed.')) return;
    const res  = await fetch(API('api/admin.php'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'reset_buildings' }) });
    const data = await res.json();
    if (data.success) alert('Buildings reset to default.');
}

document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});

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
