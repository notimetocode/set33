import { ref } from 'vue';

const DEFAULT_SUCCESS_MS = 4000;
const DEFAULT_ERROR_MS = 6000;

/** @type {import('vue').Ref<Array<{ id: number, message: string, ok: boolean }>>} */
export const toasts = ref([]);

let nextId = 1;

/**
 * @param {{ message: string, ok?: boolean, duration?: number }} options
 * @returns {number}
 */
export function show({ message, ok = true, duration } = {}) {
    const id = nextId++;
    const text = String(message || '').trim();

    if (!text) {
        return id;
    }

    toasts.value = [...toasts.value, { id, message: text, ok: Boolean(ok) }];

    const ms = duration ?? (ok ? DEFAULT_SUCCESS_MS : DEFAULT_ERROR_MS);

    if (ms > 0) {
        window.setTimeout(() => dismiss(id), ms);
    }

    return id;
}

/**
 * @param {number} id
 */
export function dismiss(id) {
    toasts.value = toasts.value.filter((item) => item.id !== id);
}

export const toast = { show, dismiss, toasts };
