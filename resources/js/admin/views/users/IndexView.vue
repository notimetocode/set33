<template>
    <AdminLayout>
        <div class="page-admin-users">
            <div class="page-admin-users__header">
                <div>
                    <h1 class="h4 page-admin-users__title">Пользователи</h1>
                    <p class="page-admin-users__subtitle">Список аккаунтов системы</p>
                </div>
            </div>

            <form class="page-admin-users__filters row g-2 mb-3" @submit.prevent="reload">
                <div class="col-md-4">
                    <input
                        v-model="filters.q"
                        type="search"
                        class="form-control"
                        placeholder="Поиск по имени, e-mail, телефону"
                    >
                </div>
                <div class="col-md-3">
                    <select v-model="filters.role" class="form-select">
                        <option value="">Все роли</option>
                        <option
                            v-for="role in meta.roles"
                            :key="role.value"
                            :value="role.value"
                        >
                            {{ role.label }}
                        </option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" type="submit" :disabled="loading">
                        Найти
                    </button>
                </div>
            </form>

            <div v-if="loading">
                <AppLoader block label="Загрузка…" />
            </div>
            <div v-else-if="error" class="alert alert-danger py-2">{{ error }}</div>

            <div v-else class="data-table">
                <table class="table table-sm table-hover align-middle data-table__grid">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Имя</th>
                            <th>E-mail</th>
                            <th>Роль</th>
                            <th>Город</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users" :key="user.id">
                            <td>{{ user.id }}</td>
                            <td>{{ user.name }}</td>
                            <td>{{ user.email }}</td>
                            <td>{{ user.role_label }}</td>
                            <td>{{ user.city?.name || '—' }}</td>
                        </tr>
                        <tr v-if="!users.length">
                            <td colspan="5" class="text-muted">Пользователи не найдены</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import AppLoader from '../../../shared/components/AppLoader.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { getUsersMeta, listUsers } from '../../api/users';

const users = ref([]);
const meta = reactive({ roles: [], genders: [] });
const filters = reactive({ q: '', role: '' });
const loading = ref(true);
const error = ref('');

async function reload() {
    loading.value = true;
    error.value = '';

    try {
        const response = await listUsers({
            q: filters.q,
            role: filters.role,
        });

        users.value = response.data ?? [];
    } catch (e) {
        error.value = e.response?.data?.message || 'Не удалось загрузить пользователей';
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    try {
        const usersMeta = await getUsersMeta();
        meta.roles = usersMeta.roles ?? [];
        meta.genders = usersMeta.genders ?? [];
    } catch (e) {
        // meta optional for first paint
    }

    await reload();
});
</script>
