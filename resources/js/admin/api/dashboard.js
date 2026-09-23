import { createHttpClient } from '../../shared/api/http';

const http = createHttpClient('/api/admin');

export async function getDashboardStats() {
    const { data } = await http.get('/dashboard');

    return data.data ?? data;
}
