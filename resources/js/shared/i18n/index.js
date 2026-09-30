import { ref } from 'vue';
import en from './locales/en.json';
import ru from './locales/ru.json';
import { setStoredLocale } from './storage';

export { ensureStoredLocale, getStoredLocale, setStoredLocale } from './storage';

export const DEFAULT_LOCALE = 'ru';

const messages = { en, ru };

const intlLocales = {
    en: 'en-US',
    ru: 'ru-RU',
};

export const locale = ref(DEFAULT_LOCALE);

/**
 * @param {string|null|undefined} code
 * @returns {string}
 */
function normalizeLocale(code) {
    const base = String(code || '').toLowerCase().split(/[-_]/)[0];

    return Object.prototype.hasOwnProperty.call(messages, base) ? base : DEFAULT_LOCALE;
}

/**
 * @param {Record<string, unknown>} dictionary
 * @param {string} key
 * @returns {string|undefined}
 */
function resolveKey(dictionary, key) {
    const value = key.split('.').reduce(
        (node, segment) => (node !== null && typeof node === 'object' ? node[segment] : undefined),
        dictionary,
    );

    return typeof value === 'string' ? value : undefined;
}

/**
 * @param {string} template
 * @param {Record<string, string|number>} [params]
 * @returns {string}
 */
function interpolate(template, params) {
    if (!params) {
        return template;
    }

    return template.replace(/\{(\w+)\}/g, (match, name) => (
        Object.prototype.hasOwnProperty.call(params, name) ? String(params[name]) : match
    ));
}

/**
 * Translate a dotted key (e.g. `profile.title`) with `{name}` placeholders.
 * Falls back to the default locale, then to the key itself.
 *
 * @param {string} key
 * @param {Record<string, string|number>} [params]
 * @returns {string}
 */
export function t(key, params) {
    const template = resolveKey(messages[locale.value], key)
        ?? resolveKey(messages[DEFAULT_LOCALE], key)
        ?? key;

    return interpolate(template, params);
}

/**
 * @param {string} code
 */
export function setLocale(code) {
    locale.value = normalizeLocale(code);
    setStoredLocale(locale.value);

    if (typeof document !== 'undefined') {
        document.documentElement.lang = locale.value;
    }
}

/**
 * BCP 47 tag for Intl / toLocale*String.
 *
 * @returns {string}
 */
export function intlLocale() {
    return intlLocales[locale.value] ?? intlLocales[DEFAULT_LOCALE];
}

export function useI18n() {
    return { t, locale, setLocale, intlLocale };
}
