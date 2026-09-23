import { createHttpClient, storeToken, clearToken, getToken } from '../../shared/api/http';

const http = createHttpClient('/api/admin');

export async function login(email, password) {
    const { data } = await http.post('/auth/login', { email, password });
    storeToken('/api/admin', data.token);

    return data;
}

export async function logout() {
    try {
        await http.post('/auth/logout');
    } finally {
        clearToken('/api/admin');
    }
}

export async function me() {
    const { data } = await http.get('/auth/me');

    return data;
}

export function isAuthenticated() {
    return Boolean(getToken('/api/admin'));
}
