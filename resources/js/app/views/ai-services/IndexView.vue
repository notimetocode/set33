<template>
    <div class="page-app-ai-services">
        <div class="page-app-ai-services__header">
            <div>
                <h1 class="h4 mb-1">AI-сервисы</h1>
                <p class="text-muted mb-0">Подключения к моделям и сервисам ИИ</p>
            </div>
            <RouterLink class="btn btn-primary" :to="{ name: 'ai-services.create' }">
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
                        <th>Сервис</th>
                        <th class="page-app-ai-services__col-key">API-ключ</th>
                        <th class="page-app-ai-services__col-status">Статус</th>
                        <th class="page-app-ai-services__col-actions">
                            <span class="visually-hidden">Действия</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="service in services"
                        :key="service.id"
                        class="page-app-ai-services__row"
                        role="link"
                        tabindex="0"
                        @click="openService(service)"
                        @keydown.enter.prevent="openService(service)"
                    >
                        <td>
                            <span class="page-app-ai-services__service-name">
                                {{ service.name }}
                            </span>
                        </td>
                        <td class="page-app-ai-services__col-key">
                            <span
                                class="status-tag"
                                :class="service.api_key_set ? 'status-tag--ok' : 'status-tag--muted'"
                            >
                                {{ service.api_key_set ? 'Задан' : 'Нет' }}
                            </span>
                        </td>
                        <td class="page-app-ai-services__col-status">
                            <AppLoader
                                v-if="busyId === service.id && busyAction === 'check'"
                                size="sm"
                                label="Проверка…"
                            />
                            <span
                                v-else
                                class="status-tag"
                                :class="statusTagClass(service.status)"
                            >
                                {{ service.status_label }}
                            </span>
                        </td>
                        <td
                            class="page-app-ai-services__col-actions"
                            @click.stop
                            @keydown.stop
                        >
                            <RowActionsMenu :disabled="busyId === service.id">
                                <template #default="{ close }">
                                    <button
                                        type="button"
                                        class="row-actions-menu__item"
                                        role="menuitem"
                                        :disabled="busyId === service.id"
                                        @click="onCheckClick(service, close)"
                                    >
                                        Проверить
                                    </button>
                                    <RouterLink
                                        class="row-actions-menu__item"
                                        role="menuitem"
                                        :to="{ name: 'ai-services.edit', params: { id: service.id } }"
                                        @click="close"
                                    >
                                        Изменить
                                    </RouterLink>
                                    <button
                                        type="button"
                                        class="row-actions-menu__item is-danger"
                                        role="menuitem"
                                        :disabled="busyId === service.id"
                                        @click="onDeleteClick(service, close)"
                                    >
                                        Удалить
                                    </button>
                                </template>
                            </RowActionsMenu>
                        </td>
                    </tr>
                    <tr v-if="!services.length">
                        <td colspan="4" class="text-muted">Пока нет AI-сервисов</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppModal
            v-model:open="checkModal.open"
            :title="checkModal.title"
            :message="checkModal.message"
            :variant="checkModal.variant"
        >
            <p
                v-if="checkModal.reply"
                class="page-app-ai-services__check-reply"
            >
                Ответ модели: «{{ checkModal.reply }}»
            </p>
        </AppModal>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppLoader from '../../../shared/components/AppLoader.vue';
import AppModal from '../../../shared/components/AppModal.vue';
import RowActionsMenu from '../../../shared/components/RowActionsMenu.vue';
import { checkAiService, deleteAiService, listAiServices } from '../../api/aiServices';

const router = useRouter();
const services = ref([]);
const loading = ref(true);
const error = ref('');
const busyId = ref(null);
const busyAction = ref(null);

const checkModal = reactive({
    open: false,
    title: '',
    message: '',
    reply: '',
    variant: '',
});

function statusTagClass(status) {
    if (status === 'ok') {
        return 'status-tag--ok';
    }

    if (status === 'error') {
        return 'status-tag--danger';
    }

    return 'status-tag--muted';
}

function openService(service) {
    if (busyId.value === service.id) {
        return;
    }

    router.push({ name: 'ai-services.show', params: { id: service.id } });
}

function applyService(updated) {
    const index = services.value.findIndex((item) => item.id === updated.id);

    if (index === -1) {
        return;
    }

    services.value[index] = updated;
}

async function reload() {
    loading.value = true;
    error.value = '';

    try {
        const response = await listAiServices();
        services.value = response.data ?? [];
    } catch (e) {
        error.value = e.response?.data?.message || 'Не удалось загрузить AI-сервисы';
    } finally {
        loading.value = false;
    }
}

async function onCheckClick(service, close) {
    close();
    busyId.value = service.id;
    busyAction.value = 'check';
    error.value = '';

    try {
        const result = await checkAiService(service.id);

        if (result.data) {
            applyService(result.data);
        }

        checkModal.title = result.title || (result.ok ? 'Проверка успешна' : 'Проверка не пройдена');
        checkModal.message = result.message || '';
        checkModal.reply = result.reply || '';
        checkModal.variant = result.ok ? 'success' : 'danger';
        checkModal.open = true;
    } catch (e) {
        checkModal.title = 'Проверка не пройдена';
        checkModal.message = e.response?.data?.message || 'Не удалось выполнить проверку';
        checkModal.reply = '';
        checkModal.variant = 'danger';
        checkModal.open = true;
    } finally {
        busyId.value = null;
        busyAction.value = null;
    }
}

async function onDeleteClick(service, close) {
    close();
    await onDelete(service);
}

async function onDelete(service) {
    if (!window.confirm(`Удалить «${service.name}»?`)) {
        return;
    }

    busyId.value = service.id;
    busyAction.value = 'delete';
    error.value = '';

    try {
        await deleteAiService(service.id);
        services.value = services.value.filter((item) => item.id !== service.id);
    } catch (e) {
        error.value = e.response?.data?.message || 'Не удалось удалить AI-сервис';
    } finally {
        busyId.value = null;
        busyAction.value = null;
    }
}

onMounted(reload);
</script>
