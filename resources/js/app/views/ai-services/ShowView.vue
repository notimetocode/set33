<template>
    <div class="page-app-ai-services page-app-ai-services--show">
        <div class="page-app-ai-services__header">
            <div>
                <p class="page-app-ai-services__eyebrow">{{ t('aiServices.title') }}</p>
                <h1 class="page-app-ai-services__title">
                    {{ service?.name || t('aiServices.show.modelTest') }}
                </h1>
                <p class="page-app-ai-services__lede">
                    {{ t('aiServices.show.lede') }}
                </p>
            </div>
            <div class="page-app-ai-services__header-actions">
                <RouterLink
                    v-if="service && !service.is_global"
                    class="btn btn-secondary"
                    :to="{ name: 'ai-services.edit', params: { id: service.id } }"
                >
                    {{ t('common.edit') }}
                </RouterLink>
                <RouterLink
                    class="btn btn-secondary"
                    :to="{ name: 'ai-services.index' }"
                >
                    {{ t('common.backToList') }}
                </RouterLink>
            </div>
        </div>

        <AppLoader
            v-if="loading"
            block
            :label="t('common.loading')"
        />
        <div v-else-if="loadError" class="alert alert-danger py-2">{{ loadError }}</div>

        <template v-else-if="service">
            <div class="page-app-ai-services__summary">
                <div class="page-app-ai-services__summary-main">
                    <span class="page-app-ai-services__summary-name">{{ service.type_label }}</span>
                    <span class="page-app-ai-services__summary-meta">
                        {{ service.settings?.model || t('aiServices.show.modelNotSet') }}
                    </span>
                </div>
                <div class="page-app-ai-services__summary-tags">
                    <span
                        class="status-tag"
                        :class="service.api_key_set ? 'status-tag--ok' : 'status-tag--muted'"
                    >
                        {{ service.api_key_set ? t('aiServices.show.keySet') : t('aiServices.show.keyNotSet') }}
                    </span>
                    <span
                        class="status-tag"
                        :class="statusTagClass(service.status)"
                    >
                        {{ service.status_label }}
                    </span>
                </div>
            </div>

            <form
                class="page-app-ai-services__panel"
                @submit.prevent="onSubmit"
            >
                <div class="page-app-ai-services__panel-body">
                    <div class="page-app-ai-services__field">
                        <label class="form-label" for="ai-service-prompt">{{ t('aiServices.show.prompt') }}</label>
                        <textarea
                            id="ai-service-prompt"
                            v-model="prompt"
                            class="form-control"
                            :class="{ 'is-invalid': fieldError }"
                            rows="6"
                            maxlength="10000"
                            :placeholder="t('aiServices.show.promptPlaceholder')"
                            :disabled="sending"
                        />
                        <div v-if="fieldError" class="invalid-feedback">{{ fieldError }}</div>
                        <div v-else class="form-text">
                            {{ t('aiServices.show.promptHint') }}
                        </div>
                    </div>

                    <div class="page-app-ai-services__form-actions">
                        <button
                            class="btn btn-primary"
                            type="submit"
                            :disabled="sending || !prompt.trim()"
                        >
                            <AppLoader
                                v-if="sending"
                                size="sm"
                            />
                            <span>{{ sending ? t('aiServices.show.sending') : t('aiServices.show.send') }}</span>
                        </button>
                        <button
                            v-if="reply || error"
                            class="btn btn-secondary"
                            type="button"
                            :disabled="sending"
                            @click="clearResult"
                        >
                            {{ t('aiServices.show.clearReply') }}
                        </button>
                    </div>

                    <div
                        v-if="error"
                        class="alert alert-danger py-2 mb-0"
                    >
                        {{ error }}
                    </div>

                    <div
                        v-if="reply"
                        class="page-app-ai-services__reply"
                    >
                        <div class="page-app-ai-services__reply-head">
                            <h2 class="page-app-ai-services__reply-title">{{ t('aiServices.show.reply') }}</h2>
                            <span
                                v-if="replyModel"
                                class="page-app-ai-services__reply-meta"
                            >
                                {{ replyModel }}
                            </span>
                        </div>
                        <AppMarkdown
                            class="page-app-ai-services__reply-body"
                            :source="reply"
                        />
                    </div>
                </div>
            </form>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AppLoader from '../../../shared/components/AppLoader.vue';
import AppMarkdown from '../../../shared/components/AppMarkdown.vue';
import { generateAiServiceContent, getAiService } from '../../api/aiServices';
import { useI18n } from '../../../shared/i18n';

const route = useRoute();
const { t } = useI18n();

const service = ref(null);
const loading = ref(true);
const loadError = ref('');
const prompt = ref('');
const sending = ref(false);
const fieldError = ref('');
const error = ref('');
const reply = ref('');
const replyModel = ref('');

function statusTagClass(status) {
    if (status === 'ok') {
        return 'status-tag--ok';
    }

    if (status === 'error') {
        return 'status-tag--danger';
    }

    return 'status-tag--muted';
}

function clearResult() {
    error.value = '';
    reply.value = '';
    replyModel.value = '';
}

async function loadService() {
    loading.value = true;
    loadError.value = '';
    service.value = null;
    clearResult();
    prompt.value = '';
    fieldError.value = '';

    try {
        service.value = await getAiService(route.params.id);
    } catch (e) {
        loadError.value = e.response?.data?.message || t('aiServices.loadFailed');
    } finally {
        loading.value = false;
    }
}

async function onSubmit() {
    fieldError.value = '';
    error.value = '';
    reply.value = '';
    replyModel.value = '';

    const text = prompt.value.trim();

    if (!text) {
        fieldError.value = t('aiServices.show.enterPrompt');

        return;
    }

    sending.value = true;

    try {
        const result = await generateAiServiceContent(route.params.id, text);

        if (result.ok) {
            reply.value = result.reply || '';
            replyModel.value = result.model || service.value?.settings?.model || '';
        } else {
            error.value = result.message || t('aiServices.show.replyFailed');
        }
    } catch (e) {
        const validation = e.response?.data?.errors?.prompt?.[0];
        const apiMessage = e.response?.data?.message;

        if (validation) {
            fieldError.value = validation;
        } else {
            error.value = apiMessage || t('aiServices.show.sendFailed');
        }
    } finally {
        sending.value = false;
    }
}

watch(
    () => route.params.id,
    () => {
        loadService();
    },
);

onMounted(loadService);
</script>
