import { ref } from 'vue';

/** @type {import('vue').Ref<string|null>} */
export const dynamicBreadcrumbLabel = ref(null);

/**
 * @param {string|null|undefined} label
 */
export function setDynamicBreadcrumbLabel(label) {
    dynamicBreadcrumbLabel.value = label ? String(label) : null;
}
