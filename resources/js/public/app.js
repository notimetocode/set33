import * as bootstrap from 'bootstrap';
import { getToken } from '../shared/api/http';
import { initCookieConsent } from './cookie-consent';

function syncPublicAuthCtas() {
    const authed = Boolean(getToken('/api/app'));
    const href = authed ? '/app' : '/app/login';
    const label = authed ? 'Личный кабинет' : 'Войти';

    document.querySelectorAll('[data-public-auth-cta]').forEach((el) => {
        el.setAttribute('href', href);
        el.textContent = label;
    });
}

function initPublicNav() {
    const root = document.querySelector('.layout-public');
    const toggle = document.querySelector('[data-public-nav-toggle]');
    const sidebar = document.querySelector('[data-public-nav-sidebar]');
    const backdrop = document.querySelector('[data-public-nav-backdrop]');

    if (!root || !toggle || !sidebar) {
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
        sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
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

    sidebar.querySelectorAll('[data-public-nav-link]').forEach((link) => {
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
    initPublicNav();
    initCookieConsent();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPublicUi);
} else {
    initPublicUi();
}
