/**
 * Scroll-driven “ink” for home page frames and center rails.
 * Sets --draw / --draw-rail (0..1) as elements pass a viewport pen line.
 */
export function initHomeLineDraw() {
    const root = document.querySelector('.page-home');

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const frames = Array.from(root.querySelectorAll('[data-home-draw="frame"]'));
    const railHosts = Array.from(root.querySelectorAll('[data-home-draw-rail]'));

    if (frames.length === 0 && railHosts.length === 0) {
        return;
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const lengthProbeCache = new Map();

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    /**
     * Resolve a CSS length custom property to px (e.g. calc(var(--home-rail-height) * 2)).
     */
    function resolveLength(host, property) {
        const key = `${property}:${host.className}`;
        const cached = lengthProbeCache.get(key);

        if (typeof cached === 'number' && cached > 0) {
            return cached;
        }

        const probe = document.createElement('div');
        probe.style.cssText = `position:absolute;visibility:hidden;pointer-events:none;height:var(${property});`;
        host.appendChild(probe);
        const height = probe.offsetHeight;
        probe.remove();

        if (height > 0) {
            lengthProbeCache.set(key, height);
        }

        return height;
    }

    /**
     * Progress as the segment crosses the “pen” line in the viewport.
     */
    function progressThrough(top, bottom) {
        const pen = window.innerHeight * 0.58;

        if (bottom <= pen) {
            return 1;
        }

        if (top >= pen) {
            return 0;
        }

        const span = bottom - top;

        if (span <= 0) {
            return 1;
        }

        return clamp((pen - top) / span, 0, 1);
    }

    function formatProgress(value) {
        return (Math.round(value * 1000) / 1000).toFixed(3);
    }

    function update() {
        if (reduceMotion.matches) {
            frames.forEach((el) => {
                el.style.setProperty('--draw', '1');
            });
            railHosts.forEach((el) => {
                el.style.setProperty('--draw-rail', '1');
            });

            return;
        }

        frames.forEach((el) => {
            const rect = el.getBoundingClientRect();
            el.style.setProperty('--draw', formatProgress(progressThrough(rect.top, rect.bottom)));
        });

        railHosts.forEach((el) => {
            const rect = el.getBoundingClientRect();
            const railHeight = resolveLength(el, '--home-rail-draw-h');

            if (railHeight <= 0) {
                el.style.setProperty('--draw-rail', '0');

                return;
            }

            el.style.setProperty(
                '--draw-rail',
                formatProgress(progressThrough(rect.bottom, rect.bottom + railHeight)),
            );
        });
    }

    let ticking = false;

    function requestUpdate() {
        if (ticking) {
            return;
        }

        ticking = true;
        window.requestAnimationFrame(() => {
            ticking = false;
            update();
        });
    }

    function onResize() {
        lengthProbeCache.clear();
        requestUpdate();
    }

    update();
    window.addEventListener('scroll', requestUpdate, { passive: true });
    window.addEventListener('resize', onResize, { passive: true });
    reduceMotion.addEventListener('change', requestUpdate);
}
