<template>
    <div class="layout-app">
        <aside class="layout-app__sidebar">
            <RouterLink class="layout-app__brand text-decoration-none" :to="{ name: 'home' }">
                <span class="logo logo--sm">
                    <img class="logo__mark" src="/images/logo.svg" alt="" width="32" height="32">
                </span>
                Личный кабинет
            </RouterLink>

            <div class="layout-app__nav-header">
                <NavUserCard
                    :loading="userLoading"
                    :avatar-url="avatarUrl"
                    :full-name="fullName"
                    :subtitle="user?.email || ''"
                />
            </div>

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
                <LogoutButton @click="onLogout" />
            </div>
        </aside>

        <main class="layout-app__main">
            <Breadcrumbs :items="breadcrumbItems" />
            <RouterView v-slot="{ Component, route: pageRoute }">
                <Transition name="page-fade" mode="out-in">
                    <component :is="Component" :key="pageRoute.path" />
                </Transition>
            </RouterView>
        </main>

        <nav class="layout-app__tabbar" aria-label="Основная навигация">
            <RouterLink
                v-for="item in navItems"
                :key="item.name"
                class="layout-app__tab"
                :class="{ 'is-active': item.isActive(route) }"
                :to="{ name: item.name }"
            >
                <span class="layout-app__tab-icon" aria-hidden="true">
                    <FontAwesomeIcon :icon="item.icon" />
                </span>
                <span class="layout-app__tab-label">{{ item.label }}</span>
            </RouterLink>
        </nav>

        <AppToastHost />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { logout, me } from '../api/auth';
import AppToastHost from '../../shared/components/AppToastHost.vue';
import Breadcrumbs from '../../shared/components/Breadcrumbs.vue';
import LogoutButton from '../../shared/components/LogoutButton.vue';
import NavUserCard from '../../shared/components/NavUserCard.vue';
import { resolveBreadcrumbs } from '../../shared/breadcrumbs';
import { formatUserFio } from '../../shared/userDisplay';

const route = useRoute();
const router = useRouter();

const user = ref(null);
const userLoading = ref(true);

const navItems = [
    {
        name: 'home',
        label: 'Главная',
        icon: ['fas', 'house'],
        isActive: (r) => r.name === 'home',
    },
    {
        name: 'sites.index',
        label: 'Сайты',
        icon: ['fas', 'globe'],
        isActive: (r) => String(r.name || '').startsWith('sites'),
    },
    {
        name: 'profile',
        label: 'Профиль',
        icon: ['fas', 'user'],
        isActive: (r) => r.name === 'profile',
    },
    {
        name: 'ai-services.index',
        label: 'AI-сервисы',
        icon: ['fas', 'robot'],
        isActive: (r) => String(r.name || '').startsWith('ai-services'),
    },
];

const breadcrumbItems = computed(() => {
    const items = resolveBreadcrumbs(route);

    if (!items.length) {
        return [];
    }

    return [{ label: 'Личный кабинет', name: 'home' }, ...items];
});
const fullName = computed(() => formatUserFio(user.value));
const avatarUrl = computed(() => user.value?.avatar?.sm || user.value?.avatar?.md || '');

onMounted(async () => {
    try {
        user.value = await me();
    } catch (e) {
        await logout();
        await router.push({ name: 'login' });
    } finally {
        userLoading.value = false;
    }
});

async function onLogout() {
    await logout();
    await router.push({ name: 'login' });
}
</script>
