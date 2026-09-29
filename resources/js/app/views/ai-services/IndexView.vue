<template>
    <div class="page-app-ai-services">
        <div class="page-app-ai-services__header">
            <div>
                <h1 class="page-app-ai-services__title">AI-сервисы</h1>
                <p class="page-app-ai-services__lede">Подключения к моделям и сервисам ИИ</p>
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

        <template v-else>
            <section
                v-if="globalServices.length"
                class="page-app-ai-services__section"
                aria-labelledby="ai-services-global-title"
            >
                <div class="page-app-ai-services__section-head">
                    <h2 id="ai-services-global-title" class="page-app-ai-services__section-title">
                        Общие сервисы
                    </h2>
                    <p class="page-app-ai-services__section-lede">
                        Доступны всем пользователям. Настраивает только администратор.
                    </p>
                </div>

                <div class="data-table">
                    <table class="table table-sm table-hover align-middle data-table__grid">
                        <thead>
                            <tr>
                                <th>Сервис</th>
                                <th class="page-app-ai-services__col-status">Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="service in globalServices"
                                :key="service.id"
                                class="page-app-ai-services__row page-app-ai-services__row--readonly"
                            >
                                <td>
                                    <span class="page-app-ai-services__service-name">
                                        {{ service.name }}
                                    </span>
                                </td>
                                <td class="page-app-ai-services__col-status">
                                    <span
                                        class="status-tag"
                                        :class="statusTagClass(service.status)"
                                    >
                                        {{ service.status_label }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                class="page-app-ai-services__section"
                aria-labelledby="ai-services-own-title"
            >
                <div class="page-app-ai-services__section-head page-app-ai-services__section-head--row">
                    <div>
                        <h2 id="ai-services-own-title" class="page-app-ai-services__section-title">
                            Мои сервисы
                        </h2>
                        <p class="page-app-ai-services__section-lede">
                            Ваши личные подключения к моделям ИИ
                        </p>
                    </div>
                </div>

                <div
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
                                v-for="service in ownServices"
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
                            <tr v-if="!ownServices.length">
                                <td colspan="4" class="text-muted">Пока нет личных AI-сервисов</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>

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
import { computed, onMounted, reactive, ref } from 'vue';
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

const globalServices = computed(() => services.value.filter((service) => service.is_global));
const ownServices = computed(() => services.value.filter((service) => !service.is_global));

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
    if (busyId.value === service.id || service.is_global) {
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
