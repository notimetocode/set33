import { createHttpClient } from '../../shared/api/http';

const http = createHttpClient('/api/app');

export async function listSites() {
    const { data } = await http.get('/sites');

    return data;
}

export async function getSite(id) {
    const { data } = await http.get(`/sites/${id}`);

    return data.data ?? data;
}

export async function createSite(payload) {
    const { data } = await http.post('/sites', payload);

    return data.data ?? data;
}

export async function updateSite(id, payload) {
    const { data } = await http.put(`/sites/${id}`, payload);

    return data.data ?? data;
}

export async function deleteSite(id) {
    await http.delete(`/sites/${id}`);
}

export async function getGoogleConnection() {
    const { data } = await http.get('/google/connection');

    return data.data ?? null;
}

export async function startGoogleOAuth(payload = {}) {
    const { data } = await http.post('/google/oauth/start', payload);

    return data;
}

export async function disconnectGoogle() {
    await http.delete('/google/connection');
}

export async function listGa4Properties() {
    const { data } = await http.get('/google/ga4-properties');

    return data.data ?? [];
}

export async function listGscSites() {
    const { data } = await http.get('/google/gsc-sites');

    return data.data ?? [];
}

export async function getSiteGoogleIntegration(siteId) {
    const { data } = await http.get(`/sites/${siteId}/google-integration`);

    return data.data ?? null;
}

export async function updateSiteGoogleIntegration(siteId, payload) {
    const { data } = await http.put(`/sites/${siteId}/google-integration`, payload);

    return data.data ?? data;
}

export async function deleteSiteGoogleIntegration(siteId) {
    await http.delete(`/sites/${siteId}/google-integration`);
}

export async function syncSiteGoogleIntegration(siteId, payload = {}) {
    const { data } = await http.post(`/sites/${siteId}/google-integration/sync`, payload);

    return data;
}

export async function getSiteAnalyticsMetrics(siteId, params) {
    const { data } = await http.get(`/sites/${siteId}/metrics/analytics`, { params });

    return data.data ?? [];
}

export async function getSiteSearchConsoleMetrics(siteId, params) {
    const { data } = await http.get(`/sites/${siteId}/metrics/search-console`, { params });

    return data.data ?? [];
}
