import { createHttpClient } from '../../shared/api/http';

const http = createHttpClient('/api/admin');

/**
 * @param {{
 *   page?: number,
 *   q?: string,
 *   role?: string,
 *   gender?: string,
 *   city_id?: number|string,
 * }} [params]
 */
export async function listUsers(params = {}) {
    const query = {};

    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            query[key] = value;
        }
    });

    const { data } = await http.get('/users', { params: query });

    return data;
}

export async function getUsersMeta() {
    const { data } = await http.get('/users/meta');

    return data.data ?? data;
}
