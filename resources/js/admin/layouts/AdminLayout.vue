<template>
    <div class="layout-admin" :class="{ 'is-nav-open': navOpen }">
        <header class="layout-admin__topbar">
            <RouterLink class="layout-admin__brand text-decoration-none" :to="{ name: 'dashboard' }">
                <span class="logo logo--sm">
                    <img class="logo__mark" src="/images/logo.svg" alt="Set33" width="96" height="20">
                </span>
                Админ-панель
            </RouterLink>

            <button
                class="layout-admin__burger"
                type="button"
                :aria-expanded="navOpen ? 'true' : 'false'"
                aria-controls="admin-nav"
                :aria-label="navOpen ? 'Закрыть меню' : 'Открыть меню'"
                @click="toggleNav"
            >
                <span class="layout-admin__burger-lines" aria-hidden="true">
                    <span />
                    <span />
                    <span />
                </span>
            </button>
        </header>

        <div
            class="layout-admin__backdrop"
            aria-hidden="true"
            @click="closeNav"
        />

        <aside
            id="admin-nav"
            class="layout-admin__sidebar"
            :aria-hidden="sidebarAriaHidden"
        >
            <RouterLink
                class="layout-admin__brand layout-admin__brand--sidebar text-decoration-none"
                :to="{ name: 'dashboard' }"
            >
                <span class="logo logo--sm">
                    <img class="logo__mark" src="/images/logo.svg" alt="Set33" width="96" height="20">
                </span>
                Админ-панель
            </RouterLink>

            <nav class="layout-admin__nav" aria-label="Навигация панели администратора">
                <RouterLink
                    class="layout-admin__nav-link"
                    :class="{ 'is-active': route.name === 'dashboard' }"
                    :to="{ name: 'dashboard' }"
                    @click="closeNav"
                >
                    Обзор
                </RouterLink>
                <RouterLink
                    class="layout-admin__nav-link"
                    :class="{ 'is-active': isUsers }"
                    :to="{ name: 'users.index' }"
                    @click="closeNav"
                >
                    Пользователи
                </RouterLink>
            </nav>

            <div class="layout-admin__sidebar-footer">
                <LogoutButton @click="onLogout" />
            </div>
        </aside>

        <main class="layout-admin__main">
            <Breadcrumbs :items="breadcrumbItems" />
            <slot />
        </main>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { logout } from '../api/auth';
import Breadcrumbs from '../../shared/components/Breadcrumbs.vue';
import LogoutButton from '../../shared/components/LogoutButton.vue';
import { resolveBreadcrumbs } from '../../shared/breadcrumbs';

const MOBILE_NAV_QUERY = '(max-width: 767.98px)';

const route = useRoute();
const router = useRouter();

const navOpen = ref(false);
const isMobileNav = ref(false);

const breadcrumbItems = computed(() => resolveBreadcrumbs(route));
const isUsers = computed(() => String(route.name || '').startsWith('users'));
const sidebarAriaHidden = computed(() => (isMobileNav.value && !navOpen.value ? 'true' : 'false'));

let mobileMediaQuery = null;

function syncMobileNav() {
    isMobileNav.value = Boolean(mobileMediaQuery?.matches);

    if (!isMobileNav.value) {
        closeNav();
    }
}

function toggleNav() {
    navOpen.value = !navOpen.value;
}

function closeNav() {
    navOpen.value = false;
}

function onKeydown(event) {
    if (event.key === 'Escape' && navOpen.value) {
        closeNav();
    }
}

watch(navOpen, (open) => {
    document.body.classList.toggle('admin-nav-open', open);
});

watch(() => route.fullPath, closeNav);

onMounted(() => {
    mobileMediaQuery = window.matchMedia(MOBILE_NAV_QUERY);
    syncMobileNav();
    mobileMediaQuery.addEventListener('change', syncMobileNav);
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    mobileMediaQuery?.removeEventListener('change', syncMobileNav);
    window.removeEventListener('keydown', onKeydown);
    document.body.classList.remove('admin-nav-open');
});

async function onLogout() {
    closeNav();
    await logout();
    await router.push({ name: 'login' });
}
</script>
