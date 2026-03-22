(function () {
    const canvas = document.getElementById('bg-canvas');
    const ctx    = canvas.getContext('2d');
    let W, H, particles = [];

    function resize() {
        W = canvas.width  = window.innerWidth;
        H = canvas.height = window.innerHeight;
    }

    resize();
    window.addEventListener('resize', resize);

    class Particle {
        constructor() { this.reset(true); }

        reset(init) {
            this.x     = Math.random() * W;
            this.y     = init ? Math.random() * H : H + 10;
            this.r     = Math.random() * 1.6 + 0.3;
            this.vy    = -(Math.random() * 0.4 + 0.1);
            this.vx    = (Math.random() - 0.5) * 0.15;
            this.alpha = Math.random() * 0.5 + 0.1;
            this.color = Math.random() > 0.6
                ? `rgba(13,110,253,${this.alpha})`
                : `rgba(255,255,255,${this.alpha * 0.6})`;
        }

        update() {
            this.x += this.vx;
            this.y += this.vy;
            if (this.y < -10) this.reset(false);
        }

        draw() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.r, 0, Math.PI * 2);
            ctx.fillStyle = this.color;
            ctx.fill();
        }
    }

    for (let i = 0; i < 120; i++) particles.push(new Particle());

    function loop() {
        ctx.clearRect(0, 0, W, H);
        ctx.fillStyle = '#04040a';
        ctx.fillRect(0, 0, W, H);

        const g = ctx.createRadialGradient(W / 2, H / 2, 0, W / 2, H / 2, W * 0.55);
        g.addColorStop(0, 'rgba(13,110,253,0.07)');
        g.addColorStop(1, 'rgba(4,4,10,0)');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);

        particles.forEach(p => { p.update(); p.draw(); });
        requestAnimationFrame(loop);
    }

    loop();
})();
