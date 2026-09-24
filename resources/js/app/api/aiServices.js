import { createHttpClient } from '../../shared/api/http';

const http = createHttpClient('/api/app');

export async function getAiServicesMeta() {
    const { data } = await http.get('/ai-services/meta');

    return data;
}

export async function listAiServiceModels(payload) {
    const { data } = await http.post('/ai-services/models', payload);

    return data.data ?? data;
}

export async function listAiServices() {
    const { data } = await http.get('/ai-services');

    return data;
}

export async function getAiService(id) {
    const { data } = await http.get(`/ai-services/${id}`);

    return data.data ?? data;
}

export async function createAiService(payload) {
    const { data } = await http.post('/ai-services', payload);

    return data.data ?? data;
}

export async function updateAiService(id, payload) {
    const { data } = await http.put(`/ai-services/${id}`, payload);

    return data.data ?? data;
}

export async function deleteAiService(id) {
    await http.delete(`/ai-services/${id}`);
}

export async function checkAiService(id) {
    const { data } = await http.post(`/ai-services/${id}/check`);

    return data;
}

export async function generateAiServiceContent(id, prompt) {
    const { data } = await http.post(`/ai-services/${id}/generate`, { prompt });

    return data;
}
