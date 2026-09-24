<template>
    <div class="page-app-register">
        <form class="auth-form" @submit.prevent="submit">
            <div class="page-app-register__brand">
                <span class="logo">
                    <img class="logo__mark" src="/images/logo.svg" alt="Set33" width="96" height="20">
                </span>
                Личный кабинет
            </div>
            <p class="text-muted mb-4">Создайте аккаунт, чтобы продолжить</p>

            <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>

            <div class="mb-3">
                <label class="form-label" for="name">Имя</label>
                <input
                    id="name"
                    v-model="name"
                    type="text"
                    class="form-control"
                    required
                    autocomplete="name"
                >
            </div>

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

            <div class="mb-3">
                <label class="form-label" for="password">Пароль</label>
                <input
                    id="password"
                    v-model="password"
                    type="password"
                    class="form-control"
                    required
                    autocomplete="new-password"
                >
            </div>

            <div class="mb-4">
                <label class="form-label" for="password_confirmation">Повторите пароль</label>
                <input
                    id="password_confirmation"
                    v-model="passwordConfirmation"
                    type="password"
                    class="form-control"
                    required
                    autocomplete="new-password"
                >
            </div>

            <button class="btn btn-primary w-100" type="submit" :disabled="loading">
                {{ loading ? 'Регистрация…' : 'Зарегистрироваться' }}
            </button>

            <p class="page-app-register__switch text-muted mb-0">
                Уже есть аккаунт?
                <router-link :to="{ name: 'login' }">Войти</router-link>
            </p>
        </form>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { register } from '../api/auth';

const router = useRouter();
const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const error = ref('');
const loading = ref(false);

async function submit() {
    loading.value = true;
    error.value = '';

    try {
        await register(name.value, email.value, password.value, passwordConfirmation.value);
        await router.push({ name: 'sites.index' });
    } catch (e) {
        error.value = e.response?.data?.message
            || e.response?.data?.errors?.email?.[0]
            || e.response?.data?.errors?.password?.[0]
            || e.response?.data?.errors?.name?.[0]
            || 'Не удалось зарегистрироваться';
    } finally {
        loading.value = false;
    }
}
</script>
