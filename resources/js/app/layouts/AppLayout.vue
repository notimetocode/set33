<template>
    <div class="layout-app">
        <aside class="layout-app__sidebar">
            <RouterLink class="layout-app__brand text-decoration-none" :to="{ name: 'sites.index' }">
                <span class="logo">
                    <img class="logo__mark" src="/images/logo.svg" alt="Set33" width="96" height="39">
                </span>
                <span class="layout-app__brand-label">Личный кабинет</span>
            </RouterLink>

            <nav class="layout-app__nav" aria-label="Навигация личного кабинета">
                <RouterLink
                    v-for="item in navItems"
                    :key="item.name"
                    class="layout-app__nav-link"
                    :class="{ 'is-active': item.isActive(route) }"
                    :to="{ name: item.name }"
                >
                    {{ item.label }}
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

        <nav class="layout-app__tabbar" aria-label="Основная навигация">
            <RouterLink
                v-for="item in navItems"
                :key="item.name"
                class="layout-app__tab"
                :class="{ 'is-active': item.isActive(route) }"
                :to="{ name: item.name }"
            >
                {{ item.label }}
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

const route = useRoute();
const router = useRouter();

const user = ref(null);
const userLoading = ref(true);

const navItems = [
    {
        name: 'sites.index',
        label: 'Сайты',
        isActive: (r) => String(r.name || '').startsWith('sites'),
    },
    {
        name: 'profile',
        label: 'Профиль',
        isActive: (r) => r.name === 'profile',
    },
    {
        name: 'ai-services.index',
        label: 'AI-сервисы',
        isActive: (r) => String(r.name || '').startsWith('ai-services'),
    },
];

const breadcrumbItems = computed(() => {
    const items = resolveBreadcrumbs(route);

    if (!items.length) {
        return [];
    }

    return [{ label: 'Личный кабинет', name: 'sites.index' }, ...items];
});
const fullName = computed(() => formatUserFio(user.value));
const avatarUrl = computed(() => user.value?.avatar?.sm || user.value?.avatar?.md || '');

async function refreshUser() {
    user.value = await me();
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
