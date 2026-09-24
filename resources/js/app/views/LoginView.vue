<template>
    <div class="page-app-login">
        <form class="auth-form" @submit.prevent="submit">
            <div class="page-app-login__brand">
                <span class="logo">
                    <img class="logo__mark" src="/images/logo.svg" alt="Set33" width="96" height="20">
                </span>
                Личный кабинет
            </div>
            <p class="text-muted mb-4">Войдите, чтобы продолжить</p>

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

            <p class="page-app-login__switch text-muted mb-0">
                Нет аккаунта?
                <router-link :to="{ name: 'register' }">Зарегистрироваться</router-link>
            </p>
        </form>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { login } from '../api/auth';

const router = useRouter();
const email = ref('user@example.com');
const password = ref('password');
const error = ref('');
const loading = ref(false);

async function submit() {
    loading.value = true;
    error.value = '';

    try {
        await login(email.value, password.value);
        await router.push({ name: 'sites.index' });
    } catch (e) {
        error.value = e.response?.data?.message
            || e.response?.data?.errors?.email?.[0]
            || 'Не удалось войти';
    } finally {
        loading.value = false;
    }
}
</script>
