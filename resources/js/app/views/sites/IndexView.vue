<template>
    <div class="page-app-sites">
        <div class="page-app-sites__header">
            <div>
                <h1 class="page-app-sites__title">{{ t('sites.title') }}</h1>
                <p class="page-app-sites__lede">{{ t('sites.lede') }}</p>
            </div>
            <RouterLink class="btn btn-primary" :to="{ name: 'sites.create' }">
                {{ t('common.add') }}
            </RouterLink>
        </div>

        <AppLoader
            v-if="loading"
            block
            :label="t('common.loading')"
        />
        <div v-else-if="error" class="alert alert-danger py-2">{{ error }}</div>

        <div
            v-else-if="!sites.length"
            class="page-app-sites__empty text-muted"
        >
            {{ t('sites.empty') }}
        </div>

        <div
            v-else
            class="page-app-sites__grid"
            :class="{ 'is-loading': busyId !== null }"
        >
            <article
                v-for="site in sites"
                :key="site.id"
                class="page-app-sites__card"
                role="link"
                tabindex="0"
                @click="openSite(site)"
                @keydown.enter.prevent="openSite(site)"
            >
                <div class="page-app-sites__card-top">
                    <div class="page-app-sites__card-copy">
                        <h2 class="page-app-sites__name">{{ site.name }}</h2>
                        <p class="page-app-sites__card-url text-muted">{{ site.url }}</p>
                    </div>
                    <div
                        class="page-app-sites__card-actions"
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
                                    {{ t('common.edit') }}
                                </RouterLink>
                                <button
                                    type="button"
                                    class="row-actions-menu__item is-danger"
                                    role="menuitem"
                                    :disabled="busyId === site.id"
                                    @click="onDeleteClick(site, close)"
                                >
                                    {{ t('common.delete') }}
                                </button>
                            </template>
                        </RowActionsMenu>
                    </div>
                </div>

                <div class="page-app-sites__card-footer">
                    <SiteIntegrationIcons
                        :site="site"
                        :show-status="false"
                    />
                    <p class="page-app-sites__card-sync text-muted">
                        <span class="page-app-sites__card-sync-label">{{ t('sites.sync') }}</span>
                        {{ syncLabel(site) }}
                    </p>
                </div>
            </article>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLoader from '../../../shared/components/AppLoader.vue';
import RowActionsMenu from '../../../shared/components/RowActionsMenu.vue';
import SiteIntegrationIcons from '../../../shared/components/SiteIntegrationIcons.vue';
import {
    formatSiteSyncedAt,
    latestSiteSyncedAt,
} from '../../../shared/siteIntegrations';
import { toast } from '../../../shared/toast';
import { deleteSite, listSites } from '../../api/sites';
import { useI18n } from '../../../shared/i18n';

const route = useRoute();
const router = useRouter();
const { t, intlLocale } = useI18n();

const sites = ref([]);
const loading = ref(true);
const error = ref('');
const busyId = ref(null);

function openSite(site) {
    if (busyId.value === site.id) {
        return;
    }

    router.push({ name: 'sites.show', params: { id: site.id } });
}

function syncLabel(site) {
    return formatSiteSyncedAt(latestSiteSyncedAt(site), intlLocale(), t('sites.neverSynced'));
}

async function reload() {
    loading.value = true;
    error.value = '';

    try {
        const response = await listSites();
        sites.value = response.data ?? [];
    } catch (e) {
        error.value = e.response?.data?.message || t('sites.loadFailed');
    } finally {
        loading.value = false;
    }
}

async function onDeleteClick(site, close) {
    close();

    if (!window.confirm(t('sites.confirmDelete', { name: site.name }))) {
        return;
    }

    busyId.value = site.id;
    error.value = '';

    try {
        await deleteSite(site.id);
        sites.value = sites.value.filter((item) => item.id !== site.id);
    } catch (e) {
        error.value = e.response?.data?.message || t('sites.deleteFailed');
    } finally {
        busyId.value = null;
    }
}

onMounted(async () => {
    if (route.query.google === 'connected') {
        toast.show({ ok: true, message: t('sites.googleConnected') });
    } else if (route.query.google === 'error') {
        toast.show({
            ok: false,
            message: route.query.message || t('sites.googleConnectFailed'),
        });
    } else if (route.query.github === 'connected') {
        toast.show({ ok: true, message: t('sites.githubConnected') });
    } else if (route.query.github === 'error') {
        toast.show({
            ok: false,
            message: route.query.message || t('sites.githubConnectFailed'),
        });
    }

    await reload();
});
</script>
