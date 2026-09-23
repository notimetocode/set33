/**
 * @param {import('vue-router').RouteLocationNormalizedLoaded} route
 * @returns {Array<{ label: string, name?: string, params?: Record<string, string|number> }>}
 */
export function resolveBreadcrumbs(route) {
    const raw = route.meta?.breadcrumbs;

    if (typeof raw === 'function') {
        return raw(route) || [];
    }

    return Array.isArray(raw) ? raw : [];
}
