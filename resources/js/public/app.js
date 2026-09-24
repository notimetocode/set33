import * as bootstrap from 'bootstrap';
import { getToken } from '../shared/api/http';
import { initCookieConsent } from './cookie-consent';
import { initHeroTorus } from './hero-torus';

function syncPublicAuthCtas() {
    const authed = Boolean(getToken('/api/app'));

    document.querySelectorAll('[data-public-auth-cta]').forEach((el) => {
        const mode = el.getAttribute('data-public-auth-cta') || 'login';
        const keepWhenAuthed = el.hasAttribute('data-public-auth-keep');

        if (authed) {
            if (mode === 'register' && !keepWhenAuthed) {
                el.hidden = true;
                return;
            }

            el.hidden = false;
            el.setAttribute('href', '/app');
            el.textContent = 'Личный кабинет';

            return;
        }

        el.hidden = false;

        if (mode === 'register') {
            el.setAttribute('href', '/app/register');
            el.textContent = 'Зарегистрироваться';

            return;
        }

        el.setAttribute('href', '/app/login');
        el.textContent = 'Войти';
    });
}

function initPublicHeaderScroll() {
    const header = document.querySelector('[data-public-header]');

    if (!header) {
        return;
    }

    const threshold = 8;

    function updateScrolled() {
        header.classList.toggle('is-scrolled', window.scrollY > threshold);
    }

    updateScrolled();
    window.addEventListener('scroll', updateScrolled, { passive: true });
}

function initPublicNav() {
    const root = document.querySelector('.layout-public');
    const toggle = document.querySelector('[data-public-nav-toggle]');
    const panel = document.querySelector('[data-public-nav-panel]');
    const backdrop = document.querySelector('[data-public-nav-backdrop]');

    if (!root || !toggle || !panel) {
        return;
    }

    const openLabel = 'Открыть меню';
    const closeLabel = 'Закрыть меню';
    const mobileQuery = window.matchMedia('(max-width: 767.98px)');

    function setOpen(open) {
        root.classList.toggle('is-nav-open', open);
        document.body.classList.toggle('public-nav-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? closeLabel : openLabel);
        panel.setAttribute('aria-hidden', open ? 'false' : 'true');
        backdrop?.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    function closeNav() {
        setOpen(false);
    }

    function toggleNav() {
        if (!mobileQuery.matches) {
            return;
        }

        setOpen(!root.classList.contains('is-nav-open'));
    }

    function onViewportChange() {
        if (!mobileQuery.matches) {
            closeNav();
        }
    }

    toggle.addEventListener('click', toggleNav);
    backdrop?.addEventListener('click', closeNav);

    panel.querySelectorAll('[data-public-nav-link]').forEach((link) => {
        link.addEventListener('click', closeNav);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && root.classList.contains('is-nav-open')) {
            closeNav();
        }
    });

    mobileQuery.addEventListener('change', onViewportChange);
}

function initPublicUi() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
        bootstrap.Tooltip.getOrCreateInstance(el);
    });

    document.querySelectorAll('[data-bs-toggle="popover"]').forEach((el) => {
        bootstrap.Popover.getOrCreateInstance(el);
    });

    syncPublicAuthCtas();
    initPublicHeaderScroll();
    initPublicNav();
    initCookieConsent();
    initHeroTorus();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPublicUi);
} else {
    initPublicUi();
}
