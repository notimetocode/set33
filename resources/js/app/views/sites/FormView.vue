<template>
    <div class="page-app-sites page-app-sites--form">
        <div class="page-app-sites__header">
            <div>
                <p class="page-app-sites__eyebrow">Сайты</p>
                <h1 class="page-app-sites__title">
                    {{ isEdit ? 'Изменить сайт' : 'Новый сайт' }}
                </h1>
            </div>
            <RouterLink class="btn btn-secondary" :to="{ name: 'sites.index' }">
                К списку
            </RouterLink>
        </div>

        <div v-if="loading" class="page-app-sites__state">
            <AppLoader block label="Загрузка…" />
        </div>
        <div v-else-if="loadError" class="alert alert-danger py-2">{{ loadError }}</div>

        <form
            v-else
            class="page-app-sites__panel"
            @submit.prevent="onSubmit"
        >
            <div v-if="formError" class="alert alert-danger py-2 mb-3">{{ formError }}</div>

            <div class="mb-3">
                <label class="form-label" for="site-name">Название</label>
                <input
                    id="site-name"
                    v-model="form.name"
                    type="text"
                    class="form-control"
                    :class="{ 'is-invalid': fieldError('name') }"
                    required
                    maxlength="255"
                    autocomplete="organization"
                >
                <div v-if="fieldError('name')" class="invalid-feedback">{{ fieldError('name') }}</div>
            </div>

            <div class="mb-4">
                <label class="form-label" for="site-url">URL</label>
                <input
                    id="site-url"
                    v-model="form.url"
                    type="url"
                    class="form-control"
                    :class="{ 'is-invalid': fieldError('url') }"
                    required
                    maxlength="2048"
                    placeholder="https://example.com"
                >
                <div v-if="fieldError('url')" class="invalid-feedback">{{ fieldError('url') }}</div>
            </div>

            <div class="page-app-sites__actions">
                <button
                    type="submit"
                    class="btn btn-primary"
                    :disabled="saving"
                >
                    {{ saving ? 'Сохранение…' : (isEdit ? 'Сохранить' : 'Создать') }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLoader from '../../../shared/components/AppLoader.vue';
import { createSite, getSite, updateSite } from '../../api/sites';

const route = useRoute();
const router = useRouter();

const isEdit = computed(() => route.name === 'sites.edit');
const loading = ref(false);
const saving = ref(false);
const loadError = ref('');
const formError = ref('');
const fieldErrors = ref({});

const form = reactive({
    name: '',
    url: '',
});

function fieldError(name) {
    const value = fieldErrors.value[name];

    if (Array.isArray(value)) {
        return value[0] || '';
    }

    return value || '';
}

async function load() {
    if (!isEdit.value) {
        return;
    }

    loading.value = true;
    loadError.value = '';

    try {
        const site = await getSite(route.params.id);
        form.name = site.name || '';
        form.url = site.url || '';
    } catch (e) {
        loadError.value = e.response?.data?.message || 'Не удалось загрузить сайт';
    } finally {
        loading.value = false;
    }
}

async function onSubmit() {
    saving.value = true;
    formError.value = '';
    fieldErrors.value = {};

    const payload = {
        name: form.name.trim(),
        url: form.url.trim(),
    };

    try {
        if (isEdit.value) {
            const site = await updateSite(route.params.id, payload);
            await router.push({ name: 'sites.show', params: { id: site.id } });
        } else {
            const site = await createSite(payload);
            await router.push({ name: 'sites.show', params: { id: site.id } });
        }
    } catch (e) {
        if (e.response?.status === 422) {
            fieldErrors.value = e.response.data.errors || {};
            formError.value = e.response.data.message || 'Проверьте поля формы';
        } else {
            formError.value = e.response?.data?.message || 'Не удалось сохранить сайт';
        }
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
