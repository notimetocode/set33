<template>
    <div class="page-app-sites">
        <div class="page-app-sites__header">
            <div>
                <h1 class="h4 mb-1">Сайты</h1>
                <p class="text-muted mb-0">Проекты и подключения Google Analytics / Search Console</p>
            </div>
            <RouterLink class="btn btn-primary" :to="{ name: 'sites.create' }">
                Добавить
            </RouterLink>
        </div>

        <AppLoader
            v-if="loading"
            block
            label="Загрузка…"
        />
        <div v-else-if="error" class="alert alert-danger py-2">{{ error }}</div>

        <div
            v-else
            class="data-table"
            :class="{ 'is-loading': busyId !== null }"
        >
            <table class="table table-sm table-hover align-middle data-table__grid">
                <thead>
                    <tr>
                        <th>Сайт</th>
                        <th>URL</th>
                        <th class="page-app-sites__col-status">Google</th>
                        <th class="page-app-sites__col-actions">
                            <span class="visually-hidden">Действия</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="site in sites"
                        :key="site.id"
                        class="page-app-sites__row"
                        role="link"
                        tabindex="0"
                        @click="openSite(site)"
                        @keydown.enter.prevent="openSite(site)"
                    >
                        <td>
                            <span class="page-app-sites__name">{{ site.name }}</span>
                        </td>
                        <td class="text-muted">{{ site.url }}</td>
                        <td class="page-app-sites__col-status">
                            <span
                                class="status-tag"
                                :class="integrationTagClass(site)"
                            >
                                {{ integrationLabel(site) }}
                            </span>
                        </td>
                        <td
                            class="page-app-sites__col-actions"
                            @click.stop
                            @keydown.stop
                        >
                            <RowActionsMenu :disabled="busyId === site.id">
                                <template #default="{ close }">
                                    <RouterLink
                                        class="row-actions-menu__item"
                                        role="menuitem"
                                        :to="{ name: 'sites.edit', params: { id: site.id } }"
                                        @click="close"
                                    >
                                        Изменить
                                    </RouterLink>
                                    <button
                                        type="button"
                                        class="row-actions-menu__item is-danger"
                                        role="menuitem"
                                        :disabled="busyId === site.id"
                                        @click="onDeleteClick(site, close)"
                                    >
                                        Удалить
                                    </button>
                                </template>
                            </RowActionsMenu>
                        </td>
                    </tr>
                    <tr v-if="!sites.length">
                        <td colspan="4" class="text-muted">Пока нет сайтов</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLoader from '../../../shared/components/AppLoader.vue';
import RowActionsMenu from '../../../shared/components/RowActionsMenu.vue';
import { toast } from '../../../shared/toast';
import { deleteSite, listSites } from '../../api/sites';

const route = useRoute();
const router = useRouter();

const sites = ref([]);
const loading = ref(true);
const error = ref('');
const busyId = ref(null);

function integrationLabel(site) {
    const integration = site.google_integration;

    if (!integration || !integration.is_configured) {
        return 'Не подключено';
    }

    return integration.status_label || 'Активна';
}

function integrationTagClass(site) {
    const integration = site.google_integration;

    if (!integration || !integration.is_configured) {
        return 'status-tag--muted';
    }

    if (integration.status === 'error') {
        return 'status-tag--danger';
    }

    if (integration.status === 'active') {
        return 'status-tag--ok';
    }

    return 'status-tag--muted';
}

function openSite(site) {
    if (busyId.value === site.id) {
        return;
    }

    router.push({ name: 'sites.show', params: { id: site.id } });
}

async function reload() {
    loading.value = true;
    error.value = '';

    try {
        const response = await listSites();
        sites.value = response.data ?? [];
    } catch (e) {
        error.value = e.response?.data?.message || 'Не удалось загрузить сайты';
    } finally {
        loading.value = false;
    }
}

async function onDeleteClick(site, close) {
    close();

    if (!window.confirm(`Удалить «${site.name}»?`)) {
        return;
    }

    busyId.value = site.id;
    error.value = '';

    try {
        await deleteSite(site.id);
        sites.value = sites.value.filter((item) => item.id !== site.id);
    } catch (e) {
        error.value = e.response?.data?.message || 'Не удалось удалить сайт';
    } finally {
        busyId.value = null;
    }
}

onMounted(async () => {
    if (route.query.google === 'connected') {
        toast.show({ ok: true, message: 'Аккаунт Google подключён.' });
    } else if (route.query.google === 'error') {
        toast.show({
            ok: false,
            message: route.query.message || 'Не удалось подключить Google.',
        });
    } else if (route.query.github === 'connected') {
        toast.show({ ok: true, message: 'Аккаунт GitHub подключён.' });
    } else if (route.query.github === 'error') {
        toast.show({
            ok: false,
            message: route.query.message || 'Не удалось подключить GitHub.',
        });
    }

    await reload();
});
</script>
