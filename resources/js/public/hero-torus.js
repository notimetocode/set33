/**
 * Interactive particle torus for the public home hero.
 * Idle spin + soft tilt + hover scatter (Harmonic-inspired).
 * Interaction: only over [data-hero-torus-hit]. Drawing: full hero canvas.
 */
export function initHeroTorus() {
    const canvas = document.querySelector('[data-hero-torus]');
    const hit = document.querySelector('[data-hero-torus-hit]');
    const hero = canvas?.closest('.page-home__hero');

    if (!(canvas instanceof HTMLCanvasElement) || !(hit instanceof HTMLElement) || !(hero instanceof HTMLElement)) {
        return;
    }

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    if (reducedMotion.matches) {
        drawStaticFrame(canvas, hit, hero);
        return;
    }

    const ctx = canvas.getContext('2d', { alpha: true });

    if (!ctx) {
        return;
    }

    const majorR = 1.05;
    const minorR = 0.42;
    const uSteps = 72;
    const vSteps = 36;
    const points = buildTorusPoints(majorR, minorR, uSteps, vSteps);

    let width = 0;
    let height = 0;
    let dpr = 1;
    let rafId = 0;
    let running = true;
    let time = 0;

    /** Torus center & base scale in canvas CSS pixels */
    let originX = 0;
    let originY = 0;
    let baseScale = 120;

    let rotX = 0.55;
    let rotY = 0.35;
    let rotZ = 0.1;
    let spinY = 0;
    let spinX = 0;

    let targetTiltX = 0;
    let targetTiltY = 0;
    let tiltX = 0;
    let tiltY = 0;
    let hoverBoost = 0;
    let scatter = 0;
    let proximity = 0;
    let pointerInside = false;

    const pointer = { x: 0.5, y: 0.5 };

    function resize() {
        const heroRect = hero.getBoundingClientRect();
        const hitRect = hit.getBoundingClientRect();
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        width = Math.max(1, Math.floor(heroRect.width));
        height = Math.max(1, Math.floor(heroRect.height));
        canvas.width = Math.floor(width * dpr);
        canvas.height = Math.floor(height * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        originX = hitRect.left - heroRect.left + hitRect.width * 0.5;
        originY = hitRect.top - heroRect.top + hitRect.height * 0.5;
        baseScale = Math.min(hitRect.width, hitRect.height) * 0.2;
    }

    /**
     * 1 at torus center, 0 near the hit-zone edge.
     * @param {number} nx normalized x 0..1
     * @param {number} ny normalized y 0..1
     */
    function proximityFromCenter(nx, ny) {
        const dx = nx - 0.5;
        const dy = ny - 0.5;
        // Normalize so ~0.45 radius → 0 (edge of interactive core)
        const dist = Math.hypot(dx, dy) / 0.48;
        const raw = Math.max(0, 1 - dist);

        return raw * raw;
    }

    function syncWordOpacity(amount) {
        // Keep «Данные» while scatter is mild; ease into «Хаос» after that
        const chaos = smoothstep(0.22, 0.55, amount);
        const dataOpacity = 0.12 * (1 - chaos);
        const chaosOpacity = 0.14 * chaos;
        hit.style.setProperty('--torus-data-opacity', String(dataOpacity));
        hit.style.setProperty('--torus-chaos-opacity', String(chaosOpacity));
    }

    function onPointerMove(event) {
        const rect = hit.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width;
        const y = (event.clientY - rect.top) / rect.height;
        pointer.x = Math.min(1, Math.max(0, x));
        pointer.y = Math.min(1, Math.max(0, y));
        pointerInside = true;
        proximity = proximityFromCenter(pointer.x, pointer.y);
        targetTiltY = (pointer.x - 0.5) * 0.22 * proximity;
        targetTiltX = (pointer.y - 0.5) * -0.16 * proximity;
    }

    function onPointerLeave() {
        pointerInside = false;
        proximity = 0;
        targetTiltX = 0;
        targetTiltY = 0;
    }

    function tick() {
        if (!running) {
            return;
        }

        time += 1;

        const baseSpin = 0.006;
        const boost = 0.0025 * hoverBoost;
        spinY += baseSpin + boost;
        spinX += baseSpin * 0.28 + boost * 0.2;

        tiltX += (targetTiltX - tiltX) * 0.045;
        tiltY += (targetTiltY - tiltY) * 0.045;
        hoverBoost += ((pointerInside ? proximity : 0) - hoverBoost) * 0.05;

        const scatterTarget = pointerInside ? proximity : 0;
        // Symmetric ease so Chaos ↔ Данные both feel smooth
        scatter += (scatterTarget - scatter) * 0.1;
        syncWordOpacity(scatter);

        const rx = rotX + spinX + tiltX;
        const ry = rotY + spinY + tiltY;
        const rz = rotZ + spinY * 0.12;

        drawFrame(
            ctx,
            width,
            height,
            points,
            rx,
            ry,
            rz,
            scatter,
            pointer,
            time,
            originX,
            originY,
            baseScale,
        );
        rafId = window.requestAnimationFrame(tick);
    }

    function onVisibility() {
        if (document.hidden) {
            running = false;
            window.cancelAnimationFrame(rafId);
            return;
        }

        if (!running) {
            running = true;
            rafId = window.requestAnimationFrame(tick);
        }
    }

    function onReducedMotionChange() {
        if (reducedMotion.matches) {
            running = false;
            window.cancelAnimationFrame(rafId);
            drawStaticFrame(canvas, hit, hero);
            return;
        }

        running = true;
        resize();
        rafId = window.requestAnimationFrame(tick);
    }

    resize();
    syncWordOpacity(0);
    rafId = window.requestAnimationFrame(tick);

    hit.addEventListener('pointermove', onPointerMove);
    hit.addEventListener('pointerenter', onPointerMove);
    hit.addEventListener('pointerleave', onPointerLeave);
    window.addEventListener('resize', resize);
    document.addEventListener('visibilitychange', onVisibility);
    reducedMotion.addEventListener('change', onReducedMotionChange);
}

/**
 * @typedef {{ x: number, y: number, z: number, nx: number, ny: number, nz: number, jitter: number }} TorusPoint
 */

/**
 * @param {number} majorR
 * @param {number} minorR
 * @param {number} uSteps
 * @param {number} vSteps
 * @returns {TorusPoint[]}
 */
function buildTorusPoints(majorR, minorR, uSteps, vSteps) {
    /** @type {TorusPoint[]} */
    const points = [];

    for (let i = 0; i < uSteps; i += 1) {
        const u = (i / uSteps) * Math.PI * 2;
        const cosU = Math.cos(u);
        const sinU = Math.sin(u);

        for (let j = 0; j < vSteps; j += 1) {
            const v = (j / vSteps) * Math.PI * 2;
            const cosV = Math.cos(v);
            const sinV = Math.sin(v);
            const jitter = 0.55 + hash2(i, j) * 0.9;

            points.push({
                x: (majorR + minorR * cosV) * cosU,
                y: minorR * sinV,
                z: (majorR + minorR * cosV) * sinU,
                nx: cosV * cosU,
                ny: sinV,
                nz: cosV * sinU,
                jitter,
            });
        }
    }

    return points;
}

/**
 * @param {number} a
 * @param {number} b
 * @returns {number}
 */
function hash2(a, b) {
    const n = Math.sin(a * 127.1 + b * 311.7) * 43758.5453;

    return n - Math.floor(n);
}

/**
 * Hermite smoothstep from edge0 → edge1.
 * @param {number} edge0
 * @param {number} edge1
 * @param {number} x
 * @returns {number}
 */
function smoothstep(edge0, edge1, x) {
    const t = Math.min(1, Math.max(0, (x - edge0) / (edge1 - edge0)));

    return t * t * (3 - 2 * t);
}

/**
 * @param {CanvasRenderingContext2D} ctx
 * @param {number} width
 * @param {number} height
 * @param {TorusPoint[]} points
 * @param {number} rotX
 * @param {number} rotY
 * @param {number} rotZ
 * @param {number} scatter
 * @param {{ x: number, y: number }} pointer
 * @param {number} time
 * @param {number} originX
 * @param {number} originY
 * @param {number} baseScale
 */
function drawFrame(
    ctx,
    width,
    height,
    points,
    rotX,
    rotY,
    rotZ,
    scatter,
    pointer,
    time,
    originX,
    originY,
    baseScale,
) {
    ctx.clearRect(0, 0, width, height);

    const scale = baseScale;
    const cx = originX;
    const cy = originY;
    const cosX = Math.cos(rotX);
    const sinX = Math.sin(rotX);
    const cosY = Math.cos(rotY);
    const sinY = Math.sin(rotY);
    const cosZ = Math.cos(rotZ);
    const sinZ = Math.sin(rotZ);

    const scatterAmp = 1.35 * scatter;
    const cursorPush = 0.55 * scatter;

    /** @type {{ sx: number, sy: number, depth: number, size: number }[]} */
    const projected = [];

    for (let i = 0; i < points.length; i += 1) {
        const p = points[i];
        const wobble = 1 + Math.sin(time * 0.03 + p.jitter * 12.5) * 0.08 * scatter;
        const dist = scatterAmp * p.jitter * wobble;

        let x = p.x + p.nx * dist;
        let y = p.y + p.ny * dist;
        let z = p.z + p.nz * dist;

        if (scatter > 0.01) {
            const len = Math.hypot(x, y, z) || 1;
            const burst = cursorPush * p.jitter * 0.55;
            x += (x / len) * burst;
            y += (y / len) * burst;
            z += (z / len) * burst;
        }

        let y1 = y * cosX - z * sinX;
        let z1 = y * sinX + z * cosX;
        y = y1;
        z = z1;

        let x1 = x * cosY + z * sinY;
        z1 = -x * sinY + z * cosY;
        x = x1;
        z = z1;

        x1 = x * cosZ - y * sinZ;
        y1 = x * sinZ + y * cosZ;
        x = x1;
        y = y1;

        if (scatter > 0.01) {
            const px = (pointer.x - 0.5) * 2.4;
            const py = (pointer.y - 0.5) * 2.4;
            const dx = x - px;
            const dy = y - py;
            const d2 = dx * dx + dy * dy + 0.35;
            const force = (cursorPush * 0.55) / d2;
            x += dx * force;
            y += dy * force;
        }

        const perspective = 3.2 / (3.2 + z);
        const t = (z + 1.6) / 3.2;
        const clamped = Math.min(1, Math.max(0, t));

        projected.push({
            sx: cx + x * scale * perspective,
            sy: cy + y * scale * perspective,
            depth: z,
            size: (1.05 + (1 - clamped) * 1.7) * (1 + scatter * 0.12 * p.jitter),
        });
    }

    projected.sort((a, b) => a.depth - b.depth);

    // Sync with «Хаос» word fade: dark gray at rest → light gray when scattered
    const chaos = smoothstep(0.22, 0.55, scatter);

    for (let i = 0; i < projected.length; i += 1) {
        const p = projected[i];
        const t = (p.depth + 1.6) / 3.2;
        const clamped = Math.min(1, Math.max(0, t));
        const alpha = (0.22 + (1 - clamped) * 0.72) * (1 - scatter * 0.12);

        ctx.beginPath();
        ctx.fillStyle = pointColor(clamped, alpha, chaos);
        ctx.arc(p.sx, p.sy, p.size, 0, Math.PI * 2);
        ctx.fill();
    }
}

/**
 * Dark gray at rest («Данные»), light gray in chaos.
 * Depth still modulates shade slightly for volume.
 * @param {number} depthT
 * @param {number} alpha
 * @param {number} chaos 0..1
 * @returns {string}
 */
function pointColor(depthT, alpha, chaos) {
    // Rest: #3A3A3A → #1F1F1F (near→far). Chaos: #D0D0D0 → #A8A8A8
    const nearRest = 58;
    const farRest = 31;
    const nearChaos = 208;
    const farChaos = 168;
    const rest = nearRest + (farRest - nearRest) * depthT;
    const chaosTone = nearChaos + (farChaos - nearChaos) * depthT;
    const tone = Math.round(rest + (chaosTone - rest) * chaos);

    return `rgba(${tone}, ${tone}, ${tone}, ${alpha})`;
}

/**
 * @param {HTMLCanvasElement} canvas
 * @param {HTMLElement} hit
 * @param {HTMLElement} hero
 */
function drawStaticFrame(canvas, hit, hero) {
    const ctx = canvas.getContext('2d', { alpha: true });

    if (!ctx) {
        return;
    }

    const heroRect = hero.getBoundingClientRect();
    const hitRect = hit.getBoundingClientRect();
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const width = Math.max(1, Math.floor(heroRect.width));
    const height = Math.max(1, Math.floor(heroRect.height));
    canvas.width = Math.floor(width * dpr);
    canvas.height = Math.floor(height * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    const originX = hitRect.left - heroRect.left + hitRect.width * 0.5;
    const originY = hitRect.top - heroRect.top + hitRect.height * 0.5;
    const baseScale = Math.min(hitRect.width, hitRect.height) * 0.2;
    const points = buildTorusPoints(1.05, 0.42, 64, 28);

    drawFrame(
        ctx,
        width,
        height,
        points,
        0.55,
        0.85,
        0.1,
        0,
        { x: 0.5, y: 0.5 },
        0,
        originX,
        originY,
        baseScale,
    );
}
