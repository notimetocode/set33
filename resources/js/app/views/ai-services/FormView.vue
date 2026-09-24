<template>
    <div class="page-app-ai-services page-app-ai-services--form">
        <div class="page-app-ai-services__header">
            <div>
                <p class="page-app-ai-services__eyebrow">AI-сервисы</p>
                <h1 class="page-app-ai-services__title">
                    {{ isEdit ? 'Изменить сервис' : 'Новый сервис' }}
                </h1>
                <p v-if="!isEdit && step === 2" class="page-app-ai-services__lede">
                    Настройте модель и параметры генерации
                </p>
            </div>
            <RouterLink
                class="btn btn-secondary"
                :to="{ name: 'ai-services.index' }"
            >
                К списку
            </RouterLink>
        </div>

        <div v-if="loading" class="page-app-ai-services__state">
            <AppLoader block label="Загрузка…" />
        </div>
        <div v-else-if="loadError" class="alert alert-danger py-2">{{ loadError }}</div>

        <template v-else>
            <nav
                v-if="!isEdit"
                class="page-app-ai-services__stepper"
                aria-label="Шаги формы"
            >
                <div
                    class="page-app-ai-services__step"
                    :class="{
                        'is-active': step === 1,
                        'is-done': step > 1,
                    }"
                >
                    <span class="page-app-ai-services__step-index" aria-hidden="true">
                        <FontAwesomeIcon v-if="step > 1" :icon="['fas', 'check']" />
                        <template v-else>1</template>
                    </span>
                    <span class="page-app-ai-services__step-copy">
                        <span class="page-app-ai-services__step-label">Подключение</span>
                        <span class="page-app-ai-services__step-hint">Тип и ключ</span>
                    </span>
                </div>

                <div class="page-app-ai-services__step-line" :class="{ 'is-done': step > 1 }" aria-hidden="true" />

                <div
                    class="page-app-ai-services__step"
                    :class="{ 'is-active': step === 2 }"
                >
                    <span class="page-app-ai-services__step-index" aria-hidden="true">2</span>
                    <span class="page-app-ai-services__step-copy">
                        <span class="page-app-ai-services__step-label">Параметры</span>
                        <span class="page-app-ai-services__step-hint">Модель и генерация</span>
                    </span>
                </div>
            </nav>

            <form class="page-app-ai-services__panel" @submit.prevent="onSubmit">
                <div v-if="formError" class="alert alert-danger py-2 mb-3">{{ formError }}</div>

                <Transition name="ai-step" mode="out-in">
                    <div v-if="step === 1" key="step-1" class="page-app-ai-services__panel-body">
                        <div class="page-app-ai-services__field">
                            <span class="form-label" id="ai-service-type-label">Тип сервиса</span>
                            <div
                                class="page-app-ai-services__type-grid"
                                role="radiogroup"
                                aria-labelledby="ai-service-type-label"
                            >
                                <label
                                    v-for="type in types"
                                    :key="type.value"
                                    class="page-app-ai-services__type-option"
                                    :class="{
                                        'is-active': form.type === type.value,
                                        'is-invalid': fieldError('type'),
                                    }"
                                >
                                    <input
                                        v-model="form.type"
                                        type="radio"
                                        name="ai-service-type"
                                        :value="type.value"
                                        required
                                    >
                                    <span class="page-app-ai-services__type-icon" aria-hidden="true">
                                        <FontAwesomeIcon :icon="['fas', 'robot']" />
                                    </span>
                                    <span class="page-app-ai-services__type-text">
                                        <span class="page-app-ai-services__type-name">{{ type.label }}</span>
                                        <span class="page-app-ai-services__type-desc">
                                            Генеративные модели Google
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <div v-if="fieldError('type')" class="invalid-feedback d-block">
                                {{ fieldError('type') }}
                            </div>
                        </div>

                        <div class="page-app-ai-services__field">
                            <label class="form-label" for="ai-service-api-key">
                                <FontAwesomeIcon class="page-app-ai-services__label-icon" :icon="['fas', 'key']" />
                                API-ключ
                            </label>
                            <input
                                id="ai-service-api-key"
                                v-model="form.api_key"
                                type="password"
                                class="form-control"
                                :class="{ 'is-invalid': fieldError('api_key') }"
                                :required="!isEdit || !apiKeySet"
                                autocomplete="off"
                                :placeholder="isEdit && apiKeySet ? 'Оставьте пустым, чтобы не менять' : 'Вставьте ключ из Google AI Studio'"
                            >
                            <div v-if="fieldError('api_key')" class="invalid-feedback">{{ fieldError('api_key') }}</div>
                            <div v-else-if="isEdit && apiKeySet" class="form-text">
                                Ключ уже сохранён. Введите новый, только если нужно заменить.
                            </div>
                        </div>

                        <div class="page-app-ai-services__form-actions">
                            <button
                                class="btn btn-primary"
                                type="button"
                                :disabled="loadingModels"
                                @click="goToStep2"
                            >
                                <AppLoader
                                    v-if="loadingModels"
                                    size="sm"
                                />
                                <span>{{ loadingModels ? 'Загрузка моделей…' : 'Далее' }}</span>
                                <FontAwesomeIcon
                                    v-if="!loadingModels"
                                    :icon="['fas', 'arrow-right']"
                                />
                            </button>
                            <RouterLink
                                class="btn btn-link page-app-ai-services__cancel"
                                :to="{ name: 'ai-services.index' }"
                            >
                                Отмена
                            </RouterLink>
                        </div>
                    </div>

                    <div v-else key="step-2" class="page-app-ai-services__panel-body">
                        <div v-if="!isEdit" class="page-app-ai-services__summary">
                            <div class="page-app-ai-services__summary-main">
                                <span class="page-app-ai-services__summary-name">{{ typeLabel }}</span>
                                <span class="page-app-ai-services__summary-meta">
                                    {{ form.api_key || apiKeySet ? 'ключ задан' : 'ключ не задан' }}
                                </span>
                            </div>
                            <button
                                type="button"
                                class="btn btn-sm btn-secondary"
                                :disabled="saving"
                                @click="goToStep1"
                            >
                                Изменить
                            </button>
                        </div>

                        <div v-if="modelsError" class="alert alert-warning py-2 mb-0">{{ modelsError }}</div>

                        <template v-if="form.type === 'gemini'">
                            <div class="page-app-ai-services__field">
                                <label class="form-label" for="ai-service-model">Модель</label>
                                <select
                                    id="ai-service-model"
                                    v-model="form.settings.model"
                                    class="form-select"
                                    :class="{ 'is-invalid': fieldError('settings.model') }"
                                    required
                                >
                                    <option disabled value="">Выберите модель</option>
                                    <option
                                        v-for="model in modelOptions"
                                        :key="model.id"
                                        :value="model.id"
                                    >
                                        {{ modelLabel(model) }}
                                    </option>
                                </select>
                                <div v-if="fieldError('settings.model')" class="invalid-feedback">
                                    {{ fieldError('settings.model') }}
                                </div>
                                <div v-else-if="!models.length" class="form-text text-danger">
                                    {{ isEdit
                                        ? 'Список моделей недоступен. Можно оставить текущую модель.'
                                        : 'Список моделей пуст. Вернитесь назад и проверьте API-ключ.' }}
                                </div>
                                <div v-else class="form-text">
                                    Загружено моделей: {{ models.length }}
                                </div>
                            </div>

                            <div class="page-app-ai-services__field">
                                <label class="form-label" for="ai-service-system">Системная инструкция</label>
                                <textarea
                                    id="ai-service-system"
                                    v-model="form.settings.system_instruction"
                                    class="form-control"
                                    rows="4"
                                    :class="{ 'is-invalid': fieldError('settings.system_instruction') }"
                                    placeholder="Необязательно. Например: отвечай кратко и по делу"
                                />
                                <div v-if="fieldError('settings.system_instruction')" class="invalid-feedback">
                                    {{ fieldError('settings.system_instruction') }}
                                </div>
                            </div>

                            <div class="page-app-ai-services__params">
                                <h2 class="page-app-ai-services__params-title">Параметры генерации</h2>
                                <div class="page-app-ai-services__params-grid">
                                    <div class="page-app-ai-services__param">
                                        <label class="form-label" for="ai-service-temperature">Температура</label>
                                        <input
                                            id="ai-service-temperature"
                                            v-model.number="form.settings.generation_config.temperature"
                                            type="number"
                                            class="form-control"
                                            min="0"
                                            max="2"
                                            step="0.1"
                                        >
                                        <div class="form-text">0–2</div>
                                    </div>
                                    <div class="page-app-ai-services__param">
                                        <label class="form-label" for="ai-service-top-p">Top P</label>
                                        <input
                                            id="ai-service-top-p"
                                            v-model.number="form.settings.generation_config.top_p"
                                            type="number"
                                            class="form-control"
                                            min="0"
                                            max="1"
                                            step="0.01"
                                        >
                                        <div class="form-text">0–1</div>
                                    </div>
                                    <div class="page-app-ai-services__param">
                                        <label class="form-label" for="ai-service-top-k">Top K</label>
                                        <input
                                            id="ai-service-top-k"
                                            v-model.number="form.settings.generation_config.top_k"
                                            type="number"
                                            class="form-control"
                                            min="1"
                                            step="1"
                                        >
                                    </div>
                                    <div class="page-app-ai-services__param">
                                        <label class="form-label" for="ai-service-max-tokens">Макс. токенов</label>
                                        <input
                                            id="ai-service-max-tokens"
                                            v-model.number="form.settings.generation_config.max_output_tokens"
                                            type="number"
                                            class="form-control"
                                            min="1"
                                            step="1"
                                        >
                                    </div>
                                    <div class="page-app-ai-services__param page-app-ai-services__param--wide">
                                        <label class="form-label" for="ai-service-thinking">Thinking budget</label>
                                        <input
                                            id="ai-service-thinking"
                                            v-model.number="form.settings.generation_config.thinking_config.thinking_budget"
                                            type="number"
                                            class="form-control"
                                            min="-1"
                                            step="1"
                                        >
                                        <div class="form-text">−1 — динамический, 0 — без thinking</div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="page-app-ai-services__form-actions">
                            <button
                                v-if="!isEdit"
                                class="btn btn-secondary"
                                type="button"
                                :disabled="saving"
                                @click="goToStep1"
                            >
                                <FontAwesomeIcon :icon="['fas', 'arrow-left']" />
                                <span>Назад</span>
                            </button>
                            <button
                                class="btn btn-primary"
                                type="submit"
                                :disabled="saving || !form.settings.model"
                            >
                                <AppLoader
                                    v-if="saving"
                                    size="sm"
                                />
                                <span>{{ saving ? 'Сохранение…' : 'Сохранить' }}</span>
                            </button>
                            <RouterLink
                                class="btn btn-link page-app-ai-services__cancel"
                                :to="{ name: 'ai-services.index' }"
                            >
                                Отмена
                            </RouterLink>
                        </div>
                    </div>
                </Transition>
            </form>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLoader from '../../../shared/components/AppLoader.vue';
import { FontAwesomeIcon } from '../../../shared/icons';
import {
    createAiService,
    getAiService,
    getAiServicesMeta,
    listAiServiceModels,
    updateAiService,
} from '../../api/aiServices';

function defaultGeminiSettings() {
    return {
        model: '',
        system_instruction: '',
        generation_config: {
            temperature: 1.0,
            top_p: 0.95,
            top_k: 40,
            max_output_tokens: 8192,
            candidate_count: 1,
            stop_sequences: [],
            seed: null,
            presence_penalty: null,
            frequency_penalty: null,
            response_mime_type: 'text/plain',
            thinking_config: {
                thinking_budget: -1,
            },
        },
    };
}

const route = useRoute();
const router = useRouter();

const isEdit = computed(() => Boolean(route.params.id));
const types = ref([{ value: 'gemini', label: 'Google Gemini' }]);
const preferredModels = ref([
    'gemini-3.6-flash',
    'gemini-3.5-flash',
    'gemini-3.1-flash-lite',
    'gemini-2.0-flash',
]);
const loading = ref(true);
const loadError = ref('');
const formError = ref('');
const fieldErrors = ref({});
const saving = ref(false);
const apiKeySet = ref(false);
const step = ref(1);
const loadingModels = ref(false);
const models = ref([]);
const modelsError = ref('');

const form = reactive({
    type: 'gemini',
    api_key: '',
    settings: defaultGeminiSettings(),
});

const typeLabel = computed(() => {
    const match = types.value.find((type) => type.value === form.type);

    return match?.label ?? form.type;
});

const modelOptions = computed(() => {
    const options = [...models.value];
    const current = form.settings.model;

    if (current && !options.some((model) => model.id === current)) {
        options.unshift({ id: current, name: current });
    }

    return options;
});

function fieldError(key) {
    const messages = fieldErrors.value[key];

    return Array.isArray(messages) ? messages[0] : '';
}

function modelLabel(model) {
    if (!model.name || model.name === model.id) {
        return model.id;
    }

    return `${model.name} (${model.id})`;
}

function pickPreferredModel(availableModels) {
    for (const preferredId of preferredModels.value) {
        const match = availableModels.find((model) => model.id === preferredId);

        if (match) {
            return match.id;
        }
    }

    return availableModels[0]?.id ?? '';
}

function applyService(service) {
    form.type = service.type ?? 'gemini';
    form.api_key = '';
    apiKeySet.value = Boolean(service.api_key_set);

    const defaults = defaultGeminiSettings();
    const incoming = service.settings ?? {};
    const generation = {
        ...defaults.generation_config,
        ...(incoming.generation_config ?? {}),
        thinking_config: {
            ...defaults.generation_config.thinking_config,
            ...(incoming.generation_config?.thinking_config ?? {}),
        },
    };

    form.settings = {
        ...defaults,
        ...incoming,
        system_instruction: incoming.system_instruction ?? '',
        generation_config: generation,
    };
}

function buildPayload() {
    const settings = {
        ...form.settings,
        system_instruction: form.settings.system_instruction || null,
    };

    const payload = {
        type: form.type,
        settings,
    };

    if (form.api_key) {
        payload.api_key = form.api_key;
    } else if (!isEdit.value) {
        payload.api_key = '';
    }

    return payload;
}

function validateStep1() {
    const errors = {};

    if (!form.type) {
        errors.type = ['Выберите тип'];
    }

    if (!form.api_key && (!isEdit.value || !apiKeySet.value)) {
        errors.api_key = ['Укажите API-ключ'];
    }

    fieldErrors.value = errors;
    formError.value = Object.keys(errors).length ? 'Заполните обязательные поля' : '';

    return Object.keys(errors).length === 0;
}

async function loadModels() {
    const payload = {
        type: form.type,
    };

    if (form.api_key) {
        payload.api_key = form.api_key;
    } else if (isEdit.value) {
        payload.ai_service_id = Number(route.params.id);
    }

    return listAiServiceModels(payload);
}

async function goToStep2() {
    if (!validateStep1()) {
        return;
    }

    loadingModels.value = true;
    formError.value = '';
    fieldErrors.value = {};
    modelsError.value = '';

    try {
        models.value = await loadModels();

        if (!form.settings.model && models.value.length) {
            form.settings.model = pickPreferredModel(models.value);
        }

        step.value = 2;
    } catch (e) {
        fieldErrors.value = e.response?.data?.errors ?? {};
        formError.value = e.response?.data?.message || 'Не удалось загрузить список моделей';
    } finally {
        loadingModels.value = false;
    }
}

async function openEditParameters() {
    step.value = 2;
    modelsError.value = '';

    try {
        models.value = await loadModels();
    } catch (e) {
        models.value = [];
        modelsError.value = e.response?.data?.message
            || e.response?.data?.errors?.api_key?.[0]
            || 'Не удалось загрузить список моделей. Можно оставить текущую.';
    }
}

function goToStep1() {
    if (isEdit.value) {
        return;
    }

    formError.value = '';
    fieldErrors.value = {};
    modelsError.value = '';
    step.value = 1;
}

async function onSubmit() {
    if (step.value !== 2) {
        await goToStep2();

        return;
    }

    saving.value = true;
    formError.value = '';
    fieldErrors.value = {};

    try {
        const payload = buildPayload();

        if (isEdit.value) {
            await updateAiService(route.params.id, payload);
        } else {
            await createAiService(payload);
        }

        await router.push({ name: 'ai-services.index' });
    } catch (e) {
        fieldErrors.value = e.response?.data?.errors ?? {};
        formError.value = e.response?.data?.message || 'Не удалось сохранить AI-сервис';

        if (fieldErrors.value.type || fieldErrors.value.api_key) {
            if (!isEdit.value) {
                step.value = 1;
            }
        }
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    loading.value = true;
    loadError.value = '';

    try {
        try {
            const meta = await getAiServicesMeta();
            if (meta.types?.length) {
                types.value = meta.types;
            }
            if (meta.gemini?.preferred_models?.length) {
                preferredModels.value = meta.gemini.preferred_models;
            }
        } catch (e) {
            // meta optional
        }

        if (isEdit.value) {
            const service = await getAiService(route.params.id);
            applyService(service);
            await openEditParameters();
        }
    } catch (e) {
        loadError.value = e.response?.data?.message || 'Не удалось загрузить AI-сервис';
    } finally {
        loading.value = false;
    }
});
</script>
