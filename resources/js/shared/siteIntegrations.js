const INTEGRATIONS = {
    analytics: {
        label: 'Google Analytics',
        logo: '/images/integrations/google-analytics.svg',
    },
    search_console: {
        label: 'Google Search Console',
        logo: '/images/integrations/google-search-console.svg',
    },
    github: {
        label: 'GitHub',
        logo: '/images/integrations/github.svg',
    },
    pagespeed: {
        label: 'Chrome UX Report',
        logo: '/images/integrations/pagespeed.svg',
    },
};

/**
 * @param {Record<string, unknown>|null|undefined} site
 * @returns {Array<{ key: string, label: string, logo: string, status: string|null, statusLabel: string|null }>}
 */
export function connectedIntegrationsForSite(site) {
    if (!site) {
        return [];
    }

    const items = [];
    const google = site.google_integration;

    if (google?.ga4_property_id) {
        items.push(integrationItem('analytics', google.status, google.status_label));
    }

    if (google?.gsc_site_url) {
        items.push(integrationItem('search_console', google.status, google.status_label));
    }

    const github = site.github_integration;

    if (github?.is_configured) {
        items.push(integrationItem('github', github.status, github.status_label));
    }

    const pagespeed = site.pagespeed_integration;

    if (pagespeed?.is_configured) {
        items.push(integrationItem('pagespeed', pagespeed.status, pagespeed.status_label));
    }

    return items;
}

function integrationItem(key, status, statusLabel) {
    const def = INTEGRATIONS[key];

    return {
        key,
        label: def.label,
        logo: def.logo,
        status: status ?? null,
        statusLabel: statusLabel ?? null,
    };
}

/**
 * @param {{ label: string, status: string|null, statusLabel: string|null }} item
 */
export function integrationIconTitle(item) {
    if (item.status === 'error' && item.statusLabel) {
        return `${item.label}: ${item.statusLabel}`;
    }

    return item.label;
}

/**
 * Latest sync timestamp across configured site integrations.
 *
 * @param {Record<string, unknown>|null|undefined} site
 * @returns {string|null} ISO date string or null
 */
export function latestSiteSyncedAt(site) {
    if (!site) {
        return null;
    }

    const timestamps = [
        site.google_integration?.last_synced_at,
        site.github_integration?.last_synced_at,
        site.pagespeed_integration?.last_synced_at,
    ].filter(Boolean);

    if (!timestamps.length) {
        return null;
    }

    return timestamps.reduce((latest, current) => {
        return new Date(current) > new Date(latest) ? current : latest;
    });
}

/**
 * @param {string|null|undefined} value
 * @returns {string}
 */
export function formatSiteSyncedAt(value) {
    if (!value) {
        return 'Ещё не синхронизировалось';
    }

    try {
        return new Date(value).toLocaleString('ru-RU');
    } catch {
        return value;
    }
}
