<template>
    <div class="layout-app">
        <aside class="layout-app__sidebar">
            <RouterLink class="layout-app__brand text-decoration-none" :to="{ name: 'sites.index' }">
                <span class="logo">
                    <img class="logo__mark" src="/images/logo.svg" alt="Set33" width="96" height="39">
                </span>
                <span class="layout-app__brand-label">{{ t('layout.brandLabel') }}</span>
            </RouterLink>

            <nav class="layout-app__nav" :aria-label="t('layout.navAria')">
                <RouterLink
                    v-for="item in navItems"
                    :key="item.name"
                    class="layout-app__nav-link"
                    :class="{ 'is-active': item.isActive(route) }"
                    :to="{ name: item.name }"
                >
                    {{ t(item.labelKey) }}
                </RouterLink>
            </nav>

            <div class="layout-app__sidebar-footer">
                <NavUserCard
                    :loading="userLoading"
                    :avatar-url="avatarUrl"
                    :full-name="fullName"
                    :subtitle="user?.email || ''"
                />
            </div>
        </aside>

        <main class="layout-app__main">
            <div class="layout-app__canvas">
                <Breadcrumbs :items="breadcrumbItems" />
                <RouterView v-slot="{ Component, route: pageRoute }">
                    <Transition name="page-fade" mode="out-in">
                        <component :is="Component" :key="pageRoute.path" />
                    </Transition>
                </RouterView>
            </div>
        </main>

        <nav class="layout-app__tabbar" :aria-label="t('layout.tabbarAria')">
            <RouterLink
                v-for="item in navItems"
                :key="item.name"
                class="layout-app__tab"
                :class="{ 'is-active': item.isActive(route) }"
                :to="{ name: item.name }"
            >
                {{ t(item.labelKey) }}
            </RouterLink>
        </nav>

        <AppToastHost />
    </div>
</template>

<script setup>
import { computed, onMounted, provide, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { logout, me } from '../api/auth';
import AppToastHost from '../../shared/components/AppToastHost.vue';
import Breadcrumbs from '../../shared/components/Breadcrumbs.vue';
import NavUserCard from '../../shared/components/NavUserCard.vue';
import { resolveBreadcrumbs } from '../../shared/breadcrumbs';
import { formatUserFio } from '../../shared/userDisplay';
import { useI18n } from '../../shared/i18n';

const route = useRoute();
const router = useRouter();
const { t, setLocale } = useI18n();

const user = ref(null);
const userLoading = ref(true);

const navItems = [
    {
        name: 'sites.index',
        labelKey: 'layout.nav.sites',
        isActive: (r) => String(r.name || '').startsWith('sites'),
    },
    {
        name: 'profile',
        labelKey: 'layout.nav.profile',
        isActive: (r) => r.name === 'profile',
    },
    {
        name: 'ai-services.index',
        labelKey: 'layout.nav.aiServices',
        isActive: (r) => String(r.name || '').startsWith('ai-services'),
    },
];

const breadcrumbItems = computed(() => {
    const items = resolveBreadcrumbs(route);

    if (!items.length) {
        return [];
    }

    return [{ label: t('layout.brandLabel'), name: 'sites.index' }, ...items];
});
const fullName = computed(() => formatUserFio(user.value));
const avatarUrl = computed(() => user.value?.avatar?.sm || user.value?.avatar?.md || '');

async function refreshUser() {
    user.value = await me();

    if (user.value?.locale) {
        setLocale(user.value.locale);
    }
}

provide('refreshAppUser', refreshUser);

onMounted(async () => {
    try {
        await refreshUser();
    } catch (e) {
        await logout();
        await router.push({ name: 'login' });
    } finally {
        userLoading.value = false;
    }
});

</script>
