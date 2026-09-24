<template>
    <div class="page-app-sites page-app-sites--show">
        <div class="page-app-sites__header">
            <div>
                <p class="page-app-sites__eyebrow">Сайты</p>
                <h1 class="page-app-sites__title">{{ site?.name || 'Сайт' }}</h1>
                <p v-if="site" class="page-app-sites__lede">
                    <a :href="site.url" target="_blank" rel="noopener noreferrer">{{ site.url }}</a>
                </p>
            </div>
            <div class="page-app-sites__header-actions">
                <RouterLink
                    v-if="site"
                    class="btn btn-secondary"
                    :to="{ name: 'sites.edit', params: { id: site.id } }"
                >
                    Изменить
                </RouterLink>
                <RouterLink class="btn btn-secondary" :to="{ name: 'sites.index' }">
                    К списку
                </RouterLink>
            </div>
        </div>

        <AppLoader
            v-if="loading"
            block
            label="Загрузка…"
        />
        <div v-else-if="error" class="alert alert-danger py-2">{{ error }}</div>

        <template v-else-if="site">
            <section class="page-app-sites__integrations" aria-label="Подключения сервисов">
                <SiteIntegrationCard
                    title="Google Analytics"
                    :connected="gaConnected"
                    :detail="gaDetail"
                    empty-detail="Property GA4 не выбран"
                    :disabled="busy"
                    @configure="openGaModal"
                />
                <SiteIntegrationCard
                    title="Google Search Console"
                    :connected="gscConnected"
                    :detail="gscDetail"
                    empty-detail="Сайт Search Console не выбран"
                    :disabled="busy"
                    @configure="openGscModal"
                />
                <SiteIntegrationCard
                    title="GitHub"
                    :connected="githubConnected"
                    :detail="githubDetail"
                    empty-detail="Репозиторий не выбран"
                    :disabled="busy"
                    @configure="openGithubModal"
                />
            </section>

            <div
                class="page-app-sites__view-switch"
                role="tablist"
                aria-label="Разделы сайта"
            >
                <button
                    v-for="tab in siteViewTabs"
                    :id="`site-view-tab-${tab.id}`"
                    :key="tab.id"
                    type="button"
                    class="page-app-sites__view-switch-btn"
                    :class="{ 'is-active': activeSiteView === tab.id }"
                    role="tab"
                    :aria-selected="activeSiteView === tab.id"
                    :aria-controls="`site-view-pane-${tab.id}`"
                    @click="activeSiteView = tab.id"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div
                v-show="activeSiteView === 'data'"
                id="site-view-pane-data"
                role="tabpanel"
                aria-labelledby="site-view-tab-data"
            >
                <section
                    v-if="gaConnected || gscConnected || githubConnected"
                    class="page-app-sites__panel page-app-sites__period mb-4"
                >
                    <div class="page-app-sites__panel-head">
                        <h2 class="h5 mb-0">Период данных</h2>
                    </div>
                    <p class="text-muted small mb-3">
                        Выберите даты для просмотра и выгрузки данных подключённых сервисов.
                    </p>
                    <div class="page-app-sites__period-row">
                        <div class="page-app-sites__period-field">
                            <label class="form-label" for="metrics-from">С</label>
                            <input
                                id="metrics-from"
                                v-model="period.from"
                                type="date"
                                class="form-control"
                                :max="period.to || periodMax"
                                :disabled="busy || metricsLoading"
                                @change="onPeriodChange"
                            >
                        </div>
                        <div class="page-app-sites__period-field">
                            <label class="form-label" for="metrics-to">По</label>
                            <input
                                id="metrics-to"
                                v-model="period.to"
                                type="date"
                                class="form-control"
                                :min="period.from"
                                :max="periodMax"
                                :disabled="busy || metricsLoading"
                                @change="onPeriodChange"
                            >
                        </div>
                        <div class="page-app-sites__period-actions">
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="busy || !canSyncPeriod"
                                @click="onSyncPeriod"
                            >
                                Загрузить данные
                            </button>
                        </div>
                    </div>
                    <p v-if="integration?.last_synced_at" class="text-muted small mt-3 mb-0">
                        Google: {{ formatDateTime(integration.last_synced_at) }}
                    </p>
                    <p v-if="githubIntegration?.last_synced_at" class="text-muted small mt-1 mb-0">
                        GitHub: {{ formatDateTime(githubIntegration.last_synced_at) }}
                    </p>
                    <p v-if="integration?.last_error" class="text-danger small mt-2 mb-0">
                        Google: {{ integration.last_error }}
                    </p>
                    <p v-if="githubIntegration?.last_error" class="text-danger small mt-2 mb-0">
                        GitHub: {{ githubIntegration.last_error }}
                    </p>
                </section>

                <section
                    class="page-app-sites__panel page-app-sites__metrics"
                    aria-label="Данные сервисов"
                >
                    <ul class="nav nav-tabs page-app-sites__metrics-tabs" role="tablist">
                        <li
                            v-for="tab in metricsTabs"
                            :key="tab.id"
                            class="nav-item"
                            role="presentation"
                        >
                            <button
                                type="button"
                                class="nav-link"
                                :class="{ active: activeMetricsTab === tab.id }"
                                role="tab"
                                :aria-selected="activeMetricsTab === tab.id"
                                :id="`metrics-tab-${tab.id}`"
                                :aria-controls="`metrics-pane-${tab.id}`"
                                @click="activeMetricsTab = tab.id"
                            >
                                {{ tab.label }}
                            </button>
                        </li>
                    </ul>

                    <div class="page-app-sites__metrics-body">
                        <div
                            v-show="activeMetricsTab === 'ga4'"
                            id="metrics-pane-ga4"
                            class="page-app-sites__metrics-pane"
                            role="tabpanel"
                            aria-labelledby="metrics-tab-ga4"
                        >
                            <p class="text-muted small mb-3">
                                «Орг.» — канал Organic Search (переходы из поисковых систем).
                            </p>
                            <AppLoader v-if="metricsLoading" block label="Загрузка метрик…" />
                            <div v-else-if="!gaConnected" class="text-muted">GA4 property не привязан</div>
                            <div v-else-if="!analyticsRows.length" class="text-muted">Нет данных за период</div>
                            <div v-else class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Дата</th>
                                            <th>Сессии</th>
                                            <th>Пользователи</th>
                                            <th>Новые</th>
                                            <th>Просмотры</th>
                                            <th>Орг. сессии</th>
                                            <th>Орг. польз.</th>
                                            <th>Орг. новые</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in analyticsRows" :key="row.date">
                                            <td>{{ row.date }}</td>
                                            <td>{{ row.sessions }}</td>
                                            <td>{{ row.total_users }}</td>
                                            <td>{{ row.new_users }}</td>
                                            <td>{{ row.screen_page_views }}</td>
                                            <td>{{ row.organic_sessions ?? 0 }}</td>
                                            <td>{{ row.organic_total_users ?? 0 }}</td>
                                            <td>{{ row.organic_new_users ?? 0 }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div
                            v-show="activeMetricsTab === 'gsc'"
                            id="metrics-pane-gsc"
                            class="page-app-sites__metrics-pane"
                            role="tabpanel"
                            aria-labelledby="metrics-tab-gsc"
                        >
                            <AppLoader v-if="metricsLoading" block label="Загрузка метрик…" />
                            <div v-else-if="!gscConnected" class="text-muted">Сайт Search Console не привязан</div>
                            <div v-else-if="!gscRows.length" class="text-muted">Нет данных за период</div>
                            <div v-else class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Дата</th>
                                            <th>Клики</th>
                                            <th>Показы</th>
                                            <th>CTR</th>
                                            <th>Позиция</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in gscRows" :key="row.date">
                                            <td>{{ row.date }}</td>
                                            <td>{{ row.clicks }}</td>
                                            <td>{{ row.impressions }}</td>
                                            <td>{{ formatPct(row.ctr) }}</td>
                                            <td>{{ formatNumber(row.position) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div
                            v-show="activeMetricsTab === 'github'"
                            id="metrics-pane-github"
                            class="page-app-sites__metrics-pane"
                            role="tabpanel"
                            aria-labelledby="metrics-tab-github"
                        >
                            <p v-if="githubIntegration?.repository_full_name" class="text-muted small mb-3">
                                Репозиторий: {{ githubIntegration.repository_full_name }}
                            </p>
                            <AppLoader v-if="metricsLoading" block label="Загрузка коммитов…" />
                            <div v-else-if="!githubConnected" class="text-muted">Репозиторий не привязан</div>
                            <div v-else-if="!commitRows.length" class="text-muted">Нет коммитов за период</div>
                            <div v-else class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>SHA</th>
                                            <th>Сообщение</th>
                                            <th>Автор</th>
                                            <th>Дата</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in commitRows" :key="row.sha">
                                            <td>
                                                <a
                                                    v-if="row.html_url"
                                                    :href="row.html_url"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >{{ row.short_sha }}</a>
                                                <span v-else>{{ row.short_sha }}</span>
                                            </td>
                                            <td class="page-app-sites__commit-message">{{ commitSubject(row.message) }}</td>
                                            <td>{{ row.author_name || '—' }}</td>
                                            <td>{{ formatDateTime(row.author_date) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div
                v-show="activeSiteView === 'ai-report'"
                id="site-view-pane-ai-report"
                role="tabpanel"
                aria-labelledby="site-view-tab-ai-report"
            >
                <section
                    class="page-app-sites__panel page-app-sites__ai-report"
                    aria-label="AI отчёт"
                >
                    <div class="page-app-sites__panel-head">
                        <h2 class="h5 mb-0">AI отчёт</h2>
                    </div>
                    <p class="text-muted mb-0">
                        Здесь появится результат AI-отчёта.
                    </p>
                </section>
            </div>
        </template>

        <AppModal
            v-model:open="gaModalOpen"
            title="Google Analytics"
            size="md"
            align="start"
            :show-confirm="false"
            :close-on-backdrop="!busy"
        >
            <div class="page-app-sites__modal-account">
                <template v-if="connection && !connection.needs_reauth">
                    <p class="text-muted small mb-2">
                        Аккаунт Google: <strong>{{ connection.google_account_email }}</strong>
                    </p>
                    <div class="page-app-sites__modal-actions">
                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm"
                            :disabled="busy"
                            @click="onConnectGoogle"
                        >
                            Переподключить
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline-danger btn-sm"
                            :disabled="busy"
                            @click="onDisconnectGoogle"
                        >
                            Отключить Google
                        </button>
                    </div>
                </template>
                <template v-else>
                    <p class="text-muted mb-3">
                        {{ connection?.needs_reauth
                            ? 'Нужна повторная авторизация Google.'
                            : 'Подключите Google, чтобы выбрать GA4 property.' }}
                    </p>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="busy"
                        @click="onConnectGoogle"
                    >
                        Подключить Google
                    </button>
                </template>
            </div>

            <template v-if="connection && !connection.needs_reauth">
                <div v-if="listsError" class="alert alert-danger py-2 mb-3">{{ listsError }}</div>
                <AppLoader v-if="listsLoading" block label="Загрузка property…" />
                <template v-else>
                    <label class="form-label" for="ga4-property">GA4 property</label>
                    <select
                        id="ga4-property"
                        v-model="form.ga4_property_id"
                        class="form-select mb-3"
                        :disabled="busy"
                    >
                        <option value="">Не выбрано</option>
                        <option
                            v-for="item in ga4Properties"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.display_name }} ({{ item.account }})
                        </option>
                    </select>

                    <div class="page-app-sites__modal-actions">
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="busy || !form.ga4_property_id"
                            @click="onSaveGa"
                        >
                            Сохранить
                        </button>
                    </div>
                </template>
            </template>
        </AppModal>

        <AppModal
            v-model:open="gscModalOpen"
            title="Google Search Console"
            size="md"
            align="start"
            :show-confirm="false"
            :close-on-backdrop="!busy"
        >
            <div class="page-app-sites__modal-account">
                <template v-if="connection && !connection.needs_reauth">
                    <p class="text-muted small mb-2">
                        Аккаунт Google: <strong>{{ connection.google_account_email }}</strong>
                    </p>
                    <div class="page-app-sites__modal-actions">
                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm"
                            :disabled="busy"
                            @click="onConnectGoogle"
                        >
                            Переподключить
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline-danger btn-sm"
                            :disabled="busy"
                            @click="onDisconnectGoogle"
                        >
                            Отключить Google
                        </button>
                    </div>
                </template>
                <template v-else>
                    <p class="text-muted mb-3">
                        {{ connection?.needs_reauth
                            ? 'Нужна повторная авторизация Google.'
                            : 'Подключите Google, чтобы выбрать сайт Search Console.' }}
                    </p>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="busy"
                        @click="onConnectGoogle"
                    >
                        Подключить Google
                    </button>
                </template>
            </div>

            <template v-if="connection && !connection.needs_reauth">
                <div v-if="listsError" class="alert alert-danger py-2 mb-3">{{ listsError }}</div>
                <AppLoader v-if="listsLoading" block label="Загрузка сайтов…" />
                <template v-else>
                    <label class="form-label" for="gsc-site">Сайт Search Console</label>
                    <select
                        id="gsc-site"
                        v-model="form.gsc_site_url"
                        class="form-select mb-3"
                        :disabled="busy"
                    >
                        <option value="">Не выбрано</option>
                        <option
                            v-for="item in gscSites"
                            :key="item.site_url"
                            :value="item.site_url"
                        >
                            {{ item.site_url }}
                        </option>
                    </select>

                    <div class="page-app-sites__modal-actions">
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="busy || !form.gsc_site_url"
                            @click="onSaveGsc"
                        >
                            Сохранить
                        </button>
                    </div>
                </template>
            </template>
        </AppModal>

        <AppModal
            v-model:open="githubModalOpen"
            title="GitHub"
            size="md"
            align="start"
            :show-confirm="false"
            :close-on-backdrop="!busy"
        >
            <div class="page-app-sites__modal-account">
                <template v-if="githubConnection && !githubConnection.needs_reauth">
                    <p class="text-muted small mb-2">
                        Аккаунт:
                        <strong>{{ githubConnection.github_login }}</strong>
                        <span v-if="githubConnection.github_account_email">
                            ({{ githubConnection.github_account_email }})
                        </span>
                    </p>
                    <div class="page-app-sites__modal-actions">
                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm"
                            :disabled="busy"
                            @click="onConnectGithub"
                        >
                            Переподключить
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline-danger btn-sm"
                            :disabled="busy"
                            @click="onDisconnectGithub"
                        >
                            Отключить GitHub
                        </button>
                    </div>
                </template>
                <template v-else>
                    <p class="text-muted mb-3">
                        {{ githubConnection?.needs_reauth
                            ? 'Нужна повторная авторизация GitHub.'
                            : 'Подключите GitHub, чтобы выбрать репозиторий.' }}
                    </p>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="busy"
                        @click="onConnectGithub"
                    >
                        Подключить GitHub
                    </button>
                </template>
            </div>

            <template v-if="githubConnection && !githubConnection.needs_reauth">
                <div v-if="githubReposError" class="alert alert-danger py-2 mb-3">{{ githubReposError }}</div>
                <AppLoader v-if="githubReposLoading" block label="Загрузка репозиториев…" />
                <template v-else>
                    <label class="form-label" for="github-repo">Репозиторий</label>
                    <select
                        id="github-repo"
                        v-model="githubForm.repository_full_name"
                        class="form-select mb-2"
                        :disabled="busy"
                        @change="onGithubRepoChange"
                    >
                        <option value="">Не выбрано</option>
                        <option
                            v-for="item in githubRepositories"
                            :key="item.id"
                            :value="item.full_name"
                        >
                            {{ item.full_name }}{{ item.private ? ' (private)' : '' }}
                        </option>
                    </select>
                    <p
                        v-if="githubIntegration?.repository_full_name && !repoInList"
                        class="form-text mb-3"
                    >
                        Сейчас привязан: {{ githubIntegration.repository_full_name }}
                    </p>

                    <template v-if="githubForm.repository_full_name">
                        <div
                            v-if="githubBranchesError"
                            class="alert alert-danger py-2 mb-3"
                        >
                            {{ githubBranchesError }}
                        </div>
                        <AppLoader
                            v-if="githubBranchesLoading"
                            block
                            label="Загрузка веток…"
                        />
                        <template v-else>
                            <label class="form-label" for="github-branch">Ветка</label>
                            <select
                                id="github-branch"
                                v-model="githubForm.default_branch"
                                class="form-select mb-2"
                                :disabled="busy || !githubBranches.length"
                            >
                                <option value="">Не выбрано</option>
                                <option
                                    v-for="branch in githubBranches"
                                    :key="branch.name"
                                    :value="branch.name"
                                >
                                    {{ branch.name }}{{ branch.protected ? ' (protected)' : '' }}
                                </option>
                            </select>
                            <p
                                v-if="githubForm.default_branch && !branchInList"
                                class="form-text mb-3"
                            >
                                Сейчас выбрана: {{ githubForm.default_branch }}
                            </p>
                        </template>
                    </template>

                    <div class="page-app-sites__modal-actions">
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="busy || !githubForm.repository_full_name || !githubForm.default_branch"
                            @click="onSaveGithubIntegration"
                        >
                            Сохранить
                        </button>
                        <button
                            v-if="githubConnected"
                            type="button"
                            class="btn btn-outline-danger"
                            :disabled="busy"
                            @click="onClearGithubIntegration"
                        >
                            Отвязать репозиторий
                        </button>
                    </div>
                </template>
            </template>
        </AppModal>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import SiteIntegrationCard from '../../components/SiteIntegrationCard.vue';
import AppLoader from '../../../shared/components/AppLoader.vue';
import AppModal from '../../../shared/components/AppModal.vue';
import { toast } from '../../../shared/toast';
import {
    disconnectGithub,
    getGithubConnection,
    getSiteGithubCommits,
    listGithubBranches,
    listGithubRepositories,
    startGithubOAuth,
    syncSiteGithubIntegration,
    updateSiteGithubIntegration,
    deleteSiteGithubIntegration,
} from '../../api/github';
import {
    disconnectGoogle,
    getGoogleConnection,
    getSite,
    getSiteAnalyticsMetrics,
    getSiteSearchConsoleMetrics,
    listGa4Properties,
    listGscSites,
    startGoogleOAuth,
    syncSiteGoogleIntegration,
    updateSiteGoogleIntegration,
} from '../../api/sites';

const route = useRoute();

const site = ref(null);
const connection = ref(null);
const githubConnection = ref(null);
const integration = ref(null);
const githubIntegration = ref(null);
const ga4Properties = ref([]);
const gscSites = ref([]);
const githubRepositories = ref([]);
const githubBranches = ref([]);
const analyticsRows = ref([]);
const gscRows = ref([]);
const commitRows = ref([]);

const loading = ref(true);
const listsLoading = ref(false);
const githubReposLoading = ref(false);
const githubBranchesLoading = ref(false);
const metricsLoading = ref(false);
const busy = ref(false);
const error = ref('');
const listsError = ref('');
const githubReposError = ref('');
const githubBranchesError = ref('');

const gaModalOpen = ref(false);
const gscModalOpen = ref(false);
const githubModalOpen = ref(false);
const activeSiteView = ref('data');
const activeMetricsTab = ref('ga4');

const siteViewTabs = [
    { id: 'data', label: 'Данные сервисов' },
    { id: 'ai-report', label: 'AI отчёт' },
];

const metricsTabs = [
    { id: 'ga4', label: 'GA4' },
    { id: 'gsc', label: 'Search Console' },
    { id: 'github', label: 'GitHub' },
];

const periodMax = yesterdayDateString();
const period = reactive({
    from: defaultPeriodFrom(),
    to: periodMax,
});

const form = reactive({
    ga4_property_id: '',
    gsc_site_url: '',
});

const githubForm = reactive({
    repository_full_name: '',
    repository_id: null,
    default_branch: '',
});

const gaConnected = computed(() => Boolean(integration.value?.ga4_property_id));
const gscConnected = computed(() => Boolean(integration.value?.gsc_site_url));
const githubConnected = computed(() => Boolean(githubIntegration.value?.is_configured));
const canSyncPeriod = computed(() => (
    (gaConnected.value || gscConnected.value || githubConnected.value)
    && Boolean(period.from)
    && Boolean(period.to)
    && period.from <= period.to
));

const gaDetail = computed(() => {
    const id = integration.value?.ga4_property_id;

    if (!id) {
        return '';
    }

    const match = ga4Properties.value.find((item) => item.id === id);

    return match ? `${match.display_name} (${match.account})` : id;
});

const gscDetail = computed(() => integration.value?.gsc_site_url || '');
const githubDetail = computed(() => {
    const fullName = githubIntegration.value?.repository_full_name;

    if (!fullName) {
        return '';
    }

    const branch = githubIntegration.value?.default_branch;

    return branch ? `${fullName} · ${branch}` : fullName;
});

const repoInList = computed(() => {
    const current = githubIntegration.value?.repository_full_name;

    if (!current) {
        return true;
    }

    return githubRepositories.value.some((item) => item.full_name === current);
});

const branchInList = computed(() => {
    const current = githubForm.default_branch;

    if (!current) {
        return true;
    }

    return githubBranches.value.some((item) => item.name === current);
});

function yesterdayDateString() {
    const date = new Date();
    date.setDate(date.getDate() - 1);

    return date.toISOString().slice(0, 10);
}

function defaultPeriodFrom() {
    const date = new Date();
    date.setDate(date.getDate() - 28);

    return date.toISOString().slice(0, 10);
}

function formatDateTime(value) {
    try {
        return new Date(value).toLocaleString('ru-RU');
    } catch {
        return value;
    }
}

function formatPct(value) {
    return `${(Number(value) * 100).toFixed(2)}%`;
}

function formatNumber(value) {
    return Number(value).toFixed(1);
}

function commitSubject(message) {
    if (!message) {
        return '—';
    }

    return String(message).split('\n')[0];
}

function preferConnectedMetricsTab() {
    const connected = [
        gaConnected.value ? 'ga4' : null,
        gscConnected.value ? 'gsc' : null,
        githubConnected.value ? 'github' : null,
    ].filter(Boolean);

    if (!connected.length) {
        return;
    }

    if (!connected.includes(activeMetricsTab.value)) {
        activeMetricsTab.value = connected[0];
    }
}

async function loadMetrics() {
    if (!site.value || !period.from || !period.to) {
        return;
    }

    metricsLoading.value = true;

    try {
        const params = { from: period.from, to: period.to };
        const [analytics, gsc, commits] = await Promise.all([
            getSiteAnalyticsMetrics(site.value.id, params),
            getSiteSearchConsoleMetrics(site.value.id, params),
            getSiteGithubCommits(site.value.id, params),
        ]);
        analyticsRows.value = analytics;
        gscRows.value = gsc;
        commitRows.value = commits;
    } catch {
        analyticsRows.value = [];
        gscRows.value = [];
        commitRows.value = [];
    } finally {
        metricsLoading.value = false;
    }
}

async function onPeriodChange() {
    if (!period.from || !period.to || period.from > period.to) {
        return;
    }

    await loadMetrics();
}

async function loadPropertyLists() {
    if (!connection.value || connection.value.needs_reauth) {
        return;
    }

    listsLoading.value = true;
    listsError.value = '';

    try {
        const [properties, sites] = await Promise.all([
            listGa4Properties(),
            listGscSites(),
        ]);
        ga4Properties.value = properties;
        gscSites.value = sites;
    } catch (e) {
        listsError.value = e.response?.data?.message || 'Не удалось загрузить списки Google';
    } finally {
        listsLoading.value = false;
    }
}

async function loadGithubRepositories() {
    if (!githubConnection.value || githubConnection.value.needs_reauth) {
        return;
    }

    githubReposLoading.value = true;
    githubReposError.value = '';

    try {
        githubRepositories.value = await listGithubRepositories({ per_page: 100 });
    } catch (e) {
        githubReposError.value = e.response?.data?.message || 'Не удалось загрузить репозитории GitHub';
    } finally {
        githubReposLoading.value = false;
    }
}

async function loadGithubBranches() {
    const fullName = githubForm.repository_full_name;

    if (!fullName || !fullName.includes('/')) {
        githubBranches.value = [];
        githubBranchesError.value = '';

        return;
    }

    const [owner, repo] = fullName.split('/', 2);

    if (!owner || !repo) {
        githubBranches.value = [];

        return;
    }

    githubBranchesLoading.value = true;
    githubBranchesError.value = '';

    try {
        githubBranches.value = await listGithubBranches(owner, repo, { per_page: 100 });

        if (!githubForm.default_branch) {
            const selected = githubRepositories.value.find((item) => item.full_name === fullName);
            const preferred = selected?.default_branch
                || githubBranches.value.find((item) => item.name === 'main' || item.name === 'master')?.name
                || githubBranches.value[0]?.name
                || '';

            githubForm.default_branch = preferred;
        }
    } catch (e) {
        githubBranches.value = [];
        githubBranchesError.value = e.response?.data?.message || 'Не удалось загрузить ветки репозитория';
    } finally {
        githubBranchesLoading.value = false;
    }
}

async function onGithubRepoChange() {
    const selected = githubRepositories.value.find(
        (item) => item.full_name === githubForm.repository_full_name,
    );

    githubForm.repository_id = selected?.id ?? null;
    githubForm.default_branch = selected?.default_branch || '';
    githubBranches.value = [];
    githubBranchesError.value = '';

    if (!githubForm.repository_full_name) {
        return;
    }

    await loadGithubBranches();
}

async function openGaModal() {
    form.ga4_property_id = integration.value?.ga4_property_id || '';
    gaModalOpen.value = true;
    await loadPropertyLists();
}

async function openGscModal() {
    form.gsc_site_url = integration.value?.gsc_site_url || '';
    gscModalOpen.value = true;
    await loadPropertyLists();
}

async function openGithubModal() {
    githubForm.repository_full_name = githubIntegration.value?.repository_full_name || '';
    githubForm.repository_id = githubIntegration.value?.repository_id || null;
    githubForm.default_branch = githubIntegration.value?.default_branch || '';
    githubBranches.value = [];
    githubBranchesError.value = '';
    githubModalOpen.value = true;
    await loadGithubRepositories();

    if (githubForm.repository_full_name) {
        await loadGithubBranches();
    }
}

async function reload() {
    loading.value = true;
    error.value = '';

    try {
        const [siteData, connectionData, githubConnectionData] = await Promise.all([
            getSite(route.params.id),
            getGoogleConnection(),
            getGithubConnection(),
        ]);

        site.value = siteData;
        connection.value = connectionData;
        githubConnection.value = githubConnectionData;
        integration.value = siteData.google_integration || null;
        githubIntegration.value = siteData.github_integration || null;
        form.ga4_property_id = integration.value?.ga4_property_id || '';
        form.gsc_site_url = integration.value?.gsc_site_url || '';
        githubForm.repository_full_name = githubIntegration.value?.repository_full_name || '';
        githubForm.repository_id = githubIntegration.value?.repository_id || null;
        githubForm.default_branch = githubIntegration.value?.default_branch || '';

        preferConnectedMetricsTab();
        await loadMetrics();
    } catch (e) {
        error.value = e.response?.data?.message || 'Не удалось загрузить сайт';
    } finally {
        loading.value = false;
    }
}

async function onConnectGoogle() {
    busy.value = true;

    try {
        const result = await startGoogleOAuth({ return_site_id: Number(route.params.id) });
        window.location.href = result.authorize_url;
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || 'Не удалось начать авторизацию Google',
        });
        busy.value = false;
    }
}

async function onDisconnectGoogle() {
    if (!window.confirm('Отключить аккаунт Google и удалить привязки у всех сайтов?')) {
        return;
    }

    busy.value = true;

    try {
        await disconnectGoogle();
        connection.value = null;
        integration.value = null;
        form.ga4_property_id = '';
        form.gsc_site_url = '';
        ga4Properties.value = [];
        gscSites.value = [];
        gaModalOpen.value = false;
        gscModalOpen.value = false;
        toast.show({ ok: true, message: 'Аккаунт Google отключён.' });
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || 'Не удалось отключить Google',
        });
    } finally {
        busy.value = false;
    }
}

async function onConnectGithub() {
    busy.value = true;

    try {
        const result = await startGithubOAuth({ return_site_id: Number(route.params.id) });
        window.location.href = result.authorize_url;
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || 'Не удалось начать авторизацию GitHub',
        });
        busy.value = false;
    }
}

async function onDisconnectGithub() {
    if (!window.confirm('Отключить аккаунт GitHub и удалить привязки репозиториев у всех сайтов?')) {
        return;
    }

    busy.value = true;

    try {
        await disconnectGithub();
        githubConnection.value = null;
        githubIntegration.value = null;
        githubForm.repository_full_name = '';
        githubForm.repository_id = null;
        githubForm.default_branch = '';
        githubRepositories.value = [];
        githubBranches.value = [];
        githubModalOpen.value = false;
        toast.show({ ok: true, message: 'Аккаунт GitHub отключён.' });
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || 'Не удалось отключить GitHub',
        });
    } finally {
        busy.value = false;
    }
}

async function onSaveGa() {
    busy.value = true;

    try {
        integration.value = await updateSiteGoogleIntegration(site.value.id, {
            ga4_property_id: form.ga4_property_id || null,
            gsc_site_url: integration.value?.gsc_site_url || form.gsc_site_url || null,
        });
        toast.show({
            ok: true,
            message: 'Google Analytics привязан.',
        });
        gaModalOpen.value = false;
        await loadMetrics();
        preferConnectedMetricsTab();
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || 'Не удалось сохранить привязку',
        });
    } finally {
        busy.value = false;
    }
}

async function onSaveGsc() {
    busy.value = true;

    try {
        integration.value = await updateSiteGoogleIntegration(site.value.id, {
            ga4_property_id: integration.value?.ga4_property_id || form.ga4_property_id || null,
            gsc_site_url: form.gsc_site_url || null,
        });
        toast.show({
            ok: true,
            message: 'Search Console привязан.',
        });
        gscModalOpen.value = false;
        await loadMetrics();
        preferConnectedMetricsTab();
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || 'Не удалось сохранить привязку',
        });
    } finally {
        busy.value = false;
    }
}

async function onSaveGithubIntegration() {
    busy.value = true;

    try {
        const selected = githubRepositories.value.find(
            (item) => item.full_name === githubForm.repository_full_name,
        );

        if (selected) {
            githubForm.repository_id = selected.id;
        }

        githubIntegration.value = await updateSiteGithubIntegration(site.value.id, {
            repository_full_name: githubForm.repository_full_name,
            repository_id: githubForm.repository_id,
            default_branch: githubForm.default_branch,
        });
        toast.show({
            ok: true,
            message: 'Репозиторий GitHub привязан к сайту.',
        });
        githubModalOpen.value = false;
        preferConnectedMetricsTab();
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || 'Не удалось сохранить репозиторий',
        });
    } finally {
        busy.value = false;
    }
}

async function onClearGithubIntegration() {
    if (!window.confirm('Отвязать репозиторий от этого сайта?')) {
        return;
    }

    busy.value = true;

    try {
        await deleteSiteGithubIntegration(site.value.id);
        githubIntegration.value = null;
        githubForm.repository_full_name = '';
        githubForm.repository_id = null;
        githubForm.default_branch = '';
        githubBranches.value = [];
        githubModalOpen.value = false;
        preferConnectedMetricsTab();
        toast.show({ ok: true, message: 'Репозиторий отвязан от сайта.' });
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || 'Не удалось отвязать репозиторий',
        });
    } finally {
        busy.value = false;
    }
}

async function onSyncPeriod() {
    if (!canSyncPeriod.value) {
        return;
    }

    busy.value = true;

    const payload = {
        from: period.from,
        to: period.to,
    };
    const errors = [];
    let syncedAny = false;

    try {
        if (gaConnected.value || gscConnected.value) {
            try {
                const result = await syncSiteGoogleIntegration(site.value.id, payload);
                if (result.data) {
                    integration.value = result.data;
                }
                syncedAny = true;
            } catch (e) {
                if (e.response?.data?.data) {
                    integration.value = e.response.data.data;
                }
                errors.push(e.response?.data?.message || 'Не удалось загрузить данные Google');
            }
        }

        if (githubConnected.value) {
            try {
                const result = await syncSiteGithubIntegration(site.value.id, payload);
                if (result.data) {
                    githubIntegration.value = result.data;
                }
                syncedAny = true;
            } catch (e) {
                if (e.response?.data?.data) {
                    githubIntegration.value = e.response.data.data;
                }
                errors.push(e.response?.data?.message || 'Не удалось загрузить коммиты GitHub');
            }
        }

        await loadMetrics();

        if (errors.length && syncedAny) {
            toast.show({
                ok: false,
                message: errors.join(' '),
            });
        } else if (errors.length) {
            toast.show({
                ok: false,
                message: errors.join(' '),
            });
        } else {
            toast.show({
                ok: true,
                message: 'Данные за выбранный период загружены.',
            });
        }
    } finally {
        busy.value = false;
    }
}

onMounted(async () => {
    if (route.query.google === 'connected') {
        toast.show({ ok: true, message: 'Аккаунт Google подключён.' });
    } else if (route.query.google === 'error') {
        toast.show({
            ok: false,
            message: route.query.message || 'Не удалось подключить Google.',
        });
    } else if (route.query.github === 'connected') {
        toast.show({ ok: true, message: 'Аккаунт GitHub подключён.' });
    } else if (route.query.github === 'error') {
        toast.show({
            ok: false,
            message: route.query.message || 'Не удалось подключить GitHub.',
        });
    }

    await reload();

    if (route.query.google === 'connected') {
        gaModalOpen.value = true;
        await loadPropertyLists();
    } else if (route.query.github === 'connected') {
        githubModalOpen.value = true;
        await loadGithubRepositories();
    }
});
</script>
