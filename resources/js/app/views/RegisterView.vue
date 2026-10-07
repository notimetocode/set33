<template>
    <div class="page-app-register">
        <form class="auth-form" @submit.prevent="submit">
            <div class="page-app-register__brand">
                <span class="logo">
                    <img class="logo__mark" src="/images/logo.svg" alt="Set33" width="96" height="39">
                </span>
                {{ t('layout.brandLabel') }}
            </div>
            <p class="text-muted mb-4">{{ t('auth.register.subtitle') }}</p>

            <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>

            <div class="mb-3">
                <label class="form-label" for="name">{{ t('common.name') }}</label>
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
                <label class="form-label" for="email">{{ t('common.email') }}</label>
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
                <label class="form-label" for="password">{{ t('common.password') }}</label>
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
                <label class="form-label" for="password_confirmation">{{ t('auth.register.passwordConfirmation') }}</label>
                <input
                    id="password_confirmation"
                    v-model="passwordConfirmation"
                    type="password"
                    class="form-control"
                    required
                    autocomplete="new-password"
                >
            </div>

            <p class="page-app-register__legal text-muted">
                {{ t('auth.register.legalPrefix') }}
                <a href="/terms" target="_blank" rel="noopener noreferrer">{{ t('auth.register.terms') }}</a>
                {{ t('auth.register.and') }}
                <a href="/privacy" target="_blank" rel="noopener noreferrer">{{ t('auth.register.privacy') }}</a>.
            </p>

            <button class="btn btn-primary w-100" type="submit" :disabled="loading">
                {{ loading ? t('auth.register.submitting') : t('auth.register.submit') }}
            </button>

            <GoogleAuthButton :disabled="loading" @error="onGoogleError" />

            <p class="page-app-register__switch text-muted mb-0">
                {{ t('auth.register.haveAccount') }}
                <router-link :to="{ name: 'login' }">{{ t('auth.register.loginLink') }}</router-link>
            </p>
        </form>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { register } from '../api/auth';
import GoogleAuthButton from '../components/GoogleAuthButton.vue';
import { ensureStoredLocale, useI18n } from '../../shared/i18n';

const router = useRouter();
const { t, locale } = useI18n();
const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const error = ref('');
const loading = ref(false);

function onGoogleError(message) {
    error.value = message;
}

async function submit() {
    loading.value = true;
    error.value = '';

    try {
        const { user } = await register(
            name.value,
            email.value,
            password.value,
            passwordConfirmation.value,
            locale.value,
        );

        if (user?.locale) {
            ensureStoredLocale(user.locale);
        }

        await router.push({ name: 'sites.index' });
    } catch (e) {
        error.value = e.response?.data?.message
            || e.response?.data?.errors?.email?.[0]
            || e.response?.data?.errors?.password?.[0]
            || e.response?.data?.errors?.name?.[0]
            || t('auth.register.failed');
    } finally {
        loading.value = false;
    }
}
</script>
