<template>
    <div class="page-app-profile">
        <h1 class="h4 mb-3">Личные данные</h1>

        <div v-if="loading" class="text-muted">Загрузка…</div>
        <div v-else-if="error" class="alert alert-danger py-2">{{ error }}</div>

        <dl v-else-if="profile" class="row mb-0">
            <dt class="col-sm-3">ФИО</dt>
            <dd class="col-sm-9">{{ fullName || '—' }}</dd>

            <dt class="col-sm-3">E-mail</dt>
            <dd class="col-sm-9">{{ profile.email || '—' }}</dd>
        </dl>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { getProfile } from '../api/profile';
import { formatUserFio } from '../../shared/userDisplay';

const profile = ref(null);
const loading = ref(true);
const error = ref('');

const fullName = computed(() => formatUserFio(profile.value));

onMounted(async () => {
    try {
        profile.value = await getProfile();
    } catch (e) {
        error.value = e.response?.data?.message || 'Не удалось загрузить профиль';
    } finally {
        loading.value = false;
    }
});
</script>
