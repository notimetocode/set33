import { createHttpClient } from '../../shared/api/http';

const http = createHttpClient('/api/app');

export async function getProfile() {
    const { data } = await http.get('/profile');

    return {
        profile: data.data ?? data,
    };
}

export async function updateProfile(payload) {
    const { data } = await http.put('/profile', payload);

    return {
        profile: data.data ?? data,
    };
}
