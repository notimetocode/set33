import { icon } from '@fortawesome/fontawesome-svg-core';
import { faCookieBite } from '@fortawesome/free-solid-svg-icons';

const STORAGE_KEY = 'ndo_cookie_consent';
const CONSENT_ALL = 'all';
const CONSENT_NECESSARY = 'necessary';

function renderCookieIcon(root) {
    const mount = root.querySelector('[data-cookie-consent-icon]');

    if (!mount) {
        return;
    }

    const rendered = icon(faCookieBite, {
        classes: ['cookie-consent__fa'],
    });

    mount.replaceChildren(...rendered.node);
}

function readConsent() {
    try {
        const value = window.localStorage.getItem(STORAGE_KEY);

        if (value === CONSENT_ALL || value === CONSENT_NECESSARY) {
            return value;
        }
    } catch {
        // localStorage может быть недоступен (приватный режим и т.п.)
    }

    return null;
}

function writeConsent(value) {
    try {
        window.localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // игнорируем — баннер всё равно скроем в этой сессии
    }

    document.cookie = [
        `${STORAGE_KEY}=${encodeURIComponent(value)}`,
        'path=/',
        `max-age=${60 * 60 * 24 * 365}`,
        'SameSite=Lax',
    ].join('; ');
}

function hasAnalyticsConsent(consent = readConsent()) {
    return consent === CONSENT_ALL;
}

/**
 * Точка расширения для аналитики (Метрика, GA и т.п.).
 * Скрипты подключаются только при согласии «Принять все».
 */
function applyAnalyticsConsent(consent) {
    if (!hasAnalyticsConsent(consent)) {
        return;
    }

    document.dispatchEvent(
        new CustomEvent('ndo:cookie-consent', {
            detail: { consent, analytics: true },
        }),
    );

    // Опциональные скрипты: <script type="text/plain" data-cookie-analytics src="…">
    document.querySelectorAll('script[data-cookie-analytics][type="text/plain"]').forEach((el) => {
        const script = document.createElement('script');

        Array.from(el.attributes).forEach((attr) => {
            if (attr.name === 'type' || attr.name === 'data-cookie-analytics') {
                return;
            }

            script.setAttribute(attr.name, attr.value);
        });

        if (el.textContent) {
            script.textContent = el.textContent;
        }

        script.type = 'text/javascript';
        el.replaceWith(script);
    });
}

function hideBanner(root) {
    root.classList.remove('is-visible');
    root.setAttribute('hidden', '');
}

function showBanner(root) {
    root.removeAttribute('hidden');
    // следующий кадр — чтобы сработала CSS-анимация
    requestAnimationFrame(() => {
        root.classList.add('is-visible');
    });
}

export function initCookieConsent() {
    const root = document.querySelector('[data-cookie-consent]');

    if (!root) {
        return;
    }

    renderCookieIcon(root);

    const existing = readConsent();

    if (existing) {
        applyAnalyticsConsent(existing);
        hideBanner(root);

        return;
    }

    showBanner(root);

    const acceptBtn = root.querySelector('[data-cookie-consent-accept]');
    const declineBtn = root.querySelector('[data-cookie-consent-decline]');

    acceptBtn?.addEventListener('click', () => {
        writeConsent(CONSENT_ALL);
        applyAnalyticsConsent(CONSENT_ALL);
        hideBanner(root);
    });

    declineBtn?.addEventListener('click', () => {
        writeConsent(CONSENT_NECESSARY);
        hideBanner(root);
    });
}

export function getCookieConsent() {
    return readConsent();
}

export function hasCookieAnalyticsConsent() {
    return hasAnalyticsConsent();
}
