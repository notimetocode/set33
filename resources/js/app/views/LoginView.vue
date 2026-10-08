<template>
    <div class="page-app-login">
        <form class="auth-form" @submit.prevent="submit">
            <div class="page-app-login__brand">
                <span class="logo">
                    <img class="logo__mark" src="/images/logo.svg" alt="Set33" width="96" height="39">
                </span>
                {{ t('layout.brandLabel') }}
            </div>
            <p class="text-muted mb-4">{{ t('auth.login.subtitle') }}</p>

            <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>

            <GoogleAuthButton :disabled="loading || exchanging" @error="onGoogleError" />

            <div class="mb-3">
                <label class="form-label" for="email">{{ t('common.email') }}</label>
                <input
                    id="email"
                    v-model="email"
                    type="email"
                    class="form-control"
                    required
                    autocomplete="username"
                    :disabled="exchanging"
                >
            </div>

            <div class="mb-4">
                <label class="form-label" for="password">{{ t('common.password') }}</label>
                <input
                    id="password"
                    v-model="password"
                    type="password"
                    class="form-control"
                    required
                    autocomplete="current-password"
                    :disabled="exchanging"
                >
            </div>

            <button class="btn btn-primary w-100" type="submit" :disabled="loading || exchanging">
                {{ loading ? t('auth.login.submitting') : t('auth.login.submit') }}
            </button>

            <p class="page-app-login__switch text-muted mb-0">
                {{ t('auth.login.noAccount') }}
                <router-link :to="{ name: 'register' }">{{ t('auth.login.registerLink') }}</router-link>
            </p>
        </form>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { exchangeGoogleLogin, login } from '../api/auth';
import GoogleAuthButton from '../components/GoogleAuthButton.vue';
import { useI18n } from '../../shared/i18n';

const router = useRouter();
const route = useRoute();
const { t, setLocale } = useI18n();
const email = ref('user@example.com');
const password = ref('password');
const error = ref('');
const loading = ref(false);
const exchanging = ref(false);

function onGoogleError(message) {
    error.value = message;
}

async function finishAuth(user) {
    if (user?.locale) {
        setLocale(user.locale);
    }

    await router.replace({ name: 'sites.index' });
}

async function submit() {
    loading.value = true;
    error.value = '';

    try {
        const { user } = await login(email.value, password.value);
        await finishAuth(user);
    } catch (e) {
        error.value = e.response?.data?.message
            || e.response?.data?.errors?.email?.[0]
            || t('auth.login.failed');
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    if (route.query.google === 'error') {
        error.value = typeof route.query.message === 'string' && route.query.message
            ? route.query.message
            : t('auth.google.failed');
        await router.replace({ name: 'login', query: {} });

        return;
    }

    const code = typeof route.query.google_code === 'string' ? route.query.google_code : '';

    if (! code) {
        return;
    }

    exchanging.value = true;
    error.value = '';

    try {
        const { user } = await exchangeGoogleLogin(code);
        await finishAuth(user);
    } catch (e) {
        error.value = e.response?.data?.message
            || e.response?.data?.errors?.code?.[0]
            || t('auth.google.failed');
        await router.replace({ name: 'login', query: {} });
    } finally {
        exchanging.value = false;
    }
});
</script>
