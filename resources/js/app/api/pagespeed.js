import { createHttpClient } from '../../shared/api/http';

const http = createHttpClient('/api/app');

export async function upsertSitePageSpeedIntegration(siteId, payload) {
    const { data } = await http.put(`/sites/${siteId}/pagespeed-integration`, payload);

    return data.data ?? data;
}

export async function deleteSitePageSpeedIntegration(siteId) {
    await http.delete(`/sites/${siteId}/pagespeed-integration`);
}

export async function syncSitePageSpeedIntegration(siteId, payload = {}) {
    const { data } = await http.post(`/sites/${siteId}/pagespeed-integration/sync`, payload);

    return data;
}

export async function getSitePageSpeedMetrics(siteId) {
    const { data } = await http.get(`/sites/${siteId}/metrics/pagespeed`);
    const payload = data.data ?? {};

    return {
        lab: payload.lab ?? [],
        crux: payload.crux ?? [],
    };
}
