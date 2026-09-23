import { createHttpClient } from '../../shared/api/http';

const http = createHttpClient('/api/app');

export async function getProfile() {
    const { data } = await http.get('/profile');

    return data.data ?? data;
}
