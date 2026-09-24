import { createHttpClient } from '../../shared/api/http';

const http = createHttpClient('/api/app');

export async function getGithubConnection() {
    const { data } = await http.get('/github/connection');

    return data.data ?? null;
}

export async function startGithubOAuth(payload = {}) {
    const { data } = await http.post('/github/oauth/start', payload);

    return data;
}

export async function disconnectGithub() {
    await http.delete('/github/connection');
}

export async function listGithubRepositories(params = {}) {
    const { data } = await http.get('/github/repositories', { params });

    return data.data ?? data;
}

export async function listGithubBranches(owner, repo, params = {}) {
    const { data } = await http.get(
        `/github/repositories/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/branches`,
        { params },
    );

    return data.data ?? data;
}

export async function listGithubCommits(owner, repo, params = {}) {
    const { data } = await http.get(
        `/github/repositories/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/commits`,
        { params },
    );

    return data.data ?? data;
}

export async function getSiteGithubIntegration(siteId) {
    const { data } = await http.get(`/sites/${siteId}/github-integration`);

    return data.data ?? null;
}

export async function updateSiteGithubIntegration(siteId, payload) {
    const { data } = await http.put(`/sites/${siteId}/github-integration`, payload);

    return data.data ?? data;
}

export async function deleteSiteGithubIntegration(siteId) {
    await http.delete(`/sites/${siteId}/github-integration`);
}

export async function syncSiteGithubIntegration(siteId, payload = {}) {
    const { data } = await http.post(`/sites/${siteId}/github-integration/sync`, payload);

    return data;
}

export async function getSiteGithubCommits(siteId, params) {
    const { data } = await http.get(`/sites/${siteId}/metrics/github-commits`, { params });

    return data.data ?? [];
}
