import axios from 'axios';

export function createHttpClient(baseURL) {
    const client = axios.create({
        baseURL,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
    });

    client.interceptors.request.use((config) => {
        const token = localStorage.getItem(tokenStorageKey(baseURL));

        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        if (typeof FormData !== 'undefined' && config.data instanceof FormData) {
            if (config.headers && typeof config.headers.set === 'function') {
                config.headers.set('Content-Type', undefined);
            } else {
                delete config.headers['Content-Type'];
            }
        }

        return config;
    });

    return client;
}

export function tokenStorageKey(baseURL) {
    if (baseURL.includes('/admin')) {
        return 'admin_api_token';
    }

    return 'app_api_token';
}

export function storeToken(baseURL, token) {
    localStorage.setItem(tokenStorageKey(baseURL), token);
}

export function clearToken(baseURL) {
    localStorage.removeItem(tokenStorageKey(baseURL));
}

export function getToken(baseURL) {
    return localStorage.getItem(tokenStorageKey(baseURL));
}
