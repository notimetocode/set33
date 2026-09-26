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
                    logo="/images/integrations/google-analytics.svg"
                    :connected="gaConnected"
                    :detail="gaDetail"
                    empty-detail="Property GA4 не выбран"
                    :disabled="busy"
                    @configure="openGaModal"
                />
                <SiteIntegrationCard
                    title="Google Search Console"
                    logo="/images/integrations/google-search-console.svg"
                    :connected="gscConnected"
                    :detail="gscDetail"
                    empty-detail="Сайт Search Console не выбран"
                    :disabled="busy"
                    @configure="openGscModal"
                />
                <SiteIntegrationCard
                    title="GitHub"
                    logo="/images/integrations/github.svg"
                    :connected="githubConnected"
                    :detail="githubDetail"
                    empty-detail="Репозиторий не выбран"
                    :disabled="busy"
                    @configure="openGithubModal"
                />
                <SiteIntegrationCard
                    title="PageSpeed / CrUX"
                    logo="/images/integrations/pagespeed.svg"
                    :connected="pagespeedConnected"
                    :detail="pagespeedDetail"
                    empty-detail="Не подключено"
                    :disabled="busy"
                    @configure="openPagespeedModal"
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
                    v-if="gaConnected || gscConnected || githubConnected || pagespeedConnected"
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

                    <div
                        v-if="availableSyncMetricGroups.length"
                        class="page-app-sites__sync-metrics mt-3"
                    >
                        <p class="text-muted small mb-2">
                            Отметьте, какие данные загружать. При загрузке предыдущие данные выбранных типов будут удалены.
                        </p>
                        <div
                            v-for="group in availableSyncMetricGroups"
                            :key="group.id"
                            class="page-app-sites__sync-metrics-group"
                        >
                            <div class="page-app-sites__sync-metrics-group-head">
                                <span class="page-app-sites__sync-metrics-group-title">{{ group.label }}</span>
                                <button
                                    type="button"
                                    class="btn btn-link btn-sm p-0"
                                    :disabled="busy"
                                    @click="toggleSyncMetricGroup(group)"
                                >
                                    {{ isSyncMetricGroupFullySelected(group) ? 'Снять все' : 'Выбрать все' }}
                                </button>
                            </div>
                            <div class="page-app-sites__sync-metrics-list">
                                <div
                                    v-for="metric in group.metrics"
                                    :key="metric.key"
                                    class="page-app-sites__sync-metric"
                                >
                                    <div class="form-check">
                                        <input
                                            :id="`sync-metric-${metric.key}`"
                                            v-model="syncMetricSelection[metric.key]"
                                            class="form-check-input"
                                            type="checkbox"
                                            :disabled="busy"
                                        >
                                        <label
                                            class="form-check-label"
                                            :for="`sync-metric-${metric.key}`"
                                        >
                                            {{ metric.label }}
                                            <span
                                                v-if="metric.optional"
                                                class="page-app-sites__sync-metric-optional"
                                            >доп.</span>
                                        </label>
                                    </div>
                                    <div
                                        v-if="metric.limitKey && syncMetricSelection[metric.key]"
                                        class="page-app-sites__sync-metric-limit"
                                    >
                                        <label
                                            class="form-label mb-0"
                                            :for="`sync-limit-${metric.limitKey}`"
                                        >Кол-во</label>
                                        <input
                                            :id="`sync-limit-${metric.limitKey}`"
                                            v-model.number="syncMetricLimits[metric.limitKey]"
                                            type="number"
                                            class="form-control form-control-sm"
                                            min="1"
                                            max="1000"
                                            :disabled="busy"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-if="integration?.last_synced_at" class="text-muted small mt-3 mb-0">
                        Последняя загрузка Google: {{ formatDateTime(integration.last_synced_at) }}
                    </p>
                    <p v-if="githubIntegration?.last_synced_at" class="text-muted small mt-1 mb-0">
                        Последняя загрузка GitHub: {{ formatDateTime(githubIntegration.last_synced_at) }}
                    </p>
                    <p v-if="pagespeedIntegration?.last_synced_at" class="text-muted small mt-1 mb-0">
                        Последняя загрузка PageSpeed / CrUX: {{ formatDateTime(pagespeedIntegration.last_synced_at) }}
                    </p>
                    <p v-if="integration?.last_error" class="text-danger small mt-2 mb-0">
                        Ошибка Google: {{ integration.last_error }}
                    </p>
                    <p v-if="githubIntegration?.last_error" class="text-danger small mt-2 mb-0">
                        Ошибка GitHub: {{ githubIntegration.last_error }}
                    </p>
                    <p v-if="pagespeedIntegration?.last_error" class="text-danger small mt-2 mb-0">
                        Ошибка PageSpeed / CrUX: {{ pagespeedIntegration.last_error }}
                    </p>
                </section>

                <section
                    class="page-app-sites__panel page-app-sites__metrics"
                    aria-label="Данные сервисов"
                >
                    <div
                        class="page-app-sites__view-switch page-app-sites__metrics-tabs"
                        role="tablist"
                        aria-label="Данные сервисов"
                    >
                        <button
                            v-for="tab in metricsTabs"
                            :id="`metrics-tab-${tab.id}`"
                            :key="tab.id"
                            type="button"
                            class="page-app-sites__view-switch-btn"
                            :class="{ 'is-active': activeMetricsTab === tab.id }"
                            role="tab"
                            :aria-selected="activeMetricsTab === tab.id"
                            :aria-controls="`metrics-pane-${tab.id}`"
                            @click="activeMetricsTab = tab.id"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

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
                                            <th v-if="hasAnalyticsEngagement">Вовлеч. сессии</th>
                                            <th v-if="hasAnalyticsEngagement">Вовлечённость</th>
                                            <th v-if="hasAnalyticsEngagement">Отказы</th>
                                            <th v-if="hasAnalyticsEngagement">Ср. длит.</th>
                                            <th v-if="hasAnalyticsEngagement">События</th>
                                            <th v-if="hasAnalyticsEngagement">Орг. вовлеч.</th>
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
                                            <td v-if="hasAnalyticsEngagement">{{ row.engaged_sessions ?? 0 }}</td>
                                            <td v-if="hasAnalyticsEngagement">{{ formatPct(row.engagement_rate) }}</td>
                                            <td v-if="hasAnalyticsEngagement">{{ formatPct(row.bounce_rate) }}</td>
                                            <td v-if="hasAnalyticsEngagement">{{ formatDuration(row.average_session_duration) }}</td>
                                            <td v-if="hasAnalyticsEngagement">{{ row.event_count ?? 0 }}</td>
                                            <td v-if="hasAnalyticsEngagement">{{ row.organic_engaged_sessions ?? 0 }}</td>
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
                            <div v-else-if="!hasGscMetrics" class="text-muted">Нет данных за период</div>
                            <div v-else class="d-flex flex-column gap-4">
                                <div v-if="gscRows.length">
                                    <h3 class="h6 mb-2">По дням</h3>
                                    <div class="table-responsive">
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

                                <div v-if="gscQueries.length">
                                    <h3 class="h6 mb-2">Топ-запросы</h3>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Запрос</th>
                                                    <th>Клики</th>
                                                    <th>Показы</th>
                                                    <th>CTR</th>
                                                    <th>Позиция</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="row in gscQueries" :key="`query-${row.rank}-${row.value}`">
                                                    <td>{{ row.rank }}</td>
                                                    <td>{{ row.value }}</td>
                                                    <td>{{ row.clicks }}</td>
                                                    <td>{{ row.impressions }}</td>
                                                    <td>{{ formatPct(row.ctr) }}</td>
                                                    <td>{{ formatNumber(row.position) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div v-if="gscPages.length">
                                    <h3 class="h6 mb-2">Топ-страницы</h3>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Страница</th>
                                                    <th>Клики</th>
                                                    <th>Показы</th>
                                                    <th>CTR</th>
                                                    <th>Позиция</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="row in gscPages" :key="`page-${row.rank}-${row.value}`">
                                                    <td>{{ row.rank }}</td>
                                                    <td class="text-break">{{ row.value }}</td>
                                                    <td>{{ row.clicks }}</td>
                                                    <td>{{ row.impressions }}</td>
                                                    <td>{{ formatPct(row.ctr) }}</td>
                                                    <td>{{ formatNumber(row.position) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div v-if="gscDevices.length">
                                    <h3 class="h6 mb-2">Устройства</h3>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Устройство</th>
                                                    <th>Клики</th>
                                                    <th>Показы</th>
                                                    <th>CTR</th>
                                                    <th>Позиция</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="row in gscDevices" :key="`device-${row.value}`">
                                                    <td>{{ formatGscDevice(row.value) }}</td>
                                                    <td>{{ row.clicks }}</td>
                                                    <td>{{ row.impressions }}</td>
                                                    <td>{{ formatPct(row.ctr) }}</td>
                                                    <td>{{ formatNumber(row.position) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div v-if="gscCountries.length">
                                    <h3 class="h6 mb-2">Страны</h3>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Страна</th>
                                                    <th>Клики</th>
                                                    <th>Показы</th>
                                                    <th>CTR</th>
                                                    <th>Позиция</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="row in gscCountries" :key="`country-${row.rank}-${row.value}`">
                                                    <td>{{ row.rank }}</td>
                                                    <td>{{ row.value }}</td>
                                                    <td>{{ row.clicks }}</td>
                                                    <td>{{ row.impressions }}</td>
                                                    <td>{{ formatPct(row.ctr) }}</td>
                                                    <td>{{ formatNumber(row.position) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div v-if="gscSearchAppearances.length">
                                    <h3 class="h6 mb-2">Типы отображения в поиске</h3>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Тип</th>
                                                    <th>Клики</th>
                                                    <th>Показы</th>
                                                    <th>CTR</th>
                                                    <th>Позиция</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="row in gscSearchAppearances"
                                                    :key="`appearance-${row.rank}-${row.value}`"
                                                >
                                                    <td>{{ row.rank }}</td>
                                                    <td>{{ row.value }}</td>
                                                    <td>{{ row.clicks }}</td>
                                                    <td>{{ row.impressions }}</td>
                                                    <td>{{ formatPct(row.ctr) }}</td>
                                                    <td>{{ formatNumber(row.position) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div v-if="gscSitemaps.length">
                                    <h3 class="h6 mb-2">Sitemaps</h3>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>URL</th>
                                                    <th>Тип</th>
                                                    <th>Ошибки</th>
                                                    <th>Предупр.</th>
                                                    <th>Статус</th>
                                                    <th>Загружен</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="row in gscSitemaps"
                                                    :key="`sitemap-${row.path}`"
                                                >
                                                    <td class="text-break">{{ row.path }}</td>
                                                    <td>{{ row.type || '—' }}</td>
                                                    <td>{{ row.errors }}</td>
                                                    <td>{{ row.warnings }}</td>
                                                    <td>
                                                        <span v-if="row.is_pending">В обработке</span>
                                                        <span v-else-if="row.is_sitemaps_index">Индекс</span>
                                                        <span v-else>Готов</span>
                                                    </td>
                                                    <td>{{ formatDateTime(row.last_downloaded_at) || '—' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div v-if="gscUrlInspections.length">
                                    <h3 class="h6 mb-2">URL Inspection</h3>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>URL</th>
                                                    <th>Вердикт</th>
                                                    <th>Загрузка</th>
                                                    <th>Индексация</th>
                                                    <th>Покрытие</th>
                                                    <th>Последний обход</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="row in gscUrlInspections"
                                                    :key="`inspect-${row.inspected_url}`"
                                                >
                                                    <td class="text-break">
                                                        <a
                                                            v-if="row.inspection_result_link"
                                                            :href="row.inspection_result_link"
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                        >{{ row.inspected_url }}</a>
                                                        <span v-else>{{ row.inspected_url }}</span>
                                                    </td>
                                                    <td>{{ formatInspectionLabel(row.verdict) }}</td>
                                                    <td>{{ formatInspectionLabel(row.page_fetch_state) }}</td>
                                                    <td>{{ formatInspectionLabel(row.indexing_state) }}</td>
                                                    <td>{{ row.coverage_state || '—' }}</td>
                                                    <td>{{ formatDateTime(row.last_crawl_time) || '—' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
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

                        <div
                            v-show="activeMetricsTab === 'pagespeed'"
                            id="metrics-pane-pagespeed"
                            class="page-app-sites__metrics-pane"
                            role="tabpanel"
                            aria-labelledby="metrics-tab-pagespeed"
                        >
                            <p v-if="pagespeedIntegration?.strategy_label" class="text-muted small mb-3">
                                Стратегия: {{ pagespeedIntegration.strategy_label }}
                            </p>
                            <AppLoader v-if="metricsLoading" block label="Загрузка PageSpeed / CrUX…" />
                            <div v-else-if="!pagespeedConnected" class="text-muted">
                                PageSpeed Insights не подключён
                            </div>
                            <template v-else>
                                <h3 class="h6 mb-2">Lab (Lighthouse)</h3>
                                <div v-if="!pagespeedLabRows.length" class="text-muted mb-4">Нет lab-снимков</div>
                                <div v-else class="table-responsive mb-4">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Стратегия</th>
                                                <th>Score</th>
                                                <th>LCP</th>
                                                <th>INP</th>
                                                <th>CLS</th>
                                                <th>FCP</th>
                                                <th>TTFB</th>
                                                <th>TBT</th>
                                                <th>SI</th>
                                                <th>Загружено</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(row, index) in pagespeedLabRows"
                                                :key="`lab-${row.strategy}-${row.fetched_at}-${index}`"
                                            >
                                                <td>{{ formatPagespeedStrategy(row.strategy) }}</td>
                                                <td>{{ formatPagespeedScore(row.performance_score) }}</td>
                                                <td>{{ formatMs(row.lcp_ms) }}</td>
                                                <td>{{ formatMs(row.inp_ms) }}</td>
                                                <td>{{ formatCls(row.cls) }}</td>
                                                <td>{{ formatMs(row.fcp_ms) }}</td>
                                                <td>{{ formatMs(row.ttfb_ms) }}</td>
                                                <td>{{ formatMs(row.tbt_ms) }}</td>
                                                <td>{{ formatMs(row.speed_index_ms) }}</td>
                                                <td>{{ formatDateTime(row.fetched_at) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <h3 class="h6 mb-2">CrUX (полевые данные)</h3>
                                <div v-if="!pagespeedCruxRows.length" class="text-muted">Нет CrUX-снимков</div>
                                <div v-else class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Scope</th>
                                                <th>Form factor</th>
                                                <th>LCP p75</th>
                                                <th>INP p75</th>
                                                <th>CLS p75</th>
                                                <th>FCP p75</th>
                                                <th>TTFB p75</th>
                                                <th>Период</th>
                                                <th>Загружено</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(row, index) in pagespeedCruxRows"
                                                :key="`crux-${row.scope}-${row.form_factor}-${row.fetched_at}-${index}`"
                                            >
                                                <td>{{ formatCruxScope(row.scope) }}</td>
                                                <td>{{ formatCruxFormFactor(row.form_factor) }}</td>
                                                <td>{{ formatMs(row.lcp_p75_ms) }}</td>
                                                <td>{{ formatMs(row.inp_p75_ms) }}</td>
                                                <td>{{ formatCls(row.cls_p75) }}</td>
                                                <td>{{ formatMs(row.fcp_p75_ms) }}</td>
                                                <td>{{ formatMs(row.ttfb_p75_ms) }}</td>
                                                <td>{{ formatCruxPeriod(row) }}</td>
                                                <td>{{ formatDateTime(row.fetched_at) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                        </div>
                    </div>
                </section>
            </div>

            <div
                v-show="activeSiteView === 'events'"
                id="site-view-pane-events"
                role="tabpanel"
                aria-labelledby="site-view-tab-events"
            >
                <section
                    class="page-app-sites__panel page-app-sites__events"
                    aria-label="События сайта"
                >
                    <div class="page-app-sites__panel-head">
                        <h2 class="h5 mb-0">События</h2>
                    </div>

                    <template v-if="isEventEditing">
                        <div class="page-app-sites__event-actions mb-3">
                            <button
                                type="button"
                                class="btn btn-secondary"
                                :disabled="eventSaving || Boolean(deletingEventId)"
                                @click="onBackFromEventEdit"
                            >
                                Назад
                            </button>
                        </div>

                        <p class="text-muted small mb-3">
                            Редактирование сохранённого события.
                        </p>

                        <div
                            v-if="eventFormError"
                            class="alert alert-danger py-2 mb-3"
                        >
                            {{ eventFormError }}
                        </div>

                        <form
                            class="page-app-sites__event-form"
                            @submit.prevent="onSubmitEvent"
                        >
                            <div class="page-app-sites__event-form-grid">
                                <div class="page-app-sites__period-field">
                                    <label class="form-label" for="event-edit-occurred-on">Дата</label>
                                    <input
                                        id="event-edit-occurred-on"
                                        v-model="eventForm.occurred_on"
                                        type="date"
                                        class="form-control"
                                        :max="todayDateString()"
                                        :disabled="busy || eventSaving || Boolean(deletingEventId)"
                                        required
                                    >
                                </div>
                                <div class="page-app-sites__event-field page-app-sites__event-field--title">
                                    <label class="form-label" for="event-edit-title">Название</label>
                                    <input
                                        id="event-edit-title"
                                        v-model="eventForm.title"
                                        type="text"
                                        class="form-control"
                                        maxlength="255"
                                        :disabled="busy || eventSaving || Boolean(deletingEventId)"
                                        required
                                    >
                                </div>
                                <div class="page-app-sites__event-field page-app-sites__event-field--url">
                                    <label class="form-label" for="event-edit-url">Ссылка</label>
                                    <input
                                        id="event-edit-url"
                                        v-model="eventForm.url"
                                        type="url"
                                        class="form-control"
                                        maxlength="2048"
                                        placeholder="https://…"
                                        :disabled="busy || eventSaving || Boolean(deletingEventId)"
                                    >
                                </div>
                                <div class="page-app-sites__event-field page-app-sites__event-field--description">
                                    <label class="form-label" for="event-edit-description">Описание</label>
                                    <textarea
                                        id="event-edit-description"
                                        v-model="eventForm.description"
                                        class="form-control"
                                        rows="3"
                                        maxlength="5000"
                                        placeholder="Кратко, что произошло и почему это важно"
                                        :disabled="busy || eventSaving || Boolean(deletingEventId)"
                                    />
                                </div>
                            </div>
                            <div class="page-app-sites__event-form-actions">
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    :disabled="busy || eventSaving || Boolean(deletingEventId) || !canSaveEvent"
                                >
                                    <AppLoader
                                        v-if="eventSaving"
                                        size="sm"
                                    />
                                    <span>
                                        {{ eventSaving ? 'Сохранение…' : 'Сохранить' }}
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    :disabled="busy || eventSaving || Boolean(deletingEventId)"
                                    @click="onBackFromEventEdit"
                                >
                                    Отмена
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-outline-danger"
                                    :disabled="busy || eventSaving || Boolean(deletingEventId)"
                                    @click="onDeleteEditingEvent"
                                >
                                    <AppLoader
                                        v-if="deletingEventId"
                                        size="sm"
                                    />
                                    <span>
                                        {{ deletingEventId ? 'Удаление…' : 'Удалить' }}
                                    </span>
                                </button>
                            </div>
                        </form>
                    </template>

                    <template v-else>
                        <AppLoader
                            v-if="!eventsLoaded && eventsLoading"
                            block
                            label="Загрузка событий…"
                        />

                        <template v-else>
                            <div
                                v-if="eventsError"
                                class="alert alert-danger py-2 mb-3"
                            >
                                {{ eventsError }}
                            </div>

                            <div
                                v-if="showEventTabs"
                                class="page-app-sites__view-switch page-app-sites__event-tabs"
                                role="tablist"
                                aria-label="Разделы событий"
                            >
                                <button
                                    v-for="tab in eventTabs"
                                    :id="`event-tab-${tab.id}`"
                                    :key="tab.id"
                                    type="button"
                                    class="page-app-sites__view-switch-btn"
                                    :class="{ 'is-active': activeEventTab === tab.id }"
                                    role="tab"
                                    :aria-selected="activeEventTab === tab.id"
                                    :aria-controls="`event-pane-${tab.id}`"
                                    @click="activeEventTab = tab.id"
                                >
                                    {{ tab.label }}
                                </button>
                            </div>

                            <div
                                v-show="activeEventTab === 'saved'"
                                id="event-pane-saved"
                                role="tabpanel"
                                :aria-labelledby="showEventTabs ? 'event-tab-saved' : undefined"
                            >
                                <p class="text-muted small mb-3">
                                    Выберите даты, чтобы просмотреть события и включить их в AI-отчёт.
                                </p>
                                <div class="page-app-sites__period-row mb-3">
                                    <div class="page-app-sites__period-field">
                                        <label class="form-label" for="events-from">С</label>
                                        <input
                                            id="events-from"
                                            v-model="period.from"
                                            type="date"
                                            class="form-control"
                                            :max="period.to || periodMax"
                                            :disabled="busy || eventsLoading || eventSaving"
                                            @change="onPeriodChange"
                                        >
                                    </div>
                                    <div class="page-app-sites__period-field">
                                        <label class="form-label" for="events-to">По</label>
                                        <input
                                            id="events-to"
                                            v-model="period.to"
                                            type="date"
                                            class="form-control"
                                            :min="period.from"
                                            :max="periodMax"
                                            :disabled="busy || eventsLoading || eventSaving"
                                            @change="onPeriodChange"
                                        >
                                    </div>
                                </div>

                                <AppLoader
                                    v-if="eventsLoading"
                                    block
                                    label="Загрузка событий…"
                                />
                                <p
                                    v-else-if="!eventRows.length"
                                    class="text-muted small mb-0"
                                >
                                    Нет событий за выбранный период
                                </p>
                                <div
                                    v-else
                                    class="page-app-sites__event-cards"
                                    role="list"
                                >
                                    <button
                                        v-for="row in eventRows"
                                        :key="row.id"
                                        type="button"
                                        class="page-app-sites__event-card"
                                        role="listitem"
                                        :disabled="busy || eventSaving"
                                        @click="startEditEvent(row)"
                                    >
                                        <span class="page-app-sites__event-card-title">
                                            {{ row.title }}
                                        </span>
                                        <span
                                            v-if="row.description"
                                            class="page-app-sites__event-card-meta"
                                        >
                                            {{ eventCardDescription(row) }}
                                        </span>
                                        <span class="page-app-sites__event-card-date">
                                            {{ formatDate(row.occurred_on) }}
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <div
                                v-show="activeEventTab === 'new'"
                                id="event-pane-new"
                                role="tabpanel"
                                :aria-labelledby="showEventTabs ? 'event-tab-new' : undefined"
                            >
                                <p class="text-muted small mb-3">
                                    Например: «На портале Onliner вышла статья про наш сайт».
                                </p>

                                <div
                                    v-if="eventFormError"
                                    class="alert alert-danger py-2 mb-3"
                                >
                                    {{ eventFormError }}
                                </div>

                                <form
                                    class="page-app-sites__event-form"
                                    @submit.prevent="onSubmitEvent"
                                >
                                    <div class="page-app-sites__event-form-grid">
                                        <div class="page-app-sites__period-field">
                                            <label class="form-label" for="event-occurred-on">Дата</label>
                                            <input
                                                id="event-occurred-on"
                                                v-model="eventForm.occurred_on"
                                                type="date"
                                                class="form-control"
                                                :max="todayDateString()"
                                                :disabled="busy || eventSaving"
                                                required
                                            >
                                        </div>
                                        <div class="page-app-sites__event-field page-app-sites__event-field--title">
                                            <label class="form-label" for="event-title">Название</label>
                                            <input
                                                id="event-title"
                                                v-model="eventForm.title"
                                                type="text"
                                                class="form-control"
                                                maxlength="255"
                                                :disabled="busy || eventSaving"
                                                required
                                            >
                                        </div>
                                        <div class="page-app-sites__event-field page-app-sites__event-field--url">
                                            <label class="form-label" for="event-url">Ссылка</label>
                                            <input
                                                id="event-url"
                                                v-model="eventForm.url"
                                                type="url"
                                                class="form-control"
                                                maxlength="2048"
                                                placeholder="https://…"
                                                :disabled="busy || eventSaving"
                                            >
                                        </div>
                                        <div class="page-app-sites__event-field page-app-sites__event-field--description">
                                            <label class="form-label" for="event-description">Описание</label>
                                            <textarea
                                                id="event-description"
                                                v-model="eventForm.description"
                                                class="form-control"
                                                rows="3"
                                                maxlength="5000"
                                                placeholder="Кратко, что произошло и почему это важно"
                                                :disabled="busy || eventSaving"
                                            />
                                        </div>
                                    </div>
                                    <div class="page-app-sites__event-form-actions">
                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                            :disabled="busy || eventSaving || !canSaveEvent"
                                        >
                                            <AppLoader
                                                v-if="eventSaving"
                                                size="sm"
                                            />
                                            <span>
                                                {{ eventSaving ? 'Сохранение…' : 'Добавить' }}
                                            </span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </template>
                    </template>
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
                        <div
                            v-if="isAiReportOpen"
                            class="page-app-sites__ai-report-toolbar"
                        >
                            <button
                                type="button"
                                class="btn btn-outline-primary btn-sm page-app-sites__ai-report-share-btn"
                                :disabled="aiReportLoading || aiReportPdfExporting || !aiReportReply"
                                @click="onDownloadAiReportPdf"
                            >
                                <FontAwesomeIcon
                                    :icon="['fas', 'file-pdf']"
                                    aria-hidden="true"
                                />
                                <span>{{ aiReportPdfExporting ? 'Сохранение…' : 'Скачать PDF' }}</span>
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-primary btn-sm page-app-sites__ai-report-share-btn"
                                :disabled="aiReportLoading || !selectedAiReportId || aiReportSharingSaving || aiReportPdfExporting"
                                @click="openAiReportSharingModal"
                            >
                                <FontAwesomeIcon
                                    :icon="['fas', 'share-nodes']"
                                    aria-hidden="true"
                                />
                                <span>Поделиться</span>
                            </button>
                        </div>
                    </div>

                    <template v-if="isAiReportOpen">
                        <div class="page-app-sites__ai-report-actions mb-3">
                            <button
                                type="button"
                                class="btn btn-secondary"
                                :disabled="aiReportLoading || aiReportSharingSaving || aiReportPdfExporting"
                                @click="onBackFromAiReport"
                            >
                                Назад
                            </button>
                        </div>

                        <div
                            v-if="aiReportError"
                            class="alert alert-danger py-2 mb-0"
                        >
                            {{ aiReportError }}
                        </div>

                        <AppLoader
                            v-if="aiReportLoading"
                            block
                            label="Загрузка отчёта…"
                        />

                        <template v-else-if="aiReportReply">
                            <div class="page-app-sites__ai-report-reply">
                                <div class="page-app-sites__ai-report-reply-head">
                                    <h3 class="page-app-sites__ai-report-reply-title">Результат</h3>
                                    <span
                                        v-if="aiReportModel"
                                        class="page-app-sites__ai-report-reply-meta"
                                    >
                                        {{ aiReportModel }}
                                    </span>
                                </div>
                                <div
                                    ref="aiReportExportEl"
                                    class="page-app-sites__ai-report-reply-body"
                                >
                                    <AppAiReportBody
                                        :source="aiReportReply"
                                        :charts="aiReportCharts"
                                    />
                                </div>
                            </div>

                            <div
                                v-if="showAiReportStats"
                                class="page-app-sites__ai-report-usage mt-3"
                                aria-label="Статистика запроса к AI"
                            >
                                <h3 class="page-app-sites__ai-report-usage-title">
                                    Статистика запроса к AI
                                </h3>

                                <p
                                    v-if="aiReportMeta"
                                    class="page-app-sites__ai-report-usage-meta"
                                >
                                    {{ aiReportMeta }}
                                </p>

                                <dl
                                    v-if="aiReportUsageItems.length"
                                    class="page-app-sites__ai-report-usage-grid"
                                >
                                    <div
                                        v-for="item in aiReportUsageItems"
                                        :key="item.key"
                                        class="page-app-sites__ai-report-usage-item"
                                    >
                                        <dt>{{ item.label }}</dt>
                                        <dd>{{ item.value }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </template>
                    </template>

                    <template v-else>
                        <AppLoader
                            v-if="!aiReportsLoaded && aiReportsLoading"
                            block
                            label="Загрузка отчётов…"
                        />

                        <template v-else>
                            <div
                                v-if="aiReportsError"
                                class="alert alert-danger py-2 mb-3"
                            >
                                {{ aiReportsError }}
                            </div>

                            <div
                                v-if="showAiReportTabs"
                                class="page-app-sites__view-switch page-app-sites__ai-report-tabs"
                                role="tablist"
                                aria-label="Разделы AI-отчёта"
                            >
                                <button
                                    v-for="tab in aiReportTabs"
                                    :id="`ai-report-tab-${tab.id}`"
                                    :key="tab.id"
                                    type="button"
                                    class="page-app-sites__view-switch-btn"
                                    :class="{ 'is-active': activeAiReportTab === tab.id }"
                                    role="tab"
                                    :aria-selected="activeAiReportTab === tab.id"
                                    :aria-controls="`ai-report-pane-${tab.id}`"
                                    @click="activeAiReportTab = tab.id"
                                >
                                    {{ tab.label }}
                                </button>
                            </div>

                            <div
                                v-show="activeAiReportTab === 'saved'"
                                id="ai-report-pane-saved"
                                role="tabpanel"
                                :aria-labelledby="showAiReportTabs ? 'ai-report-tab-saved' : undefined"
                            >
                                <AppLoader
                                    v-if="aiReportsLoading"
                                    block
                                    label="Загрузка отчётов…"
                                />
                                <p
                                    v-else-if="!aiReports.length"
                                    class="text-muted small mb-0"
                                >
                                    Пока нет сохранённых отчётов
                                </p>
                                <div
                                    v-else
                                    class="page-app-sites__ai-report-cards"
                                    role="list"
                                >
                                    <button
                                        v-for="report in aiReports"
                                        :key="report.id"
                                        type="button"
                                        class="page-app-sites__ai-report-card"
                                        role="listitem"
                                        :disabled="aiReportGenerating"
                                        @click="onOpenAiReport(report)"
                                    >
                                        <span class="page-app-sites__ai-report-card-title">
                                            {{ aiReportCardTitle(report) }}
                                        </span>
                                        <span
                                            v-if="aiReportSharingLabel(report)"
                                            class="page-app-sites__ai-report-card-share"
                                        >
                                            {{ aiReportSharingLabel(report) }}
                                        </span>
                                        <span class="page-app-sites__ai-report-card-meta">
                                            {{ aiReportCardTool(report) }}
                                        </span>
                                        <span class="page-app-sites__ai-report-card-period">
                                            {{ aiReportCardPeriod(report) }}
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <div
                                v-show="activeAiReportTab === 'new'"
                                id="ai-report-pane-new"
                                role="tabpanel"
                                :aria-labelledby="showAiReportTabs ? 'ai-report-tab-new' : undefined"
                            >
                                <p class="text-muted small mb-3">
                                    В отчёт попадут данные GA4, Search Console (включая топ-запросы, страницы, устройства и страны за выбранный период), GitHub, PageSpeed / CrUX и события. Срезы GSC появляются после загрузки метрик за тот же период.
                                    (тот же, что на вкладках «Данные сервисов» и «События»).
                                </p>

                                <div class="page-app-sites__period-row mb-3">
                                    <div class="page-app-sites__period-field">
                                        <label class="form-label" for="ai-report-from">С</label>
                                        <input
                                            id="ai-report-from"
                                            v-model="period.from"
                                            type="date"
                                            class="form-control"
                                            :max="period.to || periodMax"
                                            :disabled="busy || aiReportGenerating"
                                            @change="onPeriodChange"
                                        >
                                    </div>
                                    <div class="page-app-sites__period-field">
                                        <label class="form-label" for="ai-report-to">По</label>
                                        <input
                                            id="ai-report-to"
                                            v-model="period.to"
                                            type="date"
                                            class="form-control"
                                            :min="period.from"
                                            :max="periodMax"
                                            :disabled="busy || aiReportGenerating"
                                            @change="onPeriodChange"
                                        >
                                    </div>
                                </div>

                                <AppLoader
                                    v-if="aiServicesLoading"
                                    block
                                    label="Загрузка AI-сервисов…"
                                />
                                <div
                                    v-else-if="aiServicesError"
                                    class="alert alert-danger py-2"
                                >
                                    {{ aiServicesError }}
                                </div>
                                <template v-else>
                                    <div class="page-app-sites__ai-report-field mb-3">
                                        <label class="form-label" for="ai-report-service">AI-сервис</label>
                                        <select
                                            id="ai-report-service"
                                            v-model="aiReportServiceId"
                                            class="form-select"
                                            :disabled="busy || aiReportGenerating || !aiServices.length"
                                        >
                                            <option value="">
                                                {{ aiServices.length ? 'Выберите сервис' : 'Нет доступных сервисов' }}
                                            </option>
                                            <option
                                                v-for="service in aiServices"
                                                :key="service.id"
                                                :value="String(service.id)"
                                            >
                                                {{ aiServiceOptionLabel(service) }}
                                            </option>
                                        </select>
                                        <p v-if="!aiServices.length" class="form-text mb-0">
                                            Сначала
                                            <RouterLink :to="{ name: 'ai-services.create' }">
                                                добавьте AI-сервис
                                            </RouterLink>.
                                        </p>
                                    </div>

                                    <div class="page-app-sites__ai-report-actions mb-3">
                                        <button
                                            type="button"
                                            class="btn btn-primary"
                                            :disabled="!canGenerateAiReport"
                                            @click="onGenerateAiReport"
                                        >
                                            <AppLoader
                                                v-if="aiReportGenerating"
                                                size="sm"
                                            />
                                            <span>
                                                {{ aiReportGenerating ? 'Формирование…' : 'Сформировать отчёт' }}
                                            </span>
                                        </button>
                                    </div>

                                    <div
                                        v-if="aiReportError"
                                        class="alert alert-danger py-2 mb-0"
                                    >
                                        {{ aiReportError }}
                                    </div>
                                </template>
                            </div>
                        </template>
                    </template>
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

        <AppModal
            v-model:open="pagespeedModalOpen"
            title="PageSpeed Insights / CrUX"
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
                            : 'Подключите Google, чтобы включить PageSpeed Insights / CrUX.' }}
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
                <label class="form-label" for="pagespeed-strategy">Стратегия</label>
                <select
                    id="pagespeed-strategy"
                    v-model="pagespeedForm.strategy"
                    class="form-select mb-3"
                    :disabled="busy"
                >
                    <option
                        v-for="option in pagespeedStrategyOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <div class="page-app-sites__modal-actions">
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="busy"
                        @click="onSavePagespeedIntegration"
                    >
                        {{ pagespeedConnected ? 'Сохранить' : 'Подключить к сайту' }}
                    </button>
                    <button
                        v-if="pagespeedConnected"
                        type="button"
                        class="btn btn-outline-danger"
                        :disabled="busy"
                        @click="onDisconnectPagespeedIntegration"
                    >
                        Отключить от сайта
                    </button>
                </div>
            </template>
        </AppModal>

        <AppModal
            v-model:open="aiReportSharingOpen"
            title="Доступ к отчёту"
            align="start"
            size="md"
            :show-confirm="false"
            :close-on-backdrop="!aiReportSharingSaving"
        >
            <div class="page-app-sites__ai-report-share">
                <fieldset class="page-app-sites__ai-report-share-options">
                    <legend class="form-label">Кто может открыть отчёт</legend>

                    <label
                        v-for="option in aiReportSharingOptions"
                        :key="option.value"
                        class="page-app-sites__ai-report-share-option"
                    >
                        <input
                            v-model="aiReportSharingForm.visibility"
                            type="radio"
                            class="form-check-input"
                            name="ai-report-visibility"
                            :value="option.value"
                            :disabled="aiReportSharingSaving"
                        >
                        <span>{{ option.label }}</span>
                    </label>
                </fieldset>

                <div
                    v-if="aiReportSharingForm.visibility === 'password'"
                    class="page-app-sites__ai-report-share-password mb-3"
                >
                    <label
                        class="form-label"
                        for="ai-report-share-password"
                    >
                        {{ aiReportSharing.has_password ? 'Новый пароль' : 'Пароль' }}
                    </label>
                    <input
                        id="ai-report-share-password"
                        v-model="aiReportSharingForm.password"
                        type="password"
                        class="form-control"
                        :class="{ 'is-invalid': Boolean(aiReportSharingErrors.password) }"
                        autocomplete="new-password"
                        :placeholder="aiReportSharing.has_password ? 'Оставьте пустым, чтобы не менять' : 'Задайте пароль'"
                        :disabled="aiReportSharingSaving"
                    >
                    <div
                        v-if="aiReportSharingErrors.password"
                        class="invalid-feedback d-block"
                    >
                        {{ aiReportSharingErrors.password }}
                    </div>
                </div>

                <div
                    v-if="aiReportSharing.share_url && aiReportSharingForm.visibility !== 'private'"
                    class="page-app-sites__ai-report-share-link mb-3"
                >
                    <label
                        class="form-label"
                        for="ai-report-share-url"
                    >Ссылка</label>
                    <div class="input-group">
                        <input
                            id="ai-report-share-url"
                            type="text"
                            class="form-control"
                            :value="aiReportSharing.share_url"
                            readonly
                        >
                        <button
                            type="button"
                            class="btn btn-secondary"
                            :disabled="aiReportSharingSaving"
                            @click="onCopyAiReportShareLink"
                        >
                            Копировать
                        </button>
                    </div>
                </div>

                <div
                    v-if="aiReportSharingError"
                    class="alert alert-danger py-2 mb-3"
                >
                    {{ aiReportSharingError }}
                </div>
            </div>

            <template #actions>
                <button
                    type="button"
                    class="btn btn-secondary"
                    :disabled="aiReportSharingSaving"
                    @click="aiReportSharingOpen = false"
                >
                    Отмена
                </button>
                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="aiReportSharingSaving || !canSaveAiReportSharing"
                    @click="onSaveAiReportSharing"
                >
                    <span
                        v-if="aiReportSharingSaving"
                        class="spinner-border spinner-border-sm"
                        aria-hidden="true"
                    />
                    {{ aiReportSharingSaving ? 'Сохранение…' : 'Сохранить' }}
                </button>
            </template>
        </AppModal>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import SiteIntegrationCard from '../../components/SiteIntegrationCard.vue';
import AppAiReportBody from '../../../shared/components/AppAiReportBody.vue';
import AppLoader from '../../../shared/components/AppLoader.vue';
import AppModal from '../../../shared/components/AppModal.vue';
import {
    buildAiReportPdfFilename,
    downloadAiReportPdf,
} from '../../../shared/exportAiReportPdf';
import { toast } from '../../../shared/toast';
import { listAiServices } from '../../api/aiServices';
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
    deleteSitePageSpeedIntegration,
    getSitePageSpeedMetrics,
    syncSitePageSpeedIntegration,
    upsertSitePageSpeedIntegration,
} from '../../api/pagespeed';
import {
    disconnectGoogle,
    createSiteEvent,
    deleteSiteEvent,
    generateSiteAiReport,
    getGoogleConnection,
    getSite,
    getSiteAiReport,
    getSiteAnalyticsMetrics,
    getSiteSearchConsoleMetrics,
    listGa4Properties,
    listGscSites,
    listSiteAiReports,
    listSiteEvents,
    startGoogleOAuth,
    syncSiteGoogleIntegration,
    updateSiteAiReportSharing,
    updateSiteEvent,
    updateSiteGoogleIntegration,
} from '../../api/sites';

const route = useRoute();

const site = ref(null);
const connection = ref(null);
const githubConnection = ref(null);
const integration = ref(null);
const githubIntegration = ref(null);
const pagespeedIntegration = ref(null);
const ga4Properties = ref([]);
const gscSites = ref([]);
const githubRepositories = ref([]);
const githubBranches = ref([]);
const analyticsRows = ref([]);
const gscRows = ref([]);
const gscQueries = ref([]);
const gscPages = ref([]);
const gscDevices = ref([]);
const gscCountries = ref([]);
const gscSearchAppearances = ref([]);
const gscSitemaps = ref([]);
const gscUrlInspections = ref([]);
const commitRows = ref([]);
const pagespeedLabRows = ref([]);
const pagespeedCruxRows = ref([]);
const eventRows = ref([]);

const loading = ref(true);
const listsLoading = ref(false);
const githubReposLoading = ref(false);
const githubBranchesLoading = ref(false);
const metricsLoading = ref(false);
const eventsLoading = ref(false);
const eventsLoaded = ref(false);
const eventSaving = ref(false);
const deletingEventId = ref(null);
const busy = ref(false);
const error = ref('');
const listsError = ref('');
const githubReposError = ref('');
const githubBranchesError = ref('');
const eventsError = ref('');
const eventFormError = ref('');
const editingEventId = ref(null);
const activeEventTab = ref('saved');

const gaModalOpen = ref(false);
const gscModalOpen = ref(false);
const githubModalOpen = ref(false);
const pagespeedModalOpen = ref(false);
const activeSiteView = ref('data');
const activeMetricsTab = ref('ga4');

const aiServices = ref([]);
const aiServicesLoading = ref(false);
const aiServicesError = ref('');
const aiServicesLoaded = ref(false);
const aiReportServiceId = ref('');
const aiReportGenerating = ref(false);
const aiReportLoading = ref(false);
const aiReportError = ref('');
const aiReportReply = ref('');
const aiReportCharts = ref([]);
const aiReportModel = ref('');
const aiReportMeta = ref('');
const aiReportUsage = ref(null);
const aiReports = ref([]);
const aiReportsLoading = ref(false);
const aiReportsError = ref('');
const aiReportsLoaded = ref(false);
const selectedAiReportId = ref('');
const activeAiReportTab = ref('saved');
const aiReportExportEl = ref(null);
const aiReportPdfExporting = ref(false);
const aiReportSharing = reactive({
    visibility: 'private',
    share_url: null,
    has_password: false,
});
const aiReportSharingForm = reactive({
    visibility: 'private',
    password: '',
});
const aiReportSharingOpen = ref(false);
const aiReportSharingSaving = ref(false);
const aiReportSharingError = ref('');
const aiReportSharingErrors = reactive({
    password: '',
});
const aiReportSharingOptions = [
    { value: 'private', label: 'Приватный' },
    { value: 'link', label: 'Доступен по ссылке' },
    { value: 'password', label: 'Доступен по ссылке с паролем' },
];

const siteViewTabs = [
    { id: 'data', label: 'Данные сервисов' },
    { id: 'events', label: 'События' },
    { id: 'ai-report', label: 'AI отчёт' },
];

const aiReportTabs = [
    { id: 'saved', label: 'Сохранённые отчёты' },
    { id: 'new', label: 'Новый отчёт' },
];

const eventTabs = [
    { id: 'saved', label: 'Сохранённые события' },
    { id: 'new', label: 'Новое событие' },
];

const metricsTabs = [
    { id: 'ga4', label: 'GA4' },
    { id: 'gsc', label: 'Search Console' },
    { id: 'github', label: 'GitHub' },
    { id: 'pagespeed', label: 'PageSpeed / CrUX' },
];

const pagespeedStrategyOptions = [
    { value: 'mobile', label: 'Mobile' },
    { value: 'desktop', label: 'Desktop' },
    { value: 'both', label: 'Mobile и Desktop' },
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

const pagespeedForm = reactive({
    strategy: 'mobile',
});

const eventForm = reactive({
    occurred_on: periodMax,
    title: '',
    description: '',
    url: '',
});

const gaConnected = computed(() => Boolean(integration.value?.ga4_property_id));
const gscConnected = computed(() => Boolean(integration.value?.gsc_site_url));
const hasGscMetrics = computed(() => (
    gscRows.value.length > 0
    || gscQueries.value.length > 0
    || gscPages.value.length > 0
    || gscDevices.value.length > 0
    || gscCountries.value.length > 0
    || gscSearchAppearances.value.length > 0
    || gscSitemaps.value.length > 0
    || gscUrlInspections.value.length > 0
));
const githubConnected = computed(() => Boolean(githubIntegration.value?.is_configured));
const pagespeedConnected = computed(() => Boolean(pagespeedIntegration.value?.is_configured));

const syncMetricGroups = [
    {
        id: 'ga',
        label: 'Google Analytics',
        available: () => gaConnected.value,
        metrics: [
            { key: 'sessions', label: 'Сессии', defaultSelected: true },
            { key: 'total_users', label: 'Пользователи', defaultSelected: true },
            { key: 'new_users', label: 'Новые пользователи', defaultSelected: true },
            { key: 'screen_page_views', label: 'Просмотры', defaultSelected: true },
            { key: 'organic_sessions', label: 'Органические сессии', defaultSelected: true },
            { key: 'organic_total_users', label: 'Органические пользователи', defaultSelected: true },
            { key: 'organic_new_users', label: 'Органические новые пользователи', defaultSelected: true },
            { key: 'engaged_sessions', label: 'Вовлечённые сессии', defaultSelected: false, optional: true },
            { key: 'engagement_rate', label: 'Доля вовлечённости', defaultSelected: false, optional: true },
            { key: 'bounce_rate', label: 'Показатель отказов', defaultSelected: false, optional: true },
            { key: 'average_session_duration', label: 'Средняя длительность сессии', defaultSelected: false, optional: true },
            { key: 'event_count', label: 'События', defaultSelected: false, optional: true },
            { key: 'organic_engaged_sessions', label: 'Органические вовлечённые сессии', defaultSelected: false, optional: true },
        ],
    },
    {
        id: 'gsc-daily',
        label: 'Search Console (по дням)',
        available: () => gscConnected.value,
        metrics: [
            { key: 'clicks', label: 'Клики', defaultSelected: true },
            { key: 'impressions', label: 'Показы', defaultSelected: true },
            { key: 'ctr', label: 'CTR', defaultSelected: true },
            { key: 'position', label: 'Позиция', defaultSelected: true },
        ],
    },
    {
        id: 'gsc-dimensions',
        label: 'Search Console (разрезы)',
        available: () => gscConnected.value,
        metrics: [
            { key: 'queries', label: 'Запросы', defaultSelected: true, limitKey: 'queries' },
            { key: 'pages', label: 'Страницы', defaultSelected: true, limitKey: 'pages' },
            { key: 'devices', label: 'Устройства', defaultSelected: true },
            { key: 'countries', label: 'Страны', defaultSelected: true },
            { key: 'search_appearances', label: 'Типы отображения в поиске', defaultSelected: false, optional: true },
            { key: 'sitemaps', label: 'Sitemaps', defaultSelected: false, optional: true },
            {
                key: 'url_inspections',
                label: 'URL Inspection (топ-страницы)',
                defaultSelected: false,
                optional: true,
                limitKey: 'url_inspections',
            },
        ],
    },
    {
        id: 'github',
        label: 'GitHub',
        available: () => githubConnected.value,
        metrics: [
            { key: 'commits', label: 'Коммиты', defaultSelected: true },
        ],
    },
    {
        id: 'pagespeed',
        label: 'PageSpeed / CrUX',
        available: () => pagespeedConnected.value,
        metrics: [
            { key: 'psi_lab', label: 'Lab (Lighthouse)', defaultSelected: true },
            { key: 'crux_origin', label: 'CrUX origin', defaultSelected: true },
            { key: 'crux_url', label: 'CrUX URL', defaultSelected: false, optional: true },
        ],
    },
];

const allSyncMetricDefs = syncMetricGroups.flatMap((group) => group.metrics);

const syncMetricSelection = reactive(
    Object.fromEntries(allSyncMetricDefs.map((metric) => [metric.key, Boolean(metric.defaultSelected)])),
);

const syncMetricLimits = reactive({
    queries: 50,
    pages: 20,
    url_inspections: 10,
});

const availableSyncMetricGroups = computed(() => (
    syncMetricGroups.filter((group) => group.available())
));

const selectedSyncMetrics = computed(() => (
    availableSyncMetricGroups.value
        .flatMap((group) => group.metrics)
        .map((metric) => metric.key)
        .filter((key) => syncMetricSelection[key])
));

const PAGESPEED_SYNC_KEYS = new Set(['psi_lab', 'crux_origin', 'crux_url']);

const selectedGoogleSyncMetrics = computed(() => (
    selectedSyncMetrics.value.filter((key) => key !== 'commits' && !PAGESPEED_SYNC_KEYS.has(key))
));

const selectedGithubSyncMetrics = computed(() => (
    selectedSyncMetrics.value.filter((key) => key === 'commits')
));

const selectedPageSpeedSyncMetrics = computed(() => (
    selectedSyncMetrics.value.filter((key) => PAGESPEED_SYNC_KEYS.has(key))
));

const selectedGoogleSyncLimits = computed(() => {
    const limits = {};

    if (syncMetricSelection.queries) {
        limits.queries = Math.min(1000, Math.max(1, Number(syncMetricLimits.queries) || 50));
    }

    if (syncMetricSelection.pages) {
        limits.pages = Math.min(1000, Math.max(1, Number(syncMetricLimits.pages) || 20));
    }

    if (syncMetricSelection.url_inspections) {
        limits.url_inspections = Math.min(50, Math.max(1, Number(syncMetricLimits.url_inspections) || 10));
    }

    return limits;
});

const hasAnalyticsEngagement = computed(() => (
    analyticsRows.value.some((row) => (
        Number(row.engaged_sessions || 0) > 0
        || Number(row.engagement_rate || 0) > 0
        || Number(row.bounce_rate || 0) > 0
        || Number(row.average_session_duration || 0) > 0
        || Number(row.event_count || 0) > 0
        || Number(row.organic_engaged_sessions || 0) > 0
    ))
));

function isSyncMetricGroupFullySelected(group) {
    return group.metrics.every((metric) => syncMetricSelection[metric.key]);
}

function toggleSyncMetricGroup(group) {
    const next = !isSyncMetricGroupFullySelected(group);

    group.metrics.forEach((metric) => {
        syncMetricSelection[metric.key] = next;
    });
}

const canSyncPeriod = computed(() => (
    (gaConnected.value || gscConnected.value || githubConnected.value || pagespeedConnected.value)
    && Boolean(period.from)
    && Boolean(period.to)
    && period.from <= period.to
    && selectedSyncMetrics.value.length > 0
));

const canGenerateAiReport = computed(() => (
    Boolean(aiReportServiceId.value)
    && Boolean(period.from)
    && Boolean(period.to)
    && period.from <= period.to
    && !busy.value
    && !aiReportGenerating.value
    && !aiServicesLoading.value
));

const isAiReportOpen = computed(() => Boolean(selectedAiReportId.value));

const aiReportUsageItems = computed(() => {
    const usage = aiReportUsage.value;

    if (!usage || typeof usage !== 'object') {
        return [];
    }

    const fields = [
        { key: 'prompt_tokens', label: 'Входные токены' },
        { key: 'candidates_tokens', label: 'Выходные токены' },
        { key: 'thoughts_tokens', label: 'Токены размышлений' },
        { key: 'total_tokens', label: 'Всего токенов' },
    ];

    return fields
        .filter((field) => Number.isFinite(usage[field.key]))
        .map((field) => ({
            key: field.key,
            label: field.label,
            value: formatTokenCount(usage[field.key]),
        }));
});

const showAiReportStats = computed(() => (
    Boolean(aiReportMeta.value) || aiReportUsageItems.value.length > 0
));

const showAiReportTabs = computed(() => (
    aiReportsLoaded.value && aiReports.value.length > 0
));

const canSaveAiReportSharing = computed(() => {
    if (aiReportSharingForm.visibility !== 'password') {
        return true;
    }

    if (aiReportSharingForm.password.trim()) {
        return true;
    }

    return aiReportSharing.has_password
        && aiReportSharing.visibility === 'password';
});

const isEventEditing = computed(() => Boolean(editingEventId.value));

const showEventTabs = computed(() => eventsLoaded.value);

const canSaveEvent = computed(() => (
    Boolean(eventForm.occurred_on)
    && Boolean(String(eventForm.title || '').trim())
    && !eventSaving.value
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
const pagespeedDetail = computed(() => {
    if (!pagespeedConnected.value) {
        return '';
    }

    return pagespeedIntegration.value?.strategy_label || 'Подключено';
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

function todayDateString() {
    return new Date().toISOString().slice(0, 10);
}

function defaultPeriodFrom() {
    const date = new Date();
    date.setDate(date.getDate() - 28);

    return date.toISOString().slice(0, 10);
}

function formatDateTime(value) {
    if (!value) {
        return '';
    }

    try {
        return new Date(value).toLocaleString('ru-RU');
    } catch {
        return value;
    }
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    try {
        return new Date(`${value}T00:00:00`).toLocaleDateString('ru-RU');
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

function formatDuration(value) {
    const seconds = Math.max(0, Number(value) || 0);
    const mins = Math.floor(seconds / 60);
    const secs = Math.round(seconds % 60);

    if (mins <= 0) {
        return `${secs} с`;
    }

    return `${mins} м ${secs.toString().padStart(2, '0')} с`;
}

function formatGscDevice(value) {
    const map = {
        DESKTOP: 'Компьютер',
        MOBILE: 'Мобильный',
        TABLET: 'Планшет',
    };

    return map[String(value || '').toUpperCase()] || value || '—';
}

function formatInspectionLabel(value) {
    if (!value) {
        return '—';
    }

    const map = {
        PASS: 'OK',
        FAIL: 'Ошибка',
        NEUTRAL: 'Исключено',
        PARTIAL: 'Частично',
        SUCCESSFUL: 'Успешно',
        SOFT_404: 'Soft 404',
        NOT_FOUND: '404',
        SERVER_ERROR: 'Ошибка сервера',
        BLOCKED_ROBOTS_TXT: 'robots.txt',
        ACCESS_DENIED: '401',
        ACCESS_FORBIDDEN: '403',
        REDIRECT_ERROR: 'Редирект',
        INDEXING_ALLOWED: 'Разрешено',
        BLOCKED_BY_META_TAG: 'noindex (meta)',
        BLOCKED_BY_HTTP_HEADER: 'noindex (header)',
        ALLOWED: 'Разрешено',
        DISALLOWED: 'Запрещено',
        MOBILE: 'Mobile',
        DESKTOP: 'Desktop',
    };

    return map[String(value)] || value;
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
        pagespeedConnected.value ? 'pagespeed' : null,
    ].filter(Boolean);

    if (!connected.length) {
        return;
    }

    if (!connected.includes(activeMetricsTab.value)) {
        activeMetricsTab.value = connected[0];
    }
}

function formatMs(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return `${Math.round(Number(value))} ms`;
}

function formatCls(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return Number(value).toFixed(3);
}

function formatPagespeedScore(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

function formatPagespeedStrategy(value) {
    const map = {
        mobile: 'Mobile',
        desktop: 'Desktop',
    };

    return map[String(value)] || value || '—';
}

function formatCruxScope(value) {
    const map = {
        origin: 'Origin',
        url: 'URL',
    };

    return map[String(value)] || value || '—';
}

function formatCruxFormFactor(value) {
    const map = {
        PHONE: 'Phone',
        DESKTOP: 'Desktop',
        TABLET: 'Tablet',
    };

    return map[String(value)] || value || '—';
}

function formatCruxPeriod(row) {
    const start = row?.collection_period_start;
    const end = row?.collection_period_end;

    if (!start && !end) {
        return '—';
    }

    if (start && end) {
        return `${start} — ${end}`;
    }

    return start || end;
}

function clearAiReportView() {
    aiReportError.value = '';
    aiReportReply.value = '';
    aiReportCharts.value = [];
    aiReportModel.value = '';
    aiReportMeta.value = '';
    aiReportUsage.value = null;
    resetAiReportSharing();
}

function resetAiReportSharing() {
    aiReportSharing.visibility = 'private';
    aiReportSharing.share_url = null;
    aiReportSharing.has_password = false;
    aiReportSharingForm.visibility = 'private';
    aiReportSharingForm.password = '';
    aiReportSharingError.value = '';
    aiReportSharingErrors.password = '';
    aiReportSharingOpen.value = false;
}

function applyAiReportSharing(sharing) {
    aiReportSharing.visibility = sharing?.visibility || 'private';
    aiReportSharing.share_url = sharing?.share_url || null;
    aiReportSharing.has_password = Boolean(sharing?.has_password);
    aiReportSharingForm.visibility = aiReportSharing.visibility;
    aiReportSharingForm.password = '';
    aiReportSharingError.value = '';
    aiReportSharingErrors.password = '';
}

function aiReportSharingLabel(report) {
    const visibility = report?.sharing?.visibility;

    if (visibility === 'link') {
        return 'По ссылке';
    }

    if (visibility === 'password') {
        return 'По ссылке с паролем';
    }

    return '';
}

function openAiReportSharingModal() {
    if (!selectedAiReportId.value) {
        return;
    }

    aiReportSharingForm.visibility = aiReportSharing.visibility || 'private';
    aiReportSharingForm.password = '';
    aiReportSharingError.value = '';
    aiReportSharingErrors.password = '';
    aiReportSharingOpen.value = true;
}

async function onCopyAiReportShareLink() {
    const url = aiReportSharing.share_url;

    if (!url) {
        return;
    }

    try {
        await navigator.clipboard.writeText(url);
        toast.show({ ok: true, message: 'Ссылка скопирована.' });
    } catch {
        toast.show({ ok: false, message: 'Не удалось скопировать ссылку.' });
    }
}

async function onSaveAiReportSharing() {
    if (!site.value || !selectedAiReportId.value || !canSaveAiReportSharing.value) {
        return;
    }

    aiReportSharingSaving.value = true;
    aiReportSharingError.value = '';
    aiReportSharingErrors.password = '';

    const payload = {
        visibility: aiReportSharingForm.visibility,
    };

    if (aiReportSharingForm.visibility === 'password' && aiReportSharingForm.password.trim()) {
        payload.password = aiReportSharingForm.password.trim();
    }

    try {
        const report = await updateSiteAiReportSharing(
            site.value.id,
            selectedAiReportId.value,
            payload,
        );

        applyAiReportSharing(report.sharing);
        upsertAiReportListItem(report);
        toast.show({ ok: true, message: 'Настройки доступа сохранены.' });

        if (report.sharing?.visibility === 'private') {
            aiReportSharingOpen.value = false;
        }
    } catch (e) {
        aiReportSharingErrors.password = e.response?.data?.errors?.password?.[0] || '';
        aiReportSharingError.value = e.response?.data?.message
            || e.response?.data?.errors?.visibility?.[0]
            || (aiReportSharingErrors.password ? '' : 'Не удалось сохранить доступ.');
    } finally {
        aiReportSharingSaving.value = false;
    }
}

function upsertAiReportListItem(report) {
    if (!report?.id) {
        return;
    }

    const item = {
        id: report.id,
        created_at: report.created_at,
        period: report.period,
        tool: report.tool,
        data_counts: report.data_counts,
        usage: report.usage,
        sharing: report.sharing,
    };

    aiReports.value = [
        item,
        ...aiReports.value.filter((existing) => existing.id !== report.id),
    ];
}

function formatTokenCount(value) {
    return Number(value).toLocaleString('ru-RU');
}

function aiServiceOptionLabel(service) {
    const model = service.settings?.model;

    return model ? `${service.name} — ${model}` : service.name;
}

function aiReportCardTitle(report) {
    return report.created_at
        ? formatDateTime(report.created_at)
        : 'Без даты';
}

function aiReportCardTool(report) {
    return report.tool?.label || report.tool?.name || 'AI';
}

function aiReportCardPeriod(report) {
    const fromRaw = report.period?.from;
    const toRaw = report.period?.to;
    const from = fromRaw ? formatDate(fromRaw) : '—';
    const to = toRaw ? formatDate(toRaw) : '—';
    const days = aiReportPeriodDays(fromRaw, toRaw);

    if (days === null) {
        return `${from} — ${to}`;
    }

    return `${from} — ${to} (${days} ${pluralDays(days)})`;
}

function aiReportPeriodDays(from, to) {
    if (!from || !to) {
        return null;
    }

    const fromDate = new Date(`${from}T00:00:00`);
    const toDate = new Date(`${to}T00:00:00`);

    if (Number.isNaN(fromDate.getTime()) || Number.isNaN(toDate.getTime()) || toDate < fromDate) {
        return null;
    }

    const msPerDay = 24 * 60 * 60 * 1000;

    return Math.round((toDate - fromDate) / msPerDay) + 1;
}

function pluralDays(count) {
    const abs = Math.abs(count) % 100;
    const last = abs % 10;

    if (abs > 10 && abs < 20) {
        return 'дней';
    }

    if (last === 1) {
        return 'день';
    }

    if (last >= 2 && last <= 4) {
        return 'дня';
    }

    return 'дней';
}

function applyAiReport(report) {
    selectedAiReportId.value = report?.id ? String(report.id) : '';
    aiReportReply.value = report?.reply || '';
    aiReportCharts.value = Array.isArray(report?.charts) ? report.charts : [];
    aiReportModel.value = report?.tool?.label || report?.tool?.model || '';
    aiReportMeta.value = formatDataCounts(report?.data_counts);
    aiReportUsage.value = report?.usage ?? null;
    applyAiReportSharing(report?.sharing);

    if (report?.period?.from) {
        period.from = report.period.from;
    }

    if (report?.period?.to) {
        period.to = report.period.to;
    }
}

function onBackFromAiReport() {
    selectedAiReportId.value = '';
    clearAiReportView();
    activeAiReportTab.value = aiReports.value.length ? 'saved' : 'new';
}

function syncAiReportTab() {
    if (!aiReports.value.length) {
        activeAiReportTab.value = 'new';

        return;
    }

    if (activeAiReportTab.value !== 'new') {
        activeAiReportTab.value = 'saved';
    }
}

function formatDataCounts(counts) {
    if (!counts) {
        return '';
    }

    const parts = [];

    if (counts.analytics) {
        parts.push(`GA4: ${counts.analytics}`);
    }

    if (counts.search_console) {
        parts.push(`Search Console (дни): ${counts.search_console}`);
    }

    if (counts.search_console_queries) {
        parts.push(`запросы: ${counts.search_console_queries}`);
    }

    if (counts.search_console_pages) {
        parts.push(`страницы: ${counts.search_console_pages}`);
    }

    if (counts.search_console_devices) {
        parts.push(`устройства: ${counts.search_console_devices}`);
    }

    if (counts.search_console_countries) {
        parts.push(`страны: ${counts.search_console_countries}`);
    }

    if (counts.search_console_appearances) {
        parts.push(`типы отображения: ${counts.search_console_appearances}`);
    }

    if (counts.search_console_sitemaps) {
        parts.push(`sitemaps: ${counts.search_console_sitemaps}`);
    }

    if (counts.search_console_url_inspections) {
        parts.push(`URL Inspection: ${counts.search_console_url_inspections}`);
    }

    if (counts.pagespeed_lab) {
        parts.push(`PageSpeed lab: ${counts.pagespeed_lab}`);
    }

    if (counts.pagespeed_crux) {
        parts.push(`CrUX: ${counts.pagespeed_crux}`);
    }

    if (counts.github_commits) {
        parts.push(`GitHub: ${counts.github_commits}`);
    }

    if (counts.events) {
        parts.push(`События: ${counts.events}`);
    }

    return parts.length ? `В промпт передано — ${parts.join(', ')}.` : '';
}

async function loadAiReports() {
    if (!site.value || aiReportsLoading.value) {
        return;
    }

    aiReportsLoading.value = true;
    aiReportsError.value = '';

    try {
        aiReports.value = await listSiteAiReports(site.value.id);
        aiReportsLoaded.value = true;

        if (
            selectedAiReportId.value
            && !aiReports.value.some((item) => String(item.id) === String(selectedAiReportId.value))
        ) {
            selectedAiReportId.value = '';
            clearAiReportView();
        }

        syncAiReportTab();
    } catch (e) {
        aiReportsLoaded.value = true;
        aiReportsError.value = e.response?.data?.message || 'Не удалось загрузить отчёты';
        syncAiReportTab();
    } finally {
        aiReportsLoading.value = false;
    }
}

async function loadAiServices() {
    if (aiServicesLoading.value) {
        return;
    }

    aiServicesLoading.value = true;
    aiServicesError.value = '';

    try {
        const response = await listAiServices();
        aiServices.value = response.data ?? [];
        aiServicesLoaded.value = true;

        if (
            aiReportServiceId.value
            && !aiServices.value.some((item) => String(item.id) === String(aiReportServiceId.value))
        ) {
            aiReportServiceId.value = '';
        }
    } catch (e) {
        aiServicesError.value = e.response?.data?.message || 'Не удалось загрузить AI-сервисы';
    } finally {
        aiServicesLoading.value = false;
    }
}

async function onOpenAiReport(report) {
    if (!site.value || !report?.id || aiReportGenerating.value) {
        return;
    }

    selectedAiReportId.value = String(report.id);
    aiReportLoading.value = true;
    aiReportError.value = '';
    aiReportReply.value = '';
    aiReportCharts.value = [];
    aiReportModel.value = report.tool?.label || report.tool?.model || '';
    aiReportMeta.value = formatDataCounts(report.data_counts);
    aiReportUsage.value = report.usage ?? null;
    applyAiReportSharing(report.sharing);

    try {
        const fullReport = await getSiteAiReport(site.value.id, report.id);
        applyAiReport(fullReport);
    } catch (e) {
        clearAiReportView();
        selectedAiReportId.value = '';
        aiReportError.value = e.response?.data?.message || 'Не удалось загрузить отчёт';
    } finally {
        aiReportLoading.value = false;
    }
}

async function onDownloadAiReportPdf() {
    if (!aiReportExportEl.value || !aiReportReply.value || aiReportPdfExporting.value) {
        return;
    }

    aiReportPdfExporting.value = true;

    try {
        const from = period.from ? formatDate(period.from) : '';
        const to = period.to ? formatDate(period.to) : '';
        const subtitleParts = [];

        if (from || to) {
            subtitleParts.push(`Период: ${from || '—'} — ${to || '—'}`);
        }

        if (aiReportModel.value) {
            subtitleParts.push(aiReportModel.value);
        }

        await downloadAiReportPdf({
            element: aiReportExportEl.value,
            filename: buildAiReportPdfFilename({
                siteName: site.value?.name,
                periodFrom: period.from,
                periodTo: period.to,
            }),
            title: site.value?.name || 'AI-отчёт',
            subtitle: subtitleParts.join(' · '),
        });

        toast.show({ message: 'PDF сохранён' });
    } catch (e) {
        toast.show({
            message: e?.message || 'Не удалось сохранить PDF',
            ok: false,
        });
    } finally {
        aiReportPdfExporting.value = false;
    }
}

async function onGenerateAiReport() {
    if (!canGenerateAiReport.value || !site.value) {
        return;
    }

    aiReportGenerating.value = true;
    aiReportError.value = '';
    clearAiReportView();
    selectedAiReportId.value = '';

    try {
        const result = await generateSiteAiReport(site.value.id, {
            ai_service_id: Number(aiReportServiceId.value),
            from: period.from,
            to: period.to,
        });

        if (!result.ok) {
            aiReportError.value = result.message || 'Не удалось сформировать отчёт';
            aiReportMeta.value = formatDataCounts(result.data_counts);

            return;
        }

        const report = result.data;

        if (report) {
            upsertAiReportListItem(report);
            applyAiReport(report);
        }
    } catch (e) {
        aiReportError.value = e.response?.data?.message
            || e.response?.data?.errors?.ai_service_id?.[0]
            || 'Не удалось сформировать отчёт';
    } finally {
        aiReportGenerating.value = false;
    }
}

watch(activeSiteView, (view) => {
    if (view === 'events' && site.value) {
        loadEvents();

        return;
    }

    if (view !== 'ai-report') {
        return;
    }

    if (!aiServicesLoaded.value) {
        loadAiServices();
    }

    if (!aiReportsLoaded.value) {
        loadAiReports();
    }
});

async function loadEvents() {
    if (!site.value || !period.from || !period.to || eventsLoading.value) {
        return;
    }

    eventsLoading.value = true;
    eventsError.value = '';

    try {
        eventRows.value = await listSiteEvents(site.value.id, {
            from: period.from,
            to: period.to,
        });
        eventsLoaded.value = true;

        if (
            editingEventId.value
            && !eventRows.value.some((item) => item.id === editingEventId.value)
        ) {
            resetEventForm();
        }

        syncEventTab();
    } catch (e) {
        eventRows.value = [];
        eventsLoaded.value = true;
        eventsError.value = e.response?.data?.message || 'Не удалось загрузить события';
        syncEventTab();
    } finally {
        eventsLoading.value = false;
    }
}

function syncEventTab() {
    if (!eventRows.value.length) {
        if (activeEventTab.value !== 'saved') {
            activeEventTab.value = 'new';
        }

        return;
    }

    if (activeEventTab.value !== 'new') {
        activeEventTab.value = 'saved';
    }
}

function resetEventForm() {
    editingEventId.value = null;
    eventFormError.value = '';
    eventForm.occurred_on = period.to || periodMax;
    eventForm.title = '';
    eventForm.description = '';
    eventForm.url = '';
}

function onBackFromEventEdit() {
    resetEventForm();
    activeEventTab.value = eventRows.value.length ? 'saved' : 'new';
}

function startEditEvent(row) {
    editingEventId.value = row.id;
    eventFormError.value = '';
    eventForm.occurred_on = row.occurred_on || periodMax;
    eventForm.title = row.title || '';
    eventForm.description = row.description || '';
    eventForm.url = row.url || '';
}

function eventCardDescription(row) {
    const text = String(row?.description || '').trim();

    if (!text) {
        return '';
    }

    if (text.length <= 120) {
        return text;
    }

    return `${text.slice(0, 117).trimEnd()}…`;
}

function firstValidationError(errors) {
    if (!errors || typeof errors !== 'object') {
        return '';
    }

    const firstKey = Object.keys(errors)[0];

    if (!firstKey) {
        return '';
    }

    const messages = errors[firstKey];

    return Array.isArray(messages) ? (messages[0] || '') : String(messages || '');
}

async function onSubmitEvent() {
    if (!canSaveEvent.value || !site.value) {
        return;
    }

    eventSaving.value = true;
    eventFormError.value = '';

    const payload = {
        occurred_on: eventForm.occurred_on,
        title: String(eventForm.title || '').trim(),
        description: String(eventForm.description || '').trim() || null,
        url: String(eventForm.url || '').trim() || null,
    };

    try {
        if (editingEventId.value) {
            const updated = await updateSiteEvent(site.value.id, editingEventId.value, payload);
            const inPeriod = updated.occurred_on >= period.from && updated.occurred_on <= period.to;

            if (inPeriod) {
                eventRows.value = [
                    updated,
                    ...eventRows.value.filter((item) => item.id !== updated.id),
                ].sort((a, b) => {
                    if (a.occurred_on === b.occurred_on) {
                        return b.id - a.id;
                    }

                    return a.occurred_on < b.occurred_on ? 1 : -1;
                });
            } else {
                eventRows.value = eventRows.value.filter((item) => item.id !== updated.id);
            }

            toast.show({ ok: true, message: 'Событие обновлено.' });
            resetEventForm();
            activeEventTab.value = eventRows.value.length ? 'saved' : 'new';
        } else {
            const created = await createSiteEvent(site.value.id, payload);
            const inPeriod = created.occurred_on >= period.from && created.occurred_on <= period.to;

            if (inPeriod) {
                eventRows.value = [created, ...eventRows.value].sort((a, b) => {
                    if (a.occurred_on === b.occurred_on) {
                        return b.id - a.id;
                    }

                    return a.occurred_on < b.occurred_on ? 1 : -1;
                });
            }

            toast.show({ ok: true, message: 'Событие добавлено.' });
            resetEventForm();
            activeEventTab.value = eventRows.value.length ? 'saved' : 'new';
        }
    } catch (e) {
        eventFormError.value = firstValidationError(e.response?.data?.errors)
            || e.response?.data?.message
            || 'Не удалось сохранить событие';
    } finally {
        eventSaving.value = false;
    }
}

async function onDeleteEditingEvent() {
    if (!editingEventId.value) {
        return;
    }

    await onDeleteEvent({ id: editingEventId.value });
}

async function onDeleteEvent(row) {
    if (!site.value || !row?.id) {
        return;
    }

    if (!window.confirm('Удалить это событие?')) {
        return;
    }

    deletingEventId.value = row.id;

    try {
        await deleteSiteEvent(site.value.id, row.id);
        eventRows.value = eventRows.value.filter((item) => item.id !== row.id);

        if (editingEventId.value === row.id) {
            resetEventForm();
            activeEventTab.value = eventRows.value.length ? 'saved' : 'new';
        } else {
            syncEventTab();
        }

        toast.show({ ok: true, message: 'Событие удалено.' });
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || 'Не удалось удалить событие',
        });
    } finally {
        deletingEventId.value = null;
    }
}

async function loadMetrics() {
    if (!site.value || !period.from || !period.to) {
        return;
    }

    metricsLoading.value = true;

    try {
        const params = { from: period.from, to: period.to };
        const [analytics, gsc, commits, pagespeed] = await Promise.all([
            getSiteAnalyticsMetrics(site.value.id, params),
            getSiteSearchConsoleMetrics(site.value.id, params),
            getSiteGithubCommits(site.value.id, params),
            getSitePageSpeedMetrics(site.value.id),
        ]);
        analyticsRows.value = analytics;
        gscRows.value = gsc.daily;
        gscQueries.value = gsc.queries;
        gscPages.value = gsc.pages;
        gscDevices.value = gsc.devices;
        gscCountries.value = gsc.countries;
        gscSearchAppearances.value = gsc.search_appearances || [];
        gscSitemaps.value = gsc.sitemaps || [];
        gscUrlInspections.value = gsc.url_inspections || [];
        commitRows.value = commits;
        pagespeedLabRows.value = pagespeed.lab || [];
        pagespeedCruxRows.value = pagespeed.crux || [];
    } catch {
        analyticsRows.value = [];
        gscRows.value = [];
        gscQueries.value = [];
        gscPages.value = [];
        gscDevices.value = [];
        gscCountries.value = [];
        gscSearchAppearances.value = [];
        gscSitemaps.value = [];
        gscUrlInspections.value = [];
        commitRows.value = [];
        pagespeedLabRows.value = [];
        pagespeedCruxRows.value = [];
    } finally {
        metricsLoading.value = false;
    }
}

async function onPeriodChange() {
    if (!period.from || !period.to || period.from > period.to) {
        return;
    }

    await Promise.all([
        loadMetrics(),
        loadEvents(),
    ]);
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

async function openPagespeedModal() {
    pagespeedForm.strategy = pagespeedIntegration.value?.strategy || 'mobile';
    pagespeedModalOpen.value = true;
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
        pagespeedIntegration.value = siteData.pagespeed_integration || null;
        form.ga4_property_id = integration.value?.ga4_property_id || '';
        form.gsc_site_url = integration.value?.gsc_site_url || '';
        githubForm.repository_full_name = githubIntegration.value?.repository_full_name || '';
        githubForm.repository_id = githubIntegration.value?.repository_id || null;
        githubForm.default_branch = githubIntegration.value?.default_branch || '';
        pagespeedForm.strategy = pagespeedIntegration.value?.strategy || 'mobile';

        preferConnectedMetricsTab();
        await Promise.all([
            loadMetrics(),
            loadEvents(),
        ]);
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
        pagespeedIntegration.value = null;
        form.ga4_property_id = '';
        form.gsc_site_url = '';
        pagespeedForm.strategy = 'mobile';
        pagespeedLabRows.value = [];
        pagespeedCruxRows.value = [];
        ga4Properties.value = [];
        gscSites.value = [];
        gaModalOpen.value = false;
        gscModalOpen.value = false;
        pagespeedModalOpen.value = false;
        preferConnectedMetricsTab();
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

async function onSavePagespeedIntegration() {
    busy.value = true;

    try {
        pagespeedIntegration.value = await upsertSitePageSpeedIntegration(site.value.id, {
            strategy: pagespeedForm.strategy,
        });
        toast.show({
            ok: true,
            message: 'PageSpeed Insights подключён к сайту.',
        });
        pagespeedModalOpen.value = false;
        preferConnectedMetricsTab();
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || 'Не удалось сохранить подключение',
        });
    } finally {
        busy.value = false;
    }
}

async function onDisconnectPagespeedIntegration() {
    if (!window.confirm('Отключить PageSpeed Insights от этого сайта?')) {
        return;
    }

    busy.value = true;

    try {
        await deleteSitePageSpeedIntegration(site.value.id);
        pagespeedIntegration.value = null;
        pagespeedForm.strategy = 'mobile';
        pagespeedLabRows.value = [];
        pagespeedCruxRows.value = [];
        pagespeedModalOpen.value = false;
        preferConnectedMetricsTab();
        toast.show({ ok: true, message: 'PageSpeed Insights отключён от сайта.' });
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || 'Не удалось отключить PageSpeed',
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

    const basePayload = {
        from: period.from,
        to: period.to,
    };
    const errors = [];
    let syncedAny = false;

    try {
        if ((gaConnected.value || gscConnected.value) && selectedGoogleSyncMetrics.value.length) {
            try {
                const result = await syncSiteGoogleIntegration(site.value.id, {
                    ...basePayload,
                    metrics: selectedGoogleSyncMetrics.value,
                    limits: selectedGoogleSyncLimits.value,
                });
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

        if (githubConnected.value && selectedGithubSyncMetrics.value.length) {
            try {
                const result = await syncSiteGithubIntegration(site.value.id, {
                    ...basePayload,
                    metrics: selectedGithubSyncMetrics.value,
                });
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

        if (pagespeedConnected.value && selectedPageSpeedSyncMetrics.value.length) {
            try {
                const result = await syncSitePageSpeedIntegration(site.value.id, {
                    metrics: selectedPageSpeedSyncMetrics.value,
                });
                if (result.data) {
                    pagespeedIntegration.value = result.data;
                }
                syncedAny = true;
            } catch (e) {
                if (e.response?.data?.data) {
                    pagespeedIntegration.value = e.response.data.data;
                }
                errors.push(e.response?.data?.message || 'Не удалось загрузить PageSpeed / CrUX');
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
