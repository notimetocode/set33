export const LOCALE_STORAGE_KEY = 'set33.locale';

const SUPPORTED = new Set(['en', 'ru']);

/**
 * @param {string|null|undefined} code
 * @returns {'en'|'ru'|null}
 */
export function normalizeStoredLocale(code) {
    const base = String(code || '').toLowerCase().split(/[-_]/)[0];

    return SUPPORTED.has(base) ? /** @type {'en'|'ru'} */ (base) : null;
}

/**
 * @returns {'en'|'ru'|null}
 */
export function getStoredLocale() {
    if (typeof localStorage === 'undefined') {
        return null;
    }

    try {
        return normalizeStoredLocale(localStorage.getItem(LOCALE_STORAGE_KEY));
    } catch {
        return null;
    }
}

/**
 * @param {string|null|undefined} code
 * @returns {'en'|'ru'|null}
 */
export function setStoredLocale(code) {
    const normalized = normalizeStoredLocale(code);

    if (normalized === null || typeof localStorage === 'undefined') {
        return normalized;
    }

    try {
        localStorage.setItem(LOCALE_STORAGE_KEY, normalized);
    } catch {
        // Ignore quota / private-mode failures.
    }

    return normalized;
}

/**
 * Write locale only when storage has no supported value yet.
 *
 * @param {string|null|undefined} code
 * @returns {'en'|'ru'|null}
 */
export function ensureStoredLocale(code) {
    const existing = getStoredLocale();

    if (existing !== null) {
        return existing;
    }

    return setStoredLocale(code);
}
