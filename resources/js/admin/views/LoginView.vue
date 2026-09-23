<template>
    <div class="page-admin-login">
        <form class="auth-form" @submit.prevent="submit">
            <div class="page-admin-login__brand">
                <span class="logo">
                    <img class="logo__mark" src="/images/logo.svg" alt="" width="32" height="32">
                </span>
                Панель администратора
            </div>
            <p class="page-admin-login__label">// Только для администраторов</p>

            <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>

            <div class="mb-3">
                <label class="form-label" for="email">E-mail</label>
                <input
                    id="email"
                    v-model="email"
                    type="email"
                    class="form-control"
                    required
                    autocomplete="username"
                >
            </div>

            <div class="mb-4">
                <label class="form-label" for="password">Пароль</label>
                <input
                    id="password"
                    v-model="password"
                    type="password"
                    class="form-control"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button class="btn btn-primary w-100" type="submit" :disabled="loading">
                {{ loading ? 'Вход…' : 'Войти' }}
            </button>
        </form>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { login } from '../api/auth';

const router = useRouter();
const email = ref('admin@example.com');
const password = ref('password');
const error = ref('');
const loading = ref(false);

async function submit() {
    loading.value = true;
    error.value = '';

    try {
        await login(email.value, password.value);
        await router.push({ name: 'dashboard' });
    } catch (e) {
        error.value = e.response?.data?.message
            || e.response?.data?.errors?.email?.[0]
            || 'Не удалось войти';
    } finally {
        loading.value = false;
    }
}
</script>
