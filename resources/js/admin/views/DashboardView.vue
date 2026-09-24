<template>
    <AdminLayout>
        <div class="page-admin-dashboard">
            <div class="page-admin-dashboard__header">
                <div>
                    <h1 class="h4 page-admin-dashboard__title">Обзор</h1>
                    <p class="page-admin-dashboard__subtitle">
                        Сводка по пользователям
                    </p>
                </div>
            </div>

            <div v-if="loading" class="page-admin-dashboard__loading">
                <AppLoader block label="Загрузка статистики…" />
            </div>
            <div v-else-if="error" class="alert alert-danger py-2">{{ error }}</div>

            <template v-else-if="stats">
                <section class="page-admin-dashboard__cards" aria-label="Ключевые показатели">
                    <article
                        v-for="card in summaryCards"
                        :key="card.key"
                        class="page-admin-dashboard__card"
                    >
                        <p class="page-admin-dashboard__card-label">{{ card.label }}</p>
                        <p class="page-admin-dashboard__card-value">{{ formatNumber(card.value) }}</p>
                    </article>
                </section>

                <section class="page-admin-dashboard__panel" aria-labelledby="dash-users">
                    <div class="page-admin-dashboard__panel-head">
                        <h2 id="dash-users" class="page-admin-dashboard__panel-title">Пользователи</h2>
                        <RouterLink class="page-admin-dashboard__panel-link" :to="{ name: 'users.index' }">
                            Все пользователи
                        </RouterLink>
                    </div>

                    <dl class="page-admin-dashboard__metrics">
                        <div class="page-admin-dashboard__metric">
                            <dt>Всего</dt>
                            <dd>{{ formatNumber(stats.users.total) }}</dd>
                        </div>
                        <div class="page-admin-dashboard__metric">
                            <dt>Пользователи</dt>
                            <dd>{{ formatNumber(stats.users.users) }}</dd>
                        </div>
                        <div class="page-admin-dashboard__metric">
                            <dt>Администраторы</dt>
                            <dd>{{ formatNumber(stats.users.admins) }}</dd>
                        </div>
                        <div class="page-admin-dashboard__metric">
                            <dt>За неделю</dt>
                            <dd>{{ formatNumber(stats.users.registered_this_week) }}</dd>
                        </div>
                        <div class="page-admin-dashboard__metric">
                            <dt>За месяц</dt>
                            <dd>{{ formatNumber(stats.users.registered_this_month) }}</dd>
                        </div>
                    </dl>
                </section>
            </template>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import AppLoader from '../../shared/components/AppLoader.vue';
import AdminLayout from '../layouts/AdminLayout.vue';
import { getDashboardStats } from '../api/dashboard';

const stats = ref(null);
const loading = ref(true);
const error = ref('');

const summaryCards = computed(() => {
    if (!stats.value) {
        return [];
    }

    return [
        { key: 'total', label: 'Всего пользователей', value: stats.value.users.total },
        { key: 'users', label: 'Роль «пользователь»', value: stats.value.users.users },
        { key: 'admins', label: 'Администраторы', value: stats.value.users.admins },
        { key: 'week', label: 'Регистраций за неделю', value: stats.value.users.registered_this_week },
    ];
});

function formatNumber(value) {
    return new Intl.NumberFormat('ru-RU').format(Number(value) || 0);
}

onMounted(async () => {
    try {
        stats.value = await getDashboardStats();
    } catch (e) {
        error.value = e.response?.data?.message || 'Не удалось загрузить обзор';
    } finally {
        loading.value = false;
    }
});
</script>
