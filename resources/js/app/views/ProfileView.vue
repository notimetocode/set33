<template>
    <div class="page-app-profile">
        <h1 class="h4 mb-3">Личные данные</h1>

        <AppLoader
            v-if="loading"
            block
            label="Загрузка…"
        />
        <div v-else-if="loadError" class="alert alert-danger py-2">{{ loadError }}</div>

        <form
            v-else
            class="page-app-profile__form"
            @submit.prevent="onSubmit"
        >
            <div v-if="formError" class="alert alert-danger py-2 mb-3">{{ formError }}</div>

            <section class="page-app-profile__section">
                <h2 class="h6">Основное</h2>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="profile-last-name">Фамилия</label>
                        <input
                            id="profile-last-name"
                            v-model="form.last_name"
                            type="text"
                            class="form-control"
                            :class="{ 'is-invalid': fieldError('last_name') }"
                            maxlength="255"
                            autocomplete="family-name"
                        >
                        <div v-if="fieldError('last_name')" class="invalid-feedback">
                            {{ fieldError('last_name') }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="profile-first-name">Имя</label>
                        <input
                            id="profile-first-name"
                            v-model="form.first_name"
                            type="text"
                            class="form-control"
                            :class="{ 'is-invalid': fieldError('first_name') }"
                            maxlength="255"
                            autocomplete="given-name"
                        >
                        <div v-if="fieldError('first_name')" class="invalid-feedback">
                            {{ fieldError('first_name') }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="profile-middle-name">Отчество</label>
                        <input
                            id="profile-middle-name"
                            v-model="form.middle_name"
                            type="text"
                            class="form-control"
                            :class="{ 'is-invalid': fieldError('middle_name') }"
                            maxlength="255"
                            autocomplete="additional-name"
                        >
                        <div v-if="fieldError('middle_name')" class="invalid-feedback">
                            {{ fieldError('middle_name') }}
                        </div>
                    </div>
                </div>
            </section>

            <section class="page-app-profile__section">
                <h2 class="h6">Контакты</h2>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="profile-email">E-mail</label>
                        <input
                            id="profile-email"
                            v-model="form.email"
                            type="email"
                            class="form-control"
                            :class="{ 'is-invalid': fieldError('email') }"
                            required
                            maxlength="255"
                            autocomplete="email"
                        >
                        <div v-if="fieldError('email')" class="invalid-feedback">
                            {{ fieldError('email') }}
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="profile-phone">Телефон</label>
                        <input
                            id="profile-phone"
                            v-model="form.phone"
                            type="tel"
                            class="form-control"
                            :class="{ 'is-invalid': fieldError('phone') }"
                            maxlength="32"
                            autocomplete="tel"
                        >
                        <div v-if="fieldError('phone')" class="invalid-feedback">
                            {{ fieldError('phone') }}
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="profile-telegram">Telegram</label>
                        <input
                            id="profile-telegram"
                            v-model="form.telegram"
                            type="text"
                            class="form-control"
                            :class="{ 'is-invalid': fieldError('telegram') }"
                            maxlength="64"
                            placeholder="@username"
                        >
                        <div v-if="fieldError('telegram')" class="invalid-feedback">
                            {{ fieldError('telegram') }}
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="profile-viber">Viber</label>
                        <input
                            id="profile-viber"
                            v-model="form.viber"
                            type="text"
                            class="form-control"
                            :class="{ 'is-invalid': fieldError('viber') }"
                            maxlength="64"
                        >
                        <div v-if="fieldError('viber')" class="invalid-feedback">
                            {{ fieldError('viber') }}
                        </div>
                    </div>
                </div>
            </section>

            <div class="page-app-profile__actions">
                <button
                    type="submit"
                    class="btn btn-primary"
                    :disabled="saving"
                >
                    <AppLoader v-if="saving" size="sm" />
                    <span>{{ saving ? 'Сохранение…' : 'Сохранить' }}</span>
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { inject, onMounted, reactive, ref } from 'vue';
import AppLoader from '../../shared/components/AppLoader.vue';
import { toast } from '../../shared/toast';
import { getProfile, updateProfile } from '../api/profile';

const refreshAppUser = inject('refreshAppUser', null);

const loading = ref(true);
const saving = ref(false);
const loadError = ref('');
const formError = ref('');
const fieldErrors = ref({});

const form = reactive({
    last_name: '',
    first_name: '',
    middle_name: '',
    email: '',
    phone: '',
    telegram: '',
    viber: '',
});

function fieldError(key) {
    const messages = fieldErrors.value[key];

    return Array.isArray(messages) ? messages[0] : '';
}

function applyProfile(profile) {
    form.last_name = profile.last_name || '';
    form.first_name = profile.first_name || '';
    form.middle_name = profile.middle_name || '';
    form.email = profile.email || '';
    form.phone = profile.phone || '';
    form.telegram = profile.telegram || '';
    form.viber = profile.viber || '';
}

function buildPayload() {
    return {
        last_name: form.last_name || null,
        first_name: form.first_name || null,
        middle_name: form.middle_name || null,
        email: form.email,
        phone: form.phone || null,
        telegram: form.telegram || null,
        viber: form.viber || null,
    };
}

async function onSubmit() {
    saving.value = true;
    formError.value = '';
    fieldErrors.value = {};

    try {
        const { profile } = await updateProfile(buildPayload());
        applyProfile(profile);

        if (typeof refreshAppUser === 'function') {
            await refreshAppUser();
        }

        toast.show({ message: 'Профиль сохранён' });
    } catch (e) {
        fieldErrors.value = e.response?.data?.errors ?? {};
        formError.value = e.response?.data?.message || 'Не удалось сохранить профиль';
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    loading.value = true;
    loadError.value = '';

    try {
        const { profile } = await getProfile();
        applyProfile(profile);
    } catch (e) {
        loadError.value = e.response?.data?.message || 'Не удалось загрузить профиль';
    } finally {
        loading.value = false;
    }
});
</script>
