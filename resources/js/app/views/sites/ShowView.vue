<template>
    <div class="page-app-sites page-app-sites--show">
        <div class="page-app-sites__header">
            <div class="page-app-sites__header-main">
                <img
                    v-if="site?.favicon_url"
                    class="page-app-sites__favicon"
                    :src="site.favicon_url"
                    alt=""
                    width="32"
                    height="32"
                >
                <div>
                    <h1 class="page-app-sites__title">{{ site?.name || t('sites.show.fallbackTitle') }}</h1>
                    <p v-if="site" class="page-app-sites__lede">
                        <a :href="site.url" target="_blank" rel="noopener noreferrer">{{ site.url }}</a>
                    </p>
                </div>
            </div>
            <div class="page-app-sites__header-actions">
                <RouterLink
                    v-if="site"
                    class="btn btn-secondary"
                    :to="{ name: 'sites.edit', params: { id: site.id } }"
                >
                    <FontAwesomeIcon :icon="['fas', 'pen']" aria-hidden="true" />
                    <span>{{ t('common.edit') }}</span>
                </RouterLink>
                <RouterLink class="btn btn-secondary" :to="{ name: 'sites.index' }">
                    <FontAwesomeIcon :icon="['fas', 'arrow-left']" aria-hidden="true" />
                    <span>{{ t('common.backToList') }}</span>
                </RouterLink>
            </div>
        </div>

        <AppLoader
            v-if="loading"
            block
            :label="t('common.loading')"
        />
        <div v-else-if="error" class="alert alert-danger py-2">{{ error }}</div>

        <template v-else-if="site">
            <section class="page-app-sites__integrations" :aria-label="t('sites.show.integrationsAria')">
                <SiteIntegrationCard
                    title="Google Analytics"
                    logo="/images/integrations/google-analytics.svg"
                    :connected="gaConnected"
                    :detail="gaDetail"
                    :empty-detail="t('sites.show.integrations.gaEmpty')"
                    :disabled="busy"
                    @configure="openGaModal"
                />
                <SiteIntegrationCard
                    title="Google Search Console"
                    logo="/images/integrations/google-search-console.svg"
                    :connected="gscConnected"
                    :detail="gscDetail"
                    :empty-detail="t('sites.show.integrations.gscEmpty')"
                    :disabled="busy"
                    @configure="openGscModal"
                />
                <SiteIntegrationCard
                    title="GitHub"
                    logo="/images/integrations/github.svg"
                    :connected="githubConnected"
                    :detail="githubDetail"
                    :empty-detail="t('sites.show.integrations.githubEmpty')"
                    :disabled="busy"
                    @configure="openGithubModal"
                />
                <SiteIntegrationCard
                    title="Chrome UX Report"
                    logo="/images/integrations/pagespeed.svg"
                    :connected="pagespeedConnected"
                    :detail="pagespeedDetail"
                    :empty-detail="t('sites.show.integrations.pagespeedEmpty')"
                    :disabled="busy"
                    @configure="openPagespeedModal"
                />
            </section>

            <div
                class="page-app-sites__view-switch"
                role="tablist"
                :aria-label="t('sites.show.sectionsAria')"
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
                    {{ defLabel(tab) }}
                </button>
            </div>

            <div
                v-show="activeSiteView === 'website'"
                id="site-view-pane-website"
                role="tabpanel"
                aria-labelledby="site-view-tab-website"
            >
                <section
                    class="page-app-sites__panel page-app-sites__website"
                    :aria-label="t('sites.show.website.aria')"
                >
                    <div class="page-app-sites__panel-head">
                        <h2 class="h5 mb-0">{{ t('sites.show.tabs.website') }}</h2>
                        <button
                            type="button"
                            class="btn btn-secondary btn-sm"
                            :disabled="busy || webDataRefreshing || isWebDataPending"
                            @click="onRefreshWebData"
                        >
                            <AppLoader
                                v-if="webDataRefreshing || isWebDataPending"
                                size="sm"
                            />
                            <span>
                                {{
                                    webDataRefreshing || isWebDataPending
                                        ? t('sites.show.website.refreshing')
                                        : t('sites.show.website.refresh')
                                }}
                            </span>
                        </button>
                    </div>

                    <p class="text-muted small mb-3">
                        {{ t('sites.show.website.description') }}
                    </p>

                    <div
                        v-if="site.web_data_error && site.web_data_status === 'failed'"
                        class="alert alert-danger py-2 mb-3"
                    >
                        {{ site.web_data_error }}
                    </div>

                    <AppLoader
                        v-if="isWebDataPending"
                        block
                        :label="t('sites.show.website.collecting')"
                    />

                    <p
                        v-else-if="!hasWebsiteData"
                        class="page-app-sites__empty text-muted mb-0"
                    >
                        {{ t('sites.show.website.empty') }}
                    </p>

                    <template v-else>
                        <div class="page-app-sites__website-summary">
                            <img
                                v-if="site.favicon_url"
                                class="page-app-sites__website-favicon"
                                :src="site.favicon_url"
                                alt=""
                                width="48"
                                height="48"
                            >
                            <div class="page-app-sites__website-summary-text">
                                <p class="page-app-sites__website-title mb-1">
                                    {{ site.page_title || site.name }}
                                </p>
                                <p
                                    v-if="site.meta_description"
                                    class="page-app-sites__website-description mb-0"
                                >
                                    {{ site.meta_description }}
                                </p>
                            </div>
                        </div>

                        <dl class="page-app-sites__website-meta">
                            <div
                                v-for="row in websiteMetaRows"
                                :key="row.key"
                                class="page-app-sites__website-meta-row"
                            >
                                <dt>{{ row.label }}</dt>
                                <dd>
                                    <a
                                        v-if="row.href"
                                        :href="row.href"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >{{ row.value }}</a>
                                    <template v-else>{{ row.value }}</template>
                                </dd>
                            </div>
                        </dl>

                        <SiteWebsiteAudit :audit="site.site_audit" />

                        <div class="page-app-sites__website-robots">
                            <h3 class="page-app-sites__website-robots-title">
                                {{ t('sites.show.website.robotsTitle') }}
                            </h3>
                            <pre
                                v-if="site.robots_txt"
                                class="page-app-sites__website-robots-body"
                            >{{ site.robots_txt }}</pre>
                            <p
                                v-else
                                class="text-muted small mb-0"
                            >
                                {{ t('sites.show.website.robotsEmpty') }}
                            </p>
                        </div>

                        <p
                            v-if="site.web_data_fetched_at"
                            class="page-app-sites__website-fetched text-muted small mb-0"
                        >
                            {{ t('sites.show.website.fetchedAt', { date: formatWebsiteFetchedAt(site.web_data_fetched_at) }) }}
                        </p>
                    </template>
                </section>
            </div>

            <div
                v-show="activeSiteView === 'data'"
                id="site-view-pane-data"
                class="page-app-sites__process-host"
                role="tabpanel"
                aria-labelledby="site-view-tab-data"
            >
                <AppBlockProcessOverlay
                    :active="syncProcessActive"
                    :title="t('sites.process.sync.title')"
                    :message="syncProcessMessage"
                />
                <section
                    v-if="gaConnected || gscConnected || githubConnected || pagespeedConnected"
                    class="page-app-sites__panel page-app-sites__period mb-4"
                >
                    <div class="page-app-sites__panel-head">
                        <h2 class="h5 mb-0">{{ t('sites.show.sync.title') }}</h2>
                    </div>
                    <p class="text-muted small mb-3">
                        {{ t('sites.show.sync.description') }}
                    </p>
                    <div class="page-app-sites__period-row">
                        <div class="page-app-sites__period-field">
                            <label class="form-label" for="metrics-from">{{ t('sites.show.sync.from') }}</label>
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
                            <label class="form-label" for="metrics-to">{{ t('sites.show.sync.to') }}</label>
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
                    </div>

                    <div
                        v-if="availableSyncMetricGroups.length"
                        class="page-app-sites__sync-metrics"
                    >
                        <div
                            v-for="group in availableSyncMetricGroups"
                            :key="group.id"
                            class="page-app-sites__sync-metrics-group"
                            :class="{ 'is-expanded': isSyncMetricGroupExpanded(group.id) }"
                        >
                            <div class="page-app-sites__sync-metrics-group-head">
                                <button
                                    type="button"
                                    class="page-app-sites__sync-metrics-group-toggle"
                                    :aria-expanded="isSyncMetricGroupExpanded(group.id) ? 'true' : 'false'"
                                    :aria-controls="`sync-metrics-body-${group.id}`"
                                    @click="toggleSyncMetricGroupExpanded(group.id)"
                                >
                                    <FontAwesomeIcon
                                        class="page-app-sites__sync-metrics-group-chevron"
                                        :icon="['fas', 'chevron-down']"
                                        aria-hidden="true"
                                    />
                                    <span class="page-app-sites__sync-metrics-group-title">{{ defLabel(group) }}</span>
                                    <span class="page-app-sites__sync-metrics-group-count">
                                        {{ t('sites.show.sync.selectedCount', { selected: syncMetricGroupSelectedCount(group), total: group.metrics.length }) }}
                                    </span>
                                </button>
                                <button
                                    v-if="isSyncMetricGroupExpanded(group.id)"
                                    type="button"
                                    class="page-app-sites__sync-metrics-group-select"
                                    :disabled="busy"
                                    @click="toggleSyncMetricGroup(group)"
                                >
                                    {{ isSyncMetricGroupFullySelected(group) ? t('sites.show.sync.deselectAll') : t('sites.show.sync.selectAll') }}
                                </button>
                            </div>
                            <div
                                v-show="isSyncMetricGroupExpanded(group.id)"
                                :id="`sync-metrics-body-${group.id}`"
                                class="page-app-sites__sync-metrics-list"
                            >
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
                                            {{ defLabel(metric) }}
                                            <span
                                                v-if="metric.optional"
                                                class="page-app-sites__sync-metric-optional"
                                            >{{ t('sites.show.sync.optional') }}</span>
                                        </label>
                                    </div>
                                    <div
                                        v-if="metric.limitKey && syncMetricSelection[metric.key]"
                                        class="page-app-sites__sync-metric-limit"
                                    >
                                        <label
                                            class="form-label mb-0"
                                            :for="`sync-limit-${metric.limitKey}`"
                                        >{{ t('sites.show.sync.limit') }}</label>
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

                    <div class="page-app-sites__period-actions">
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="busy || !canSyncPeriod"
                            @click="onSyncPeriod"
                        >
                            {{ t('sites.show.sync.submit') }}
                        </button>
                    </div>
                </section>

                <section
                    v-if="gaConnected || gscConnected || githubConnected || pagespeedConnected"
                    class="page-app-sites__panel page-app-sites__sync-status mb-4"
                    :aria-label="t('sites.show.sync.statusTitle')"
                >
                    <div class="page-app-sites__panel-head">
                        <h2 class="h5 mb-0">{{ t('sites.show.sync.statusTitle') }}</h2>
                    </div>

                    <div class="page-app-sites__sync-status-section">
                        <h3 class="page-app-sites__sync-status-title">{{ t('sites.show.sync.lastImport') }}</h3>
                        <ul class="page-app-sites__sync-status-list">
                            <li
                                v-for="item in syncStatusItems"
                                :key="item.key"
                                class="page-app-sites__sync-status-item"
                                :class="{ 'has-error': Boolean(item.error) }"
                            >
                                <div class="page-app-sites__sync-status-item-main">
                                    <span class="page-app-sites__sync-status-label">{{ item.label }}</span>
                                    <span
                                        class="page-app-sites__sync-status-value"
                                        :class="{ 'is-empty': !item.at }"
                                    >
                                        {{ item.at ? formatDateTime(item.at) : t('sites.show.sync.neverImported') }}
                                    </span>
                                </div>
                                <p
                                    v-if="item.error"
                                    class="page-app-sites__sync-status-error"
                                >
                                    {{ item.error }}
                                </p>
                            </li>
                        </ul>
                    </div>

                    <div class="page-app-sites__sync-status-section">
                        <h3 class="page-app-sites__sync-status-title">{{ t('sites.show.sync.importedData') }}</h3>
                        <div
                            v-if="metricsCoverageItems.length"
                            class="table-responsive"
                        >
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ t('sites.show.sync.source') }}</th>
                                        <th>{{ t('sites.show.col.period') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in metricsCoverageItems"
                                        :key="item.key"
                                    >
                                        <td>{{ item.label }}</td>
                                        <td>{{ item.period }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p
                            v-else
                            class="page-app-sites__sync-status-empty"
                        >
                            {{ metricsCoverageLoaded
                                ? t('sites.show.sync.noImported')
                                : t('sites.show.sync.loadingDetails') }}
                        </p>
                    </div>
                </section>

                <section
                    class="page-app-sites__panel page-app-sites__metrics"
                    :aria-label="t('sites.show.tabs.data')"
                >
                    <div
                        class="page-app-sites__view-switch page-app-sites__metrics-tabs"
                        role="tablist"
                        :aria-label="t('sites.show.tabs.data')"
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
                            {{ defLabel(tab) }}
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
                            <AppLoader v-if="metricsLoading" block :label="t('sites.show.data.loadingMetrics')" />
                            <div v-else-if="!gaConnected" class="text-muted">{{ t('sites.show.data.gaNotLinked') }}</div>
                            <div v-else-if="!analyticsRows.length" class="text-muted">{{ t('sites.show.data.noData') }}</div>
                            <div v-else class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ t('sites.show.col.date') }}</th>
                                            <th>{{ t('sites.show.col.sessions') }}</th>
                                            <th>{{ t('sites.show.col.users') }}</th>
                                            <th>{{ t('sites.show.col.newUsers') }}</th>
                                            <th>{{ t('sites.show.col.views') }}</th>
                                            <th>{{ t('sites.show.col.orgSessions') }}</th>
                                            <th>{{ t('sites.show.col.orgUsers') }}</th>
                                            <th>{{ t('sites.show.col.orgNew') }}</th>
                                            <th v-if="hasAnalyticsEngagement">{{ t('sites.show.col.engagedSessions') }}</th>
                                            <th v-if="hasAnalyticsEngagement">{{ t('sites.show.col.engagement') }}</th>
                                            <th v-if="hasAnalyticsEngagement">{{ t('sites.show.col.bounces') }}</th>
                                            <th v-if="hasAnalyticsEngagement">{{ t('sites.show.col.avgDuration') }}</th>
                                            <th v-if="hasAnalyticsEngagement">{{ t('sites.show.tabs.events') }}</th>
                                            <th v-if="hasAnalyticsEngagement">{{ t('sites.show.col.orgEngaged') }}</th>
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
                            <AppLoader v-if="metricsLoading" block :label="t('sites.show.data.loadingMetrics')" />
                            <div v-else-if="!gscConnected" class="text-muted">{{ t('sites.show.data.gscNotLinked') }}</div>
                            <div v-else-if="!hasGscMetrics" class="text-muted">{{ t('sites.show.data.noData') }}</div>
                            <div v-else class="d-flex flex-column gap-4">
                                <div v-if="gscRows.length">
                                    <h3 class="h6 mb-2">{{ t('sites.show.gsc.byDay') }}</h3>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>{{ t('sites.show.col.date') }}</th>
                                                    <th>{{ t('sites.show.col.clicks') }}</th>
                                                    <th>{{ t('sites.show.col.impressions') }}</th>
                                                    <th>CTR</th>
                                                    <th>{{ t('sites.show.col.position') }}</th>
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
                                    <h3 class="h6 mb-2">{{ t('sites.show.gsc.topQueries') }}</h3>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>{{ t('sites.show.col.query') }}</th>
                                                    <th>{{ t('sites.show.col.clicks') }}</th>
                                                    <th>{{ t('sites.show.col.impressions') }}</th>
                                                    <th>CTR</th>
                                                    <th>{{ t('sites.show.col.position') }}</th>
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
                                    <h3 class="h6 mb-2">{{ t('sites.show.gsc.topPages') }}</h3>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>{{ t('sites.show.col.page') }}</th>
                                                    <th>{{ t('sites.show.col.clicks') }}</th>
                                                    <th>{{ t('sites.show.col.impressions') }}</th>
                                                    <th>CTR</th>
                                                    <th>{{ t('sites.show.col.position') }}</th>
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
                                    <h3 class="h6 mb-2">{{ t('sites.show.gsc.devices') }}</h3>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>{{ t('sites.show.col.device') }}</th>
                                                    <th>{{ t('sites.show.col.clicks') }}</th>
                                                    <th>{{ t('sites.show.col.impressions') }}</th>
                                                    <th>CTR</th>
                                                    <th>{{ t('sites.show.col.position') }}</th>
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
                                    <h3 class="h6 mb-2">{{ t('sites.show.gsc.countries') }}</h3>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>{{ t('sites.show.col.country') }}</th>
                                                    <th>{{ t('sites.show.col.clicks') }}</th>
                                                    <th>{{ t('sites.show.col.impressions') }}</th>
                                                    <th>CTR</th>
                                                    <th>{{ t('sites.show.col.position') }}</th>
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
                                    <h3 class="h6 mb-2">{{ t('sites.show.gsc.searchAppearances') }}</h3>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>{{ t('sites.show.col.type') }}</th>
                                                    <th>{{ t('sites.show.col.clicks') }}</th>
                                                    <th>{{ t('sites.show.col.impressions') }}</th>
                                                    <th>CTR</th>
                                                    <th>{{ t('sites.show.col.position') }}</th>
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
                                                    <th>{{ t('sites.show.col.type') }}</th>
                                                    <th>{{ t('sites.show.col.errors') }}</th>
                                                    <th>{{ t('sites.show.col.warnings') }}</th>
                                                    <th>{{ t('sites.show.col.status') }}</th>
                                                    <th>{{ t('sites.show.col.downloaded') }}</th>
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
                                                        <span v-if="row.is_pending">{{ t('sites.show.gsc.sitemapPending') }}</span>
                                                        <span v-else-if="row.is_sitemaps_index">{{ t('sites.show.gsc.sitemapIndex') }}</span>
                                                        <span v-else>{{ t('sites.show.gsc.sitemapReady') }}</span>
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
                                                    <th>{{ t('sites.show.col.verdict') }}</th>
                                                    <th>{{ t('sites.show.col.fetch') }}</th>
                                                    <th>{{ t('sites.show.col.indexing') }}</th>
                                                    <th>{{ t('sites.show.col.coverage') }}</th>
                                                    <th>{{ t('sites.show.col.lastCrawl') }}</th>
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
                                {{ t('sites.show.github.repository', { name: githubIntegration.repository_full_name }) }}
                            </p>
                            <AppLoader v-if="metricsLoading" block :label="t('sites.show.github.loadingCommits')" />
                            <div v-else-if="!githubConnected" class="text-muted">{{ t('sites.show.github.notLinked') }}</div>
                            <div v-else-if="!commitRows.length" class="text-muted">{{ t('sites.show.github.noCommits') }}</div>
                            <div v-else class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>SHA</th>
                                            <th>{{ t('sites.show.col.message') }}</th>
                                            <th>{{ t('sites.show.col.author') }}</th>
                                            <th>{{ t('sites.show.col.date') }}</th>
                                            <th class="text-end">{{ t('sites.show.col.actions') }}</th>
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
                                            <td class="text-end text-nowrap">
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-secondary btn-sm"
                                                    :disabled="busy || commitFilesBusyId === row.id"
                                                    @click="onCommitFilesAction(row)"
                                                >
                                                    <span
                                                        v-if="commitFilesBusyId === row.id"
                                                        class="spinner-border spinner-border-sm me-1"
                                                        aria-hidden="true"
                                                    />
                                                    {{
                                                        commitFilesBusyId === row.id
                                                            ? t('sites.show.github.fetchingChanges')
                                                            : row.has_files
                                                                ? t('sites.show.github.viewChanges')
                                                                : t('sites.show.github.fetchChanges')
                                                    }}
                                                </button>
                                            </td>
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
                                {{ t('sites.show.pagespeed.strategyLine', { value: pagespeedIntegration.strategy_label }) }}
                                <template v-if="pagespeedPageUrlsLabel">
                                    · URL: {{ pagespeedPageUrlsLabel }}
                                </template>
                            </p>
                            <AppLoader v-if="metricsLoading" block :label="t('sites.show.pagespeed.loadingCrux')" />
                            <div v-else-if="!pagespeedConnected" class="text-muted">
                                {{ t('sites.show.integrations.pagespeedEmpty') }}
                            </div>
                            <template v-else>
                                <h3 class="h6 mb-2">Lab (Lighthouse)</h3>
                                <div v-if="!pagespeedLabRows.length" class="text-muted mb-4">{{ t('sites.show.pagespeed.noLab') }}</div>
                                <div v-else class="table-responsive mb-4">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>URL</th>
                                                <th>{{ t('sites.show.col.strategy') }}</th>
                                                <th>Perf</th>
                                                <th>A11y</th>
                                                <th>BP</th>
                                                <th>SEO</th>
                                                <th>LCP</th>
                                                <th>INP</th>
                                                <th>CLS</th>
                                                <th>FCP</th>
                                                <th>TTFB</th>
                                                <th>TBT</th>
                                                <th>SI</th>
                                                <th>{{ t('sites.show.col.loaded') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(row, index) in pagespeedLabRows"
                                                :key="`lab-${row.url}-${row.strategy}-${row.fetched_at}-${index}`"
                                            >
                                                <td class="text-break">{{ row.url || '—' }}</td>
                                                <td>{{ formatPagespeedStrategy(row.strategy) }}</td>
                                                <td>{{ formatPagespeedScore(row.performance_score) }}</td>
                                                <td>{{ formatPagespeedScore(row.accessibility_score) }}</td>
                                                <td>{{ formatPagespeedScore(row.best_practices_score) }}</td>
                                                <td>{{ formatPagespeedScore(row.seo_score) }}</td>
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

                                <h3 class="h6 mb-2">{{ t('sites.show.pagespeed.cruxTitle') }}</h3>
                                <div v-if="!pagespeedCruxRows.length" class="text-muted">{{ t('sites.show.pagespeed.noCrux') }}</div>
                                <div v-else class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Scope</th>
                                                <th>URL</th>
                                                <th>Form factor</th>
                                                <th>{{ t('sites.show.col.category') }}</th>
                                                <th>LCP p75</th>
                                                <th>INP p75</th>
                                                <th>CLS p75</th>
                                                <th>FCP p75</th>
                                                <th>TTFB p75</th>
                                                <th>{{ t('sites.show.col.period') }}</th>
                                                <th>{{ t('sites.show.col.loaded') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(row, index) in pagespeedCruxRows"
                                                :key="`crux-${row.scope}-${row.url}-${row.form_factor}-${row.fetched_at}-${index}`"
                                            >
                                                <td>{{ formatCruxScope(row.scope) }}</td>
                                                <td class="text-break">{{ row.url || '—' }}</td>
                                                <td>{{ formatCruxFormFactor(row.form_factor) }}</td>
                                                <td>{{ formatCruxOverallCategory(row.overall_category) }}</td>
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
                    :aria-label="t('sites.show.events.aria')"
                >
                    <div class="page-app-sites__panel-head">
                        <h2 class="h5 mb-0">{{ t('sites.show.tabs.events') }}</h2>
                    </div>

                    <template v-if="isEventEditing">
                        <div class="page-app-sites__event-actions mb-3">
                            <button
                                type="button"
                                class="btn btn-secondary"
                                :disabled="eventSaving || Boolean(deletingEventId)"
                                @click="onBackFromEventEdit"
                            >
                                {{ t('common.back') }}
                            </button>
                        </div>

                        <p class="text-muted small mb-3">
                            {{ t('sites.show.events.editHint') }}
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
                                    <label class="form-label" for="event-edit-occurred-on">{{ t('sites.show.field.date') }}</label>
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
                                    <label class="form-label" for="event-edit-title">{{ t('sites.show.field.title') }}</label>
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
                                    <label class="form-label" for="event-edit-url">{{ t('common.link') }}</label>
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
                                    <label class="form-label" for="event-edit-description">{{ t('sites.show.field.description') }}</label>
                                    <textarea
                                        id="event-edit-description"
                                        v-model="eventForm.description"
                                        class="form-control"
                                        rows="3"
                                        maxlength="5000"
                                        :placeholder="t('sites.show.events.descriptionPlaceholder')"
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
                                        {{ eventSaving ? t('common.saving') : t('common.save') }}
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    :disabled="busy || eventSaving || Boolean(deletingEventId)"
                                    @click="onBackFromEventEdit"
                                >
                                    {{ t('common.cancel') }}
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
                                        {{ deletingEventId ? t('common.deleting') : t('common.delete') }}
                                    </span>
                                </button>
                            </div>
                        </form>
                    </template>

                    <template v-else>
                        <AppLoader
                            v-if="!eventsLoaded && eventsLoading"
                            block
                            :label="t('sites.show.events.loading')"
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
                                :aria-label="t('sites.show.events.sectionsAria')"
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
                                    {{ defLabel(tab) }}
                                </button>
                            </div>

                            <div
                                v-show="activeEventTab === 'saved'"
                                id="event-pane-saved"
                                role="tabpanel"
                                :aria-labelledby="showEventTabs ? 'event-tab-saved' : undefined"
                            >
                                <p class="text-muted small mb-3">
                                    {{ t('sites.show.events.description') }}
                                </p>

                                <AppLoader
                                    v-if="eventsLoading"
                                    block
                                    :label="t('sites.show.events.loading')"
                                />
                                <p
                                    v-else-if="!eventRows.length"
                                    class="text-muted small mb-0"
                                >
                                    {{ t('sites.show.events.empty') }}
                                </p>
                                <div
                                    v-else
                                    class="page-app-sites__event-list"
                                    role="list"
                                >
                                    <button
                                        v-for="row in eventRows"
                                        :key="row.id"
                                        type="button"
                                        class="page-app-sites__event-card page-app-sites__event-card--wide"
                                        role="listitem"
                                        :disabled="busy || eventSaving"
                                        @click="onEventRowClick(row)"
                                    >
                                        <span class="page-app-sites__event-card-head">
                                            <span class="page-app-sites__event-card-title">
                                                {{ row.title }}
                                            </span>
                                            <span class="page-app-sites__event-card-date">
                                                {{ formatDate(row.occurred_on) }}
                                            </span>
                                        </span>
                                        <span
                                            v-if="row.description"
                                            class="page-app-sites__event-card-meta"
                                        >
                                            {{ row.description }}
                                        </span>
                                        <a
                                            v-if="row.url"
                                            class="page-app-sites__event-link"
                                            :href="row.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            @click.stop
                                        >
                                            {{ row.url }}
                                        </a>
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
                                    {{ t('sites.show.events.example') }}
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
                                            <label class="form-label" for="event-occurred-on">{{ t('sites.show.field.date') }}</label>
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
                                            <label class="form-label" for="event-title">{{ t('sites.show.field.title') }}</label>
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
                                            <label class="form-label" for="event-url">{{ t('common.link') }}</label>
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
                                            <label class="form-label" for="event-description">{{ t('sites.show.field.description') }}</label>
                                            <textarea
                                                id="event-description"
                                                v-model="eventForm.description"
                                                class="form-control"
                                                rows="3"
                                                maxlength="5000"
                                                :placeholder="t('sites.show.events.descriptionPlaceholder')"
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
                                                {{ eventSaving ? t('common.saving') : t('common.add') }}
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
                v-show="activeSiteView === 'documents'"
                id="site-view-pane-documents"
                role="tabpanel"
                aria-labelledby="site-view-tab-documents"
            >
                <section
                    class="page-app-sites__panel page-app-sites__documents"
                    :aria-label="t('sites.show.documents.aria')"
                >
                    <div class="page-app-sites__panel-head">
                        <h2 class="h5 mb-0">{{ t('sites.show.tabs.documents') }}</h2>
                    </div>

                    <template v-if="isDocumentEditing">
                        <div class="page-app-sites__event-actions mb-3">
                            <button
                                type="button"
                                class="btn btn-secondary"
                                :disabled="documentSaving || Boolean(deletingDocumentId)"
                                @click="onBackFromDocumentEdit"
                            >
                                {{ t('common.back') }}
                            </button>
                        </div>

                        <p class="text-muted small mb-3">
                            {{ t('sites.show.documents.editHint') }}
                        </p>

                        <div
                            v-if="documentFormError"
                            class="alert alert-danger py-2 mb-3"
                        >
                            {{ documentFormError }}
                        </div>

                        <form
                            class="page-app-sites__event-form"
                            @submit.prevent="onSubmitDocument"
                        >
                            <div class="page-app-sites__event-form-grid">
                                <div class="page-app-sites__event-field page-app-sites__event-field--title">
                                    <label class="form-label" for="document-edit-title">{{ t('sites.show.field.title') }}</label>
                                    <input
                                        id="document-edit-title"
                                        v-model="documentForm.title"
                                        type="text"
                                        class="form-control"
                                        maxlength="255"
                                        :disabled="busy || documentSaving || Boolean(deletingDocumentId)"
                                        required
                                    >
                                </div>
                                <div class="page-app-sites__event-field page-app-sites__event-field--description">
                                    <label class="form-label" for="document-edit-description">{{ t('sites.show.field.shortDescription') }}</label>
                                    <textarea
                                        id="document-edit-description"
                                        v-model="documentForm.description"
                                        class="form-control"
                                        rows="3"
                                        maxlength="2000"
                                        :placeholder="t('sites.show.documents.descriptionPlaceholder')"
                                        :disabled="busy || documentSaving || Boolean(deletingDocumentId)"
                                    />
                                </div>
                                <div class="page-app-sites__event-field page-app-sites__event-field--file">
                                    <label class="form-label" for="document-edit-file">{{ t('sites.show.field.markdownFile') }}</label>
                                    <input
                                        id="document-edit-file"
                                        ref="documentFileInput"
                                        type="file"
                                        class="form-control"
                                        accept=".md,.txt,text/markdown,text/plain"
                                        :disabled="busy || documentSaving || Boolean(deletingDocumentId)"
                                        @change="onDocumentFileChange"
                                    >
                                    <p
                                        v-if="editingDocumentFilename"
                                        class="form-text mb-0"
                                    >
                                        {{ t('sites.show.documents.currentFile', { name: editingDocumentFilename }) }}
                                    </p>
                                    <p class="form-text mb-0">
                                        {{ t('sites.show.documents.replaceHint') }}
                                    </p>
                                </div>
                            </div>
                            <div class="page-app-sites__event-form-actions">
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    :disabled="busy || documentSaving || Boolean(deletingDocumentId) || !canSaveDocument"
                                >
                                    <AppLoader
                                        v-if="documentSaving"
                                        size="sm"
                                    />
                                    <span>
                                        {{ documentSaving ? t('common.saving') : t('common.save') }}
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    :disabled="busy || documentSaving || Boolean(deletingDocumentId)"
                                    @click="onBackFromDocumentEdit"
                                >
                                    {{ t('common.cancel') }}
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-outline-danger"
                                    :disabled="busy || documentSaving || Boolean(deletingDocumentId)"
                                    @click="onDeleteEditingDocument"
                                >
                                    <AppLoader
                                        v-if="deletingDocumentId"
                                        size="sm"
                                    />
                                    <span>
                                        {{ deletingDocumentId ? t('common.deleting') : t('common.delete') }}
                                    </span>
                                </button>
                            </div>
                        </form>
                    </template>

                    <template v-else>
                        <AppLoader
                            v-if="!documentsLoaded && documentsLoading"
                            block
                            :label="t('sites.show.documents.loading')"
                        />

                        <template v-else>
                            <div
                                v-if="documentsError"
                                class="alert alert-danger py-2 mb-3"
                            >
                                {{ documentsError }}
                            </div>

                            <div
                                v-if="showDocumentTabs"
                                class="page-app-sites__view-switch page-app-sites__event-tabs"
                                role="tablist"
                                :aria-label="t('sites.show.documents.sectionsAria')"
                            >
                                <button
                                    v-for="tab in documentTabs"
                                    :id="`document-tab-${tab.id}`"
                                    :key="tab.id"
                                    type="button"
                                    class="page-app-sites__view-switch-btn"
                                    :class="{ 'is-active': activeDocumentTab === tab.id }"
                                    role="tab"
                                    :aria-selected="activeDocumentTab === tab.id"
                                    :aria-controls="`document-pane-${tab.id}`"
                                    @click="activeDocumentTab = tab.id"
                                >
                                    {{ defLabel(tab) }}
                                </button>
                            </div>

                            <div
                                v-show="activeDocumentTab === 'saved'"
                                id="document-pane-saved"
                                role="tabpanel"
                                :aria-labelledby="showDocumentTabs ? 'document-tab-saved' : undefined"
                            >
                                <p class="text-muted small mb-3">
                                    {{ t('sites.show.documents.description') }}
                                </p>

                                <AppLoader
                                    v-if="documentsLoading"
                                    block
                                    :label="t('sites.show.documents.loading')"
                                />
                                <p
                                    v-else-if="!documentRows.length"
                                    class="text-muted small mb-0"
                                >
                                    {{ t('sites.show.documents.empty') }}
                                </p>
                                <div
                                    v-else
                                    class="page-app-sites__event-cards"
                                    role="list"
                                >
                                    <button
                                        v-for="row in documentRows"
                                        :key="row.id"
                                        type="button"
                                        class="page-app-sites__event-card"
                                        role="listitem"
                                        :disabled="busy || documentSaving"
                                        @click="startEditDocument(row)"
                                    >
                                        <span class="page-app-sites__event-card-title">
                                            {{ row.title }}
                                        </span>
                                        <span
                                            v-if="row.description"
                                            class="page-app-sites__event-card-meta"
                                        >
                                            {{ documentCardDescription(row) }}
                                        </span>
                                        <span class="page-app-sites__event-card-date">
                                            {{ row.original_filename || 'Markdown' }}
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <div
                                v-show="activeDocumentTab === 'new'"
                                id="document-pane-new"
                                role="tabpanel"
                                :aria-labelledby="showDocumentTabs ? 'document-tab-new' : undefined"
                            >
                                <p class="text-muted small mb-3">
                                    {{ t('sites.show.documents.example') }}
                                </p>

                                <div
                                    v-if="documentFormError"
                                    class="alert alert-danger py-2 mb-3"
                                >
                                    {{ documentFormError }}
                                </div>

                                <form
                                    class="page-app-sites__event-form"
                                    @submit.prevent="onSubmitDocument"
                                >
                                    <div class="page-app-sites__event-form-grid">
                                        <div class="page-app-sites__event-field page-app-sites__event-field--title">
                                            <label class="form-label" for="document-title">{{ t('sites.show.field.title') }}</label>
                                            <input
                                                id="document-title"
                                                v-model="documentForm.title"
                                                type="text"
                                                class="form-control"
                                                maxlength="255"
                                                :disabled="busy || documentSaving"
                                                required
                                            >
                                        </div>
                                        <div class="page-app-sites__event-field page-app-sites__event-field--description">
                                            <label class="form-label" for="document-description">{{ t('sites.show.field.shortDescription') }}</label>
                                            <textarea
                                                id="document-description"
                                                v-model="documentForm.description"
                                                class="form-control"
                                                rows="3"
                                                maxlength="2000"
                                                :placeholder="t('sites.show.documents.descriptionPlaceholder')"
                                                :disabled="busy || documentSaving"
                                            />
                                        </div>
                                        <div class="page-app-sites__event-field page-app-sites__event-field--file">
                                            <label class="form-label" for="document-file">{{ t('sites.show.field.markdownFile') }}</label>
                                            <input
                                                id="document-file"
                                                ref="documentFileInput"
                                                type="file"
                                                class="form-control"
                                                accept=".md,.txt,text/markdown,text/plain"
                                                :disabled="busy || documentSaving"
                                                required
                                                @change="onDocumentFileChange"
                                            >
                                            <p class="form-text mb-0">
                                                {{ t('sites.show.documents.allowedFiles') }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="page-app-sites__event-form-actions">
                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                            :disabled="busy || documentSaving || !canSaveDocument"
                                        >
                                            <AppLoader
                                                v-if="documentSaving"
                                                size="sm"
                                            />
                                            <span>
                                                {{ documentSaving ? t('common.saving') : t('common.add') }}
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
                    :aria-label="t('sites.show.tabs.aiReport')"
                >
                    <div class="page-app-sites__panel-head">
                        <h2 class="h5 mb-0">{{ t('sites.show.tabs.aiReport') }}</h2>
                        <div
                            v-if="isAiReportOpen"
                            class="page-app-sites__ai-report-toolbar"
                        >
                            <button
                                type="button"
                                class="btn btn-outline-secondary btn-sm page-app-sites__ai-report-prompt-btn"
                                :disabled="aiReportLoading || !aiReportPrompt"
                                @click="aiReportPromptOpen = true"
                            >
                                <FontAwesomeIcon
                                    :icon="['fas', 'file-lines']"
                                    aria-hidden="true"
                                />
                                <span>{{ t('sites.show.aiReport.showPrompt') }}</span>
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-primary btn-sm page-app-sites__ai-report-share-btn"
                                :disabled="aiReportLoading || !selectedAiReportId || aiReportSharingSaving"
                                @click="openAiReportSharingModal"
                            >
                                <FontAwesomeIcon
                                    :icon="['fas', 'share-nodes']"
                                    aria-hidden="true"
                                />
                                <span>{{ t('sites.show.aiReport.share') }}</span>
                            </button>
                        </div>
                    </div>

                    <template v-if="isAiReportOpen">
                        <div class="page-app-sites__ai-report-actions mb-3">
                            <button
                                type="button"
                                class="btn btn-secondary"
                                :disabled="aiReportLoading || aiReportSharingSaving"
                                @click="onBackFromAiReport"
                            >
                                {{ t('common.back') }}
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
                            :label="t('sites.show.aiReport.loadingReport')"
                        />

                        <template v-else-if="aiReportReply">
                            <div class="page-app-sites__ai-report-reply">
                                <div class="page-app-sites__ai-report-reply-head">
                                    <h3 class="page-app-sites__ai-report-reply-title">{{ t('sites.show.aiReport.result') }}</h3>
                                    <span
                                        v-if="aiReportModel"
                                        class="page-app-sites__ai-report-reply-meta"
                                    >
                                        {{ aiReportModel }}
                                    </span>
                                </div>
                                <div class="page-app-sites__ai-report-reply-body">
                                    <AppAiReportBody
                                        :source="aiReportReply"
                                        :charts="aiReportCharts"
                                    />
                                </div>
                            </div>

                            <div
                                v-if="showAiReportStats"
                                class="page-app-sites__ai-report-usage mt-3"
                                :aria-label="t('sites.show.aiReport.stats')"
                            >
                                <h3 class="page-app-sites__ai-report-usage-title">
                                    {{ t('sites.show.aiReport.stats') }}
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
                            :label="t('sites.show.aiReport.loadingReports')"
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
                                :aria-label="t('sites.show.aiReport.sectionsAria')"
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
                                    {{ defLabel(tab) }}
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
                                    :label="t('sites.show.aiReport.loadingReports')"
                                />
                                <p
                                    v-else-if="!aiReports.length"
                                    class="text-muted small mb-0"
                                >
                                    {{ t('sites.show.aiReport.empty') }}
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
                                class="page-app-sites__process-host"
                                role="tabpanel"
                                :aria-labelledby="showAiReportTabs ? 'ai-report-tab-new' : undefined"
                            >
                                <AppBlockProcessOverlay
                                    :active="aiReportProcessActive"
                                    :title="t('sites.process.aiReport.title')"
                                    :message="aiReportProcessMessage"
                                />
                                <p class="text-muted small mb-3">
                                    {{ t('sites.show.aiReport.newHint', { dataTab: t('sites.show.tabs.data'), eventsTab: t('sites.show.tabs.events') }) }}
                                </p>

                                <div class="page-app-sites__period-row mb-3">
                                    <div class="page-app-sites__period-field">
                                        <label class="form-label" for="ai-report-from">{{ t('sites.show.sync.from') }}</label>
                                        <input
                                            id="ai-report-from"
                                            v-model="period.from"
                                            type="date"
                                            class="form-control"
                                            :max="period.to || periodMax"
                                            :disabled="busy || aiReportGenerating || aiReportPreprocessing"
                                            @change="onPeriodChange"
                                        >
                                    </div>
                                    <div class="page-app-sites__period-field">
                                        <label class="form-label" for="ai-report-to">{{ t('sites.show.sync.to') }}</label>
                                        <input
                                            id="ai-report-to"
                                            v-model="period.to"
                                            type="date"
                                            class="form-control"
                                            :min="period.from"
                                            :max="periodMax"
                                            :disabled="busy || aiReportGenerating || aiReportPreprocessing"
                                            @change="onPeriodChange"
                                        >
                                    </div>
                                </div>

                                <AppMetricsCoverageTimeline
                                    class="mb-3"
                                    :items="aiReportCoverageTimelineItems"
                                    :selected-from="period.from"
                                    :selected-to="period.to"
                                    :loading="!metricsCoverageLoaded"
                                    :empty="metricsCoverageEmpty"
                                />

                                <AppLoader
                                    v-if="aiServicesLoading"
                                    block
                                    :label="t('sites.show.aiReport.loadingAiServices')"
                                />
                                <div
                                    v-else-if="aiServicesError"
                                    class="alert alert-danger py-2"
                                >
                                    {{ aiServicesError }}
                                </div>
                                <template v-else>
                                    <div class="page-app-sites__ai-report-field mb-3">
                                        <label class="form-label" for="ai-report-service">{{ t('sites.show.aiReport.aiService') }}</label>
                                        <select
                                            id="ai-report-service"
                                            v-model="aiReportServiceId"
                                            class="form-select"
                                            :disabled="busy || aiReportGenerating || aiReportPreprocessing || !aiServices.length"
                                        >
                                            <option value="">
                                                {{ aiServices.length ? t('sites.show.aiReport.chooseService') : t('sites.show.aiReport.noServices') }}
                                            </option>
                                            <optgroup
                                                v-if="globalAiServices.length"
                                                :label="t('sites.show.aiReport.groupShared')"
                                            >
                                                <option
                                                    v-for="service in globalAiServices"
                                                    :key="service.id"
                                                    :value="String(service.id)"
                                                >
                                                    {{ aiServiceOptionLabel(service) }}
                                                </option>
                                            </optgroup>
                                            <optgroup
                                                v-if="ownAiServices.length"
                                                :label="t('sites.show.aiReport.groupOwn')"
                                            >
                                                <option
                                                    v-for="service in ownAiServices"
                                                    :key="service.id"
                                                    :value="String(service.id)"
                                                >
                                                    {{ aiServiceOptionLabel(service) }}
                                                </option>
                                            </optgroup>
                                        </select>
                                        <p v-if="!aiServices.length" class="form-text mb-0">
                                            {{ t('sites.show.aiReport.addFirst') }}
                                            <RouterLink :to="{ name: 'ai-services.create' }">
                                                {{ t('sites.show.aiReport.addLink') }}
                                            </RouterLink>.
                                        </p>
                                    </div>

                                    <div class="page-app-sites__ai-report-prompt mb-3">
                                        <div class="form-check">
                                            <input
                                                id="ai-report-use-system-prompt"
                                                v-model="aiReportUseSystemPrompt"
                                                class="form-check-input"
                                                type="checkbox"
                                                :disabled="busy || aiReportGenerating || aiReportPreprocessing"
                                            >
                                            <label
                                                class="form-check-label"
                                                for="ai-report-use-system-prompt"
                                            >
                                                {{ t('sites.show.aiReport.useSystemPrompt') }}
                                            </label>
                                        </div>
                                        <p class="form-text mb-0">
                                            {{ t('sites.show.aiReport.useSystemPromptHint') }}
                                        </p>

                                        <div
                                            v-if="!aiReportUseSystemPrompt"
                                            class="page-app-sites__ai-report-prompt-field mt-3"
                                        >
                                            <label
                                                class="form-label"
                                                for="ai-report-custom-prompt"
                                            >{{ t('sites.show.aiReport.customPrompt') }}</label>
                                            <textarea
                                                id="ai-report-custom-prompt"
                                                v-model="aiReportCustomPrompt"
                                                class="form-control"
                                                rows="8"
                                                :placeholder="t('sites.show.aiReport.customPromptPlaceholder')"
                                                :disabled="busy || aiReportGenerating || aiReportPreprocessing"
                                            />
                                            <p class="form-text mb-0">
                                                {{ t('sites.show.aiReport.customPromptHint') }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="page-app-sites__ai-report-actions mb-3">
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary"
                                            :disabled="!canPreprocessAiReport"
                                            @click="onPreprocessAiReport"
                                        >
                                            <AppLoader
                                                v-if="aiReportPreprocessing"
                                                size="sm"
                                            />
                                            <span>
                                                {{ aiReportPreprocessButtonLabel }}
                                            </span>
                                        </button>
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
                                                {{ aiReportGenerateButtonLabel }}
                                            </span>
                                        </button>
                                    </div>

                                    <p class="form-text mb-3">
                                        {{ t('sites.show.aiReport.preprocess.hint') }}
                                    </p>

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
                        {{ t('sites.show.modal.googleAccount') }} <strong>{{ connection.google_account_email }}</strong>
                    </p>
                    <div class="page-app-sites__modal-actions">
                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm"
                            :disabled="busy"
                            @click="onConnectGoogle"
                        >
                            {{ t('sites.show.modal.reconnect') }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline-danger btn-sm"
                            :disabled="busy"
                            @click="onDisconnectGoogle"
                        >
                            {{ t('sites.show.modal.disconnectGoogle') }}
                        </button>
                    </div>
                </template>
                <template v-else>
                    <p class="text-muted mb-3">
                        {{ connection?.needs_reauth
                            ? t('sites.show.modal.googleReauth')
                            : t('sites.show.modal.connectGoogleForGa') }}
                    </p>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="busy"
                        @click="onConnectGoogle"
                    >
                        {{ t('sites.show.modal.connectGoogle') }}
                    </button>
                </template>
            </div>

            <template v-if="connection && !connection.needs_reauth">
                <div v-if="listsError" class="alert alert-danger py-2 mb-3">{{ listsError }}</div>
                <AppLoader v-if="listsLoading" block :label="t('sites.show.modal.loadingProperties')" />
                <template v-else>
                    <label class="form-label" for="ga4-property">Google Analytics property</label>
                    <select
                        id="ga4-property"
                        v-model="form.ga4_property_id"
                        class="form-select mb-3"
                        :disabled="busy"
                    >
                        <option value="">{{ t('sites.show.modal.notSelected') }}</option>
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
                            {{ t('common.save') }}
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
                        {{ t('sites.show.modal.googleAccount') }} <strong>{{ connection.google_account_email }}</strong>
                    </p>
                    <div class="page-app-sites__modal-actions">
                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm"
                            :disabled="busy"
                            @click="onConnectGoogle"
                        >
                            {{ t('sites.show.modal.reconnect') }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline-danger btn-sm"
                            :disabled="busy"
                            @click="onDisconnectGoogle"
                        >
                            {{ t('sites.show.modal.disconnectGoogle') }}
                        </button>
                    </div>
                </template>
                <template v-else>
                    <p class="text-muted mb-3">
                        {{ connection?.needs_reauth
                            ? t('sites.show.modal.googleReauth')
                            : t('sites.show.modal.connectGoogleForGsc') }}
                    </p>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="busy"
                        @click="onConnectGoogle"
                    >
                        {{ t('sites.show.modal.connectGoogle') }}
                    </button>
                </template>
            </div>

            <template v-if="connection && !connection.needs_reauth">
                <div v-if="listsError" class="alert alert-danger py-2 mb-3">{{ listsError }}</div>
                <AppLoader v-if="listsLoading" block :label="t('sites.show.modal.loadingSites')" />
                <template v-else>
                    <label class="form-label" for="gsc-site">{{ t('sites.show.modal.gscSite') }}</label>
                    <select
                        id="gsc-site"
                        v-model="form.gsc_site_url"
                        class="form-select mb-3"
                        :disabled="busy"
                    >
                        <option value="">{{ t('sites.show.modal.notSelected') }}</option>
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
                            {{ t('common.save') }}
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
                        {{ t('sites.show.modal.githubAccount') }}
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
                            {{ t('sites.show.modal.reconnect') }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline-danger btn-sm"
                            :disabled="busy"
                            @click="onDisconnectGithub"
                        >
                            {{ t('sites.show.modal.disconnectGithub') }}
                        </button>
                    </div>
                </template>
                <template v-else>
                    <p class="text-muted mb-3">
                        {{ githubConnection?.needs_reauth
                            ? t('sites.show.modal.githubReauth')
                            : t('sites.show.modal.connectGithubForRepo') }}
                    </p>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="busy"
                        @click="onConnectGithub"
                    >
                        {{ t('sites.show.modal.connectGithub') }}
                    </button>
                </template>
            </div>

            <template v-if="githubConnection && !githubConnection.needs_reauth">
                <div v-if="githubReposError" class="alert alert-danger py-2 mb-3">{{ githubReposError }}</div>
                <AppLoader v-if="githubReposLoading" block :label="t('sites.show.modal.loadingRepos')" />
                <template v-else>
                    <label class="form-label" for="github-repo">{{ t('sites.show.modal.repository') }}</label>
                    <select
                        id="github-repo"
                        v-model="githubForm.repository_full_name"
                        class="form-select mb-2"
                        :disabled="busy"
                        @change="onGithubRepoChange"
                    >
                        <option value="">{{ t('sites.show.modal.notSelected') }}</option>
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
                        {{ t('sites.show.modal.currentlyLinked', { name: githubIntegration.repository_full_name }) }}
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
                            :label="t('sites.show.modal.loadingBranches')"
                        />
                        <template v-else>
                            <label class="form-label" for="github-branch">{{ t('sites.show.modal.branch') }}</label>
                            <select
                                id="github-branch"
                                v-model="githubForm.default_branch"
                                class="form-select mb-2"
                                :disabled="busy || !githubBranches.length"
                            >
                                <option value="">{{ t('sites.show.modal.notSelected') }}</option>
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
                                {{ t('sites.show.modal.currentBranch', { name: githubForm.default_branch }) }}
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
                            {{ t('common.save') }}
                        </button>
                        <button
                            v-if="githubConnected"
                            type="button"
                            class="btn btn-outline-danger"
                            :disabled="busy"
                            @click="onClearGithubIntegration"
                        >
                            {{ t('sites.show.modal.unlinkRepo') }}
                        </button>
                    </div>
                </template>
            </template>
        </AppModal>

        <AppModal
            v-model:open="pagespeedModalOpen"
            title="Chrome UX Report"
            size="md"
            align="start"
            :show-confirm="false"
            :close-on-backdrop="!busy"
        >
            <div class="page-app-sites__modal-account">
                <template v-if="connection && !connection.needs_reauth">
                    <p class="text-muted small mb-2">
                        {{ t('sites.show.modal.googleAccount') }} <strong>{{ connection.google_account_email }}</strong>
                    </p>
                    <div class="page-app-sites__modal-actions">
                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm"
                            :disabled="busy"
                            @click="onConnectGoogle"
                        >
                            {{ t('sites.show.modal.reconnect') }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline-danger btn-sm"
                            :disabled="busy"
                            @click="onDisconnectGoogle"
                        >
                            {{ t('sites.show.modal.disconnectGoogle') }}
                        </button>
                    </div>
                </template>
                <template v-else>
                    <p class="text-muted mb-3">
                        {{ connection?.needs_reauth
                            ? t('sites.show.modal.googleReauth')
                            : t('sites.show.modal.connectGoogleForCrux') }}
                    </p>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="busy"
                        @click="onConnectGoogle"
                    >
                        {{ t('sites.show.modal.connectGoogle') }}
                    </button>
                </template>
            </div>

            <template v-if="connection && !connection.needs_reauth">
                <label class="form-label" for="pagespeed-strategy">{{ t('sites.show.modal.strategy') }}</label>
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
                        {{ defLabel(option) }}
                    </option>
                </select>

                <label class="form-label" for="pagespeed-page-urls">{{ t('sites.show.modal.pageUrls') }}</label>
                <textarea
                    id="pagespeed-page-urls"
                    v-model="pagespeedForm.pageUrlsText"
                    class="form-control mb-2"
                    rows="4"
                    :disabled="busy"
                    placeholder="https://example.com/&#10;https://example.com/catalog&#10;/about"
                />
                <p class="form-text mb-3">
                    {{ t('sites.show.modal.pageUrlsHint') }}
                </p>

                <div class="page-app-sites__modal-actions">
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="busy"
                        @click="onSavePagespeedIntegration"
                    >
                        {{ pagespeedConnected ? t('common.save') : t('sites.show.modal.connectToSite') }}
                    </button>
                    <button
                        v-if="pagespeedConnected"
                        type="button"
                        class="btn btn-outline-danger"
                        :disabled="busy"
                        @click="onDisconnectPagespeedIntegration"
                    >
                        {{ t('sites.show.modal.disconnectFromSite') }}
                    </button>
                </div>
            </template>
        </AppModal>

        <AppModal
            v-model:open="aiReportPromptOpen"
            :title="t('sites.show.aiReport.promptModal.title')"
            align="start"
            size="lg"
            :confirm-label="t('common.close')"
        >
            <p class="text-muted small mb-2">
                {{ t('sites.show.aiReport.promptModal.hint') }}
            </p>
            <p
                v-if="aiReportPromptKindLabel"
                class="page-app-sites__ai-report-prompt-kind mb-3"
            >
                {{ aiReportPromptKindLabel }}
            </p>
            <pre class="page-app-sites__ai-report-saved-prompt-body page-app-sites__ai-report-saved-prompt-body--modal">{{ aiReportPrompt }}</pre>
        </AppModal>

        <AppModal
            v-model:open="aiReportSharingOpen"
            :title="t('sites.show.sharing.title')"
            align="start"
            size="md"
            :show-confirm="false"
            :close-on-backdrop="!aiReportSharingSaving"
        >
            <div class="page-app-sites__ai-report-share">
                <fieldset class="page-app-sites__ai-report-share-options">
                    <legend class="form-label">{{ t('sites.show.sharing.legend') }}</legend>

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
                        <span>{{ defLabel(option) }}</span>
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
                        {{ aiReportSharing.has_password ? t('sites.show.sharing.newPassword') : t('common.password') }}
                    </label>
                    <input
                        id="ai-report-share-password"
                        v-model="aiReportSharingForm.password"
                        type="password"
                        class="form-control"
                        :class="{ 'is-invalid': Boolean(aiReportSharingErrors.password) }"
                        autocomplete="new-password"
                        :placeholder="aiReportSharing.has_password ? t('common.leaveEmptyToKeep') : t('sites.show.sharing.setPassword')"
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
                    >{{ t('common.link') }}</label>
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
                            {{ t('sites.show.sharing.copy') }}
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
                    {{ t('common.cancel') }}
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
                    {{ aiReportSharingSaving ? t('common.saving') : t('common.save') }}
                </button>
            </template>
        </AppModal>

        <AppModal
            v-model:open="aiReportPreprocessModalOpen"
            :title="t('sites.show.aiReport.preprocess.title')"
            :message="t('sites.show.aiReport.preprocess.message')"
            size="md"
            align="start"
            :show-confirm="false"
            :close-on-backdrop="!aiReportPreprocessFetching"
        >
            <div class="page-app-sites__ai-report-preprocess">
                <div
                    v-if="aiReportPreprocessError"
                    class="alert alert-danger py-2"
                >
                    {{ aiReportPreprocessError }}
                </div>

                <div
                    v-else-if="!aiReportPreprocessItems.length"
                    class="text-muted"
                >
                    {{ t('sites.show.aiReport.preprocess.empty') }}
                </div>

                <template v-else>
                    <div class="page-app-sites__ai-report-preprocess-toolbar mb-2">
                        <label class="form-check mb-0">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                :checked="aiReportPreprocessAllSelected"
                                :disabled="aiReportPreprocessFetching"
                                @change="onToggleAllPreprocessItems"
                            >
                            <span class="form-check-label">
                                {{ t('sites.show.aiReport.preprocess.selectAll') }}
                            </span>
                        </label>
                        <span class="text-muted small">
                            {{ t('sites.show.aiReport.preprocess.selectedCount', { count: aiReportPreprocessSelectedCount }) }}
                        </span>
                    </div>

                    <div
                        v-for="group in aiReportPreprocessGroups"
                        :key="group.type"
                        class="page-app-sites__ai-report-preprocess-group"
                    >
                        <h3 class="page-app-sites__ai-report-preprocess-group-title">
                            {{ preprocessTypeLabel(group.type) }}
                        </h3>
                        <ul class="page-app-sites__ai-report-preprocess-list list-unstyled mb-0">
                            <li
                                v-for="item in group.items"
                                :key="item.key"
                                class="page-app-sites__ai-report-preprocess-item"
                            >
                                <label class="form-check mb-0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        :checked="aiReportPreprocessSelectedKeys.includes(item.key)"
                                        :disabled="aiReportPreprocessFetching"
                                        @change="onTogglePreprocessItem(item.key)"
                                    >
                                    <span class="form-check-label">
                                        <span class="page-app-sites__ai-report-preprocess-item-title">
                                            <span>{{ item.title || item.url || item.key }}</span>
                                        </span>
                                        <span
                                            v-if="item.reason"
                                            class="page-app-sites__ai-report-preprocess-item-reason text-muted small"
                                        >
                                            {{ t('sites.show.aiReport.preprocess.reason') }}: {{ item.reason }}
                                        </span>
                                    </span>
                                </label>
                            </li>
                        </ul>
                    </div>

                    <p
                        v-if="aiReportPreprocessFetching"
                        class="text-muted small mb-0 mt-3"
                    >
                        {{ t('sites.show.aiReport.preprocess.fetching') }}
                    </p>
                </template>
            </div>

            <template #actions>
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    :disabled="aiReportPreprocessFetching"
                    @click="aiReportPreprocessModalOpen = false"
                >
                    {{ t('sites.show.aiReport.preprocess.cancel') }}
                </button>
                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="!canConfirmAiReportPreprocess"
                    @click="onConfirmAiReportPreprocess"
                >
                    <AppLoader
                        v-if="aiReportPreprocessFetching"
                        size="sm"
                    />
                    <span>
                        {{
                            aiReportPreprocessFetching
                                ? t('sites.show.aiReport.preprocess.fetching')
                                : t('sites.show.aiReport.preprocess.confirm')
                        }}
                    </span>
                </button>
            </template>
        </AppModal>

        <AppModal
            v-model:open="commitFilesModalOpen"
            :title="commitFilesModalTitle"
            size="lg"
            align="start"
            :show-confirm="true"
            :confirm-label="t('common.close')"
        >
            <AppLoader
                v-if="commitFilesLoading"
                block
                :label="t('sites.show.github.fetchingChanges')"
            />
            <div
                v-else-if="commitFilesError"
                class="alert alert-danger py-2 mb-0"
            >
                {{ commitFilesError }}
            </div>
            <div
                v-else-if="commitFilesDetail"
                class="page-app-sites__commit-files"
            >
                <p
                    v-if="commitFilesDetail.stats"
                    class="text-muted small mb-2"
                >
                    {{
                        t('sites.show.github.changesStats', {
                            additions: commitFilesDetail.stats.additions,
                            deletions: commitFilesDetail.stats.deletions,
                            total: commitFilesDetail.stats.total,
                        })
                    }}
                </p>
                <p
                    v-if="commitFilesDetail.files_incomplete"
                    class="alert alert-warning py-2 small"
                >
                    {{ t('sites.show.github.changesIncomplete') }}
                </p>
                <div
                    v-if="!(commitFilesDetail.files || []).length"
                    class="text-muted"
                >
                    {{ t('sites.show.github.changesEmpty') }}
                </div>
                <div
                    v-else
                    class="table-responsive"
                >
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ t('sites.show.github.colFile') }}</th>
                                <th>{{ t('sites.show.github.colStatus') }}</th>
                                <th class="text-end">{{ t('sites.show.github.colAdditions') }}</th>
                                <th class="text-end">{{ t('sites.show.github.colDeletions') }}</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            <template
                                v-for="file in commitFilesDetail.files"
                                :key="`${file.filename}-${file.status}`"
                            >
                                <tr>
                                    <td class="page-app-sites__commit-file-name">
                                        <span>{{ file.filename }}</span>
                                        <span
                                            v-if="file.previous_filename"
                                            class="text-muted d-block small"
                                        >
                                            ← {{ file.previous_filename }}
                                        </span>
                                    </td>
                                    <td>{{ commitFileStatusLabel(file.status) }}</td>
                                    <td class="text-end text-success">{{ file.additions }}</td>
                                    <td class="text-end text-danger">{{ file.deletions }}</td>
                                    <td class="text-end">
                                        <button
                                            v-if="file.patch"
                                            type="button"
                                            class="btn btn-link btn-sm px-0"
                                            @click="toggleCommitFilePatch(file.filename)"
                                        >
                                            {{
                                                expandedCommitPatches[file.filename]
                                                    ? t('sites.show.github.hidePatch')
                                                    : t('sites.show.github.showPatch')
                                            }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="file.patch && expandedCommitPatches[file.filename]">
                                    <td colspan="5">
                                        <pre class="page-app-sites__commit-patch mb-0">{{ file.patch }}</pre>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </AppModal>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import SiteIntegrationCard from '../../components/SiteIntegrationCard.vue';
import SiteWebsiteAudit from '../../components/SiteWebsiteAudit.vue';
import AppAiReportBody from '../../../shared/components/AppAiReportBody.vue';
import AppBlockProcessOverlay from '../../../shared/components/AppBlockProcessOverlay.vue';
import AppLoader from '../../../shared/components/AppLoader.vue';
import AppMetricsCoverageTimeline from '../../../shared/components/AppMetricsCoverageTimeline.vue';
import AppModal from '../../../shared/components/AppModal.vue';
import { setDynamicBreadcrumbLabel } from '../../../shared/dynamicBreadcrumbLabel';
import { toast } from '../../../shared/toast';
import { useI18n } from '../../../shared/i18n';
import { listAiServices } from '../../api/aiServices';
import {
    disconnectGithub,
    fetchSiteGithubCommitFiles,
    getGithubConnection,
    getSiteGithubCommitFiles,
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
    createSiteDocument,
    createSiteEvent,
    deleteSiteDocument,
    deleteSiteEvent,
    generateSiteAiReport,
    getGoogleConnection,
    getSite,
    getSiteAiReport,
    getSiteAnalyticsMetrics,
    getSiteMetricsCoverage,
    getSiteSearchConsoleMetrics,
    listGa4Properties,
    listGscSites,
    listSiteAiReports,
    listSiteDocuments,
    listSiteEvents,
    preprocessSiteAiReport,
    applySiteAiReportPreprocess,
    refreshSiteWebData,
    startGoogleOAuth,
    syncSiteGoogleIntegration,
    updateSiteAiReportSharing,
    updateSiteDocument,
    updateSiteEvent,
    updateSiteGoogleIntegration,
} from '../../api/sites';

const route = useRoute();
const { t, intlLocale } = useI18n();

/**
 * @param {{ label?: string, labelKey?: string }} definition
 * @returns {string}
 */
function defLabel(definition) {
    return definition.labelKey ? t(definition.labelKey) : definition.label;
}

const site = ref(null);

watch(
    () => site.value?.name,
    (name) => {
        setDynamicBreadcrumbLabel(name || null);
    },
    { immediate: true },
);

onUnmounted(() => {
    setDynamicBreadcrumbLabel(null);
    stopWebDataPolling();
});

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
const commitFilesBusyId = ref(null);
const commitFilesModalOpen = ref(false);
const commitFilesLoading = ref(false);
const commitFilesError = ref('');
const commitFilesDetail = ref(null);
const expandedCommitPatches = ref({});
const pagespeedLabRows = ref([]);
const pagespeedCruxRows = ref([]);
const metricsCoverage = ref(null);
const metricsCoverageLoaded = ref(false);
const periodDefaultsFromCoverageApplied = ref(false);
const eventRows = ref([]);
const documentRows = ref([]);

const loading = ref(true);
const listsLoading = ref(false);
const githubReposLoading = ref(false);
const githubBranchesLoading = ref(false);
const metricsLoading = ref(false);
const eventsLoading = ref(false);
const eventsLoaded = ref(false);
const eventSaving = ref(false);
const deletingEventId = ref(null);
const documentsLoading = ref(false);
const documentsLoaded = ref(false);
const documentSaving = ref(false);
const deletingDocumentId = ref(null);
const busy = ref(false);
const syncProcessActive = ref(false);
const syncProcessMessage = ref('');
const aiReportProcessActive = ref(false);
const error = ref('');
const listsError = ref('');
const githubReposError = ref('');
const githubBranchesError = ref('');
const eventsError = ref('');
const eventFormError = ref('');
const editingEventId = ref(null);
const activeEventTab = ref('saved');
const documentsError = ref('');
const documentFormError = ref('');
const editingDocumentId = ref(null);
const editingDocumentFilename = ref('');
const activeDocumentTab = ref('saved');
const documentFile = ref(null);
const documentFileInput = ref(null);

const gaModalOpen = ref(false);
const gscModalOpen = ref(false);
const githubModalOpen = ref(false);
const pagespeedModalOpen = ref(false);
const activeSiteView = ref('website');
const activeMetricsTab = ref('ga4');
const webDataRefreshing = ref(false);
let webDataPollTimer = null;

const isWebDataPending = computed(() => site.value?.web_data_status === 'pending');

const hasWebsiteData = computed(() => {
    if (!site.value) {
        return false;
    }

    return site.value.web_data_status === 'ready'
        || Boolean(site.value.page_title)
        || Boolean(site.value.meta_description)
        || Boolean(site.value.favicon_url)
        || Boolean(site.value.robots_txt)
        || Boolean(site.value.site_audit);
});

const websiteMetaRows = computed(() => {
    if (!site.value) {
        return [];
    }

    /** @type {Array<{ key: string, label: string, value: string, href?: string }>} */
    const rows = [];

    const pushRow = (key, labelKey, value, href = null) => {
        if (!value) {
            return;
        }

        rows.push({
            key,
            label: t(labelKey),
            value,
            ...(href ? { href } : {}),
        });
    };

    pushRow('page_title', 'sites.show.website.fields.title', site.value.page_title);
    pushRow('meta_description', 'sites.show.website.fields.description', site.value.meta_description);
    pushRow('meta_keywords', 'sites.show.website.fields.keywords', site.value.meta_keywords);
    pushRow('og_title', 'sites.show.website.fields.ogTitle', site.value.og_title);
    pushRow('og_description', 'sites.show.website.fields.ogDescription', site.value.og_description);
    pushRow('og_image_url', 'sites.show.website.fields.ogImage', site.value.og_image_url, site.value.og_image_url);
    pushRow('canonical_url', 'sites.show.website.fields.canonical', site.value.canonical_url, site.value.canonical_url);
    pushRow('html_lang', 'sites.show.website.fields.lang', site.value.html_lang);
    pushRow(
        'favicon_source_url',
        'sites.show.website.fields.faviconSource',
        site.value.favicon_source_url,
        site.value.favicon_source_url,
    );

    return rows;
});

function formatWebsiteFetchedAt(value) {
    try {
        return new Intl.DateTimeFormat(intlLocale.value, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value));
    } catch {
        return value;
    }
}

function stopWebDataPolling() {
    if (webDataPollTimer) {
        clearInterval(webDataPollTimer);
        webDataPollTimer = null;
    }
}

function startWebDataPolling() {
    stopWebDataPolling();

    if (!isWebDataPending.value) {
        return;
    }

    webDataPollTimer = setInterval(async () => {
        if (!site.value || !isWebDataPending.value) {
            stopWebDataPolling();

            return;
        }

        try {
            const siteData = await getSite(site.value.id);
            site.value = {
                ...site.value,
                ...siteData,
                google_integration: siteData.google_integration ?? site.value.google_integration,
                github_integration: siteData.github_integration ?? site.value.github_integration,
                pagespeed_integration: siteData.pagespeed_integration ?? site.value.pagespeed_integration,
            };

            if (siteData.web_data_status !== 'pending') {
                stopWebDataPolling();
            }
        } catch {
            // Keep polling until the user leaves the page.
        }
    }, 3000);
}

async function onRefreshWebData() {
    if (!site.value || webDataRefreshing.value) {
        return;
    }

    webDataRefreshing.value = true;

    try {
        const siteData = await refreshSiteWebData(site.value.id);
        site.value = {
            ...site.value,
            ...siteData,
            google_integration: siteData.google_integration ?? site.value.google_integration,
            github_integration: siteData.github_integration ?? site.value.github_integration,
            pagespeed_integration: siteData.pagespeed_integration ?? site.value.pagespeed_integration,
        };
        startWebDataPolling();
        toast.show({ ok: true, message: t('sites.show.toast.webDataRefreshStarted') });
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || t('sites.show.errors.refreshWebData'),
        });
    } finally {
        webDataRefreshing.value = false;
    }
}

const aiServices = ref([]);
const aiServicesLoading = ref(false);
const aiServicesError = ref('');
const aiServicesLoaded = ref(false);
const aiReportServiceId = ref('');
const aiReportUseSystemPrompt = ref(true);
const aiReportCustomPrompt = ref('');
const aiReportPrompt = ref('');
const aiReportPromptIsSystem = ref(true);
const aiReportPromptOpen = ref(false);

const globalAiServices = computed(() => aiServices.value.filter((service) => service.is_global));
const ownAiServices = computed(() => aiServices.value.filter((service) => !service.is_global));
const aiReportGenerating = ref(false);
const aiReportAttempt = ref(0);
const aiReportLoading = ref(false);
const aiReportError = ref('');
const aiReportPreprocessing = ref(false);
const aiReportPreprocessAttempt = ref(0);
const aiReportPreprocessModalOpen = ref(false);
const aiReportPreprocessItems = ref([]);
const aiReportPreprocessSelectedKeys = ref([]);
const aiReportPreprocessError = ref('');
const aiReportPreprocessFetching = ref(false);
const AI_REPORT_MAX_ATTEMPTS = 5;
const AI_REPORT_PREPROCESS_MAX_ATTEMPTS = 2;
const AI_REPORT_RETRY_DELAY_MS = 2500;
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
    { value: 'private', labelKey: 'sites.show.sharing.private' },
    { value: 'link', labelKey: 'sites.show.sharing.link' },
    { value: 'password', labelKey: 'sites.show.sharing.password' },
];

const siteViewTabs = [
    { id: 'website', labelKey: 'sites.show.tabs.website' },
    { id: 'data', labelKey: 'sites.show.tabs.data' },
    { id: 'events', labelKey: 'sites.show.tabs.events' },
    { id: 'documents', labelKey: 'sites.show.tabs.documents' },
    { id: 'ai-report', labelKey: 'sites.show.tabs.aiReport' },
];

const aiReportTabs = [
    { id: 'saved', labelKey: 'sites.show.tabs.aiSaved' },
    { id: 'new', labelKey: 'sites.show.tabs.aiNew' },
];

const eventTabs = [
    { id: 'saved', labelKey: 'sites.show.tabs.eventsSaved' },
    { id: 'new', labelKey: 'sites.show.tabs.eventsNew' },
];

const documentTabs = [
    { id: 'saved', labelKey: 'sites.show.tabs.documentsSaved' },
    { id: 'new', labelKey: 'sites.show.tabs.documentsNew' },
];

const metricsTabs = [
    { id: 'ga4', label: 'Google Analytics' },
    { id: 'gsc', label: 'Search Console' },
    { id: 'github', label: 'GitHub' },
    { id: 'pagespeed', label: 'Chrome UX Report' },
];

const pagespeedStrategyOptions = [
    { value: 'mobile', label: 'Mobile' },
    { value: 'desktop', label: 'Desktop' },
    { value: 'both', labelKey: 'sites.show.pagespeed.bothStrategies' },
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
    pageUrlsText: '',
});

const eventForm = reactive({
    occurred_on: periodMax,
    title: '',
    description: '',
    url: '',
});

const documentForm = reactive({
    title: '',
    description: '',
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

const metricsCoverageDefs = [
    { key: 'analytics', label: 'Google Analytics', available: () => gaConnected.value },
    { key: 'search_console_daily', labelKey: 'sites.show.sync.groupGscDaily', available: () => gscConnected.value },
    {
        key: 'search_console_dimensions',
        labelKey: 'sites.show.sync.groupGscDimensions',
        available: () => gscConnected.value,
    },
    { key: 'github_commits', label: 'GitHub', available: () => githubConnected.value },
    { key: 'pagespeed_lab', label: 'PageSpeed Lab', available: () => pagespeedConnected.value },
    { key: 'crux', label: 'Chrome UX Report', available: () => pagespeedConnected.value },
];

const metricsCoverageItems = computed(() => (
    metricsCoverageDefs
        .filter((item) => item.available())
        .map((item) => {
            const range = metricsCoverage.value?.[item.key];

            if (!range?.from || !range?.to) {
                return null;
            }

            return {
                key: item.key,
                label: defLabel(item),
                period: formatCoveragePeriod(range.from, range.to),
            };
        })
        .filter(Boolean)
));

const aiReportCoverageTimelineItems = computed(() => (
    metricsCoverageDefs
        .filter((item) => item.available())
        .map((item) => {
            const range = metricsCoverage.value?.[item.key];

            if (!range?.from || !range?.to) {
                return null;
            }

            return {
                key: item.key,
                label: defLabel(item),
                from: range.from,
                to: range.to,
            };
        })
        .filter(Boolean)
));

const metricsCoverageEmpty = computed(() => (
    metricsCoverageLoaded.value
    && metricsCoverageDefs.some((item) => item.available())
    && metricsCoverageItems.value.length === 0
));

const metricsCoverageBounds = computed(() => {
    const items = aiReportCoverageTimelineItems.value;

    if (!items.length) {
        return null;
    }

    let from = items[0].from;
    let to = items[0].to;

    for (const item of items) {
        if (item.from < from) {
            from = item.from;
        }

        if (item.to > to) {
            to = item.to;
        }
    }

    if (to > periodMax) {
        to = periodMax;
    }

    if (from > to) {
        return null;
    }

    return { from, to };
});

const syncMetricGroups = [
    {
        id: 'ga',
        label: 'Google Analytics',
        available: () => gaConnected.value,
        metrics: [
            { key: 'sessions', labelKey: 'sites.show.col.sessions', defaultSelected: true },
            { key: 'total_users', labelKey: 'sites.show.col.users', defaultSelected: true },
            { key: 'new_users', labelKey: 'sites.show.metric.newUsers', defaultSelected: true },
            { key: 'screen_page_views', labelKey: 'sites.show.col.views', defaultSelected: true },
            { key: 'organic_sessions', labelKey: 'sites.show.metric.organicSessions', defaultSelected: true },
            { key: 'organic_total_users', labelKey: 'sites.show.metric.organicUsers', defaultSelected: true },
            { key: 'organic_new_users', labelKey: 'sites.show.metric.organicNewUsers', defaultSelected: true },
            { key: 'engaged_sessions', labelKey: 'sites.show.metric.engagedSessions', defaultSelected: false, optional: true },
            { key: 'engagement_rate', labelKey: 'sites.show.metric.engagementRate', defaultSelected: false, optional: true },
            { key: 'bounce_rate', labelKey: 'sites.show.metric.bounceRate', defaultSelected: false, optional: true },
            { key: 'average_session_duration', labelKey: 'sites.show.metric.averageSessionDuration', defaultSelected: false, optional: true },
            { key: 'event_count', labelKey: 'sites.show.tabs.events', defaultSelected: false, optional: true },
            { key: 'organic_engaged_sessions', labelKey: 'sites.show.metric.organicEngagedSessions', defaultSelected: false, optional: true },
        ],
    },
    {
        id: 'gsc-daily',
        labelKey: 'sites.show.sync.groupGscDaily',
        available: () => gscConnected.value,
        metrics: [
            { key: 'clicks', labelKey: 'sites.show.col.clicks', defaultSelected: true },
            { key: 'impressions', labelKey: 'sites.show.col.impressions', defaultSelected: true },
            { key: 'ctr', label: 'CTR', defaultSelected: true },
            { key: 'position', labelKey: 'sites.show.col.position', defaultSelected: true },
        ],
    },
    {
        id: 'gsc-dimensions',
        labelKey: 'sites.show.sync.groupGscDimensions',
        available: () => gscConnected.value,
        metrics: [
            { key: 'queries', labelKey: 'sites.show.metric.queries', defaultSelected: true, limitKey: 'queries' },
            { key: 'pages', labelKey: 'sites.show.metric.pages', defaultSelected: true, limitKey: 'pages' },
            { key: 'devices', labelKey: 'sites.show.gsc.devices', defaultSelected: true },
            { key: 'countries', labelKey: 'sites.show.gsc.countries', defaultSelected: true },
            { key: 'search_appearances', labelKey: 'sites.show.gsc.searchAppearances', defaultSelected: false, optional: true },
            { key: 'sitemaps', label: 'Sitemaps', defaultSelected: false, optional: true },
            {
                key: 'url_inspections',
                labelKey: 'sites.show.sync.urlInspectionTop',
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
            { key: 'commits', labelKey: 'sites.show.metric.commits', defaultSelected: true },
        ],
    },
    {
        id: 'pagespeed',
        label: 'Chrome UX Report',
        available: () => pagespeedConnected.value,
        metrics: [
            { key: 'psi_lab', label: 'Lab (Lighthouse)', defaultSelected: true },
            { key: 'crux_origin', label: 'Chrome UX Report origin', defaultSelected: true },
            { key: 'crux_url', label: 'Chrome UX Report URL', defaultSelected: false, optional: true },
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

const expandedSyncMetricGroups = reactive({});

const syncStatusItems = computed(() => {
    const items = [];

    if (gaConnected.value || gscConnected.value) {
        items.push({
            key: 'google',
            label: 'Google',
            at: integration.value?.last_synced_at || null,
            error: integration.value?.last_error || null,
        });
    }

    if (githubConnected.value) {
        items.push({
            key: 'github',
            label: 'GitHub',
            at: githubIntegration.value?.last_synced_at || null,
            error: githubIntegration.value?.last_error || null,
        });
    }

    if (pagespeedConnected.value) {
        items.push({
            key: 'pagespeed',
            label: 'Chrome UX Report',
            at: pagespeedIntegration.value?.last_synced_at || null,
            error: pagespeedIntegration.value?.last_error || null,
        });
    }

    return items;
});

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

function syncMetricGroupSelectedCount(group) {
    return group.metrics.filter((metric) => syncMetricSelection[metric.key]).length;
}

function isSyncMetricGroupExpanded(groupId) {
    return Boolean(expandedSyncMetricGroups[groupId]);
}

function toggleSyncMetricGroupExpanded(groupId) {
    expandedSyncMetricGroups[groupId] = !expandedSyncMetricGroups[groupId];
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
    && !aiReportPreprocessing.value
    && !aiServicesLoading.value
    && (aiReportUseSystemPrompt.value || Boolean(aiReportCustomPrompt.value.trim()))
));

const canPreprocessAiReport = computed(() => (
    Boolean(aiReportServiceId.value)
    && Boolean(period.from)
    && Boolean(period.to)
    && period.from <= period.to
    && !busy.value
    && !aiReportGenerating.value
    && !aiReportPreprocessing.value
    && !aiReportPreprocessFetching.value
    && !aiServicesLoading.value
));

const aiReportGenerateButtonLabel = computed(() => {
    if (!aiReportGenerating.value) {
        return t('sites.show.aiReport.generate');
    }

    if (aiReportAttempt.value > 1) {
        return t('sites.show.aiReport.takingLonger');
    }

    return t('sites.show.aiReport.generating');
});

const aiReportProcessMessage = computed(() => {
    if (aiReportAttempt.value > 1) {
        return t('sites.process.aiReport.takingLonger');
    }

    return t('sites.process.aiReport.generating');
});

const aiReportPromptKindLabel = computed(() => {
    if (!aiReportPrompt.value) {
        return '';
    }

    return aiReportPromptIsSystem.value
        ? t('sites.show.aiReport.promptModal.systemBadge')
        : t('sites.show.aiReport.promptModal.customBadge');
});

const aiReportPreprocessButtonLabel = computed(() => {
    if (!aiReportPreprocessing.value) {
        return t('sites.show.aiReport.preprocess.button');
    }

    if (aiReportPreprocessAttempt.value > 1) {
        return t('sites.show.aiReport.takingLonger');
    }

    return t('sites.show.aiReport.preprocess.processing');
});

const aiReportPreprocessSelectedCount = computed(() => aiReportPreprocessSelectedKeys.value.length);

const aiReportPreprocessAllSelected = computed(() => (
    aiReportPreprocessItems.value.length > 0
    && aiReportPreprocessSelectedKeys.value.length === aiReportPreprocessItems.value.length
));

const aiReportPreprocessGroups = computed(() => {
    const groups = [];
    const indexByType = {};

    for (const item of aiReportPreprocessItems.value) {
        const type = item.type || 'unknown';
        if (indexByType[type] === undefined) {
            indexByType[type] = groups.length;
            groups.push({ type, items: [] });
        }
        groups[indexByType[type]].items.push(item);
    }

    return groups;
});

const canConfirmAiReportPreprocess = computed(() => (
    aiReportPreprocessItems.value.length > 0
    && aiReportPreprocessSelectedKeys.value.length > 0
    && !aiReportPreprocessFetching.value
));

function preprocessTypeLabel(type) {
    const key = `sites.show.aiReport.preprocess.types.${type}`;
    const label = t(key);

    return label === key ? type : label;
}

const isAiReportOpen = computed(() => Boolean(selectedAiReportId.value));

const aiReportUsageItems = computed(() => {
    const usage = aiReportUsage.value;

    if (!usage || typeof usage !== 'object') {
        return [];
    }

    const fields = [
        { key: 'prompt_tokens', label: t('sites.show.aiReport.promptTokens') },
        { key: 'candidates_tokens', label: t('sites.show.aiReport.candidatesTokens') },
        { key: 'thoughts_tokens', label: t('sites.show.aiReport.thoughtsTokens') },
        { key: 'total_tokens', label: t('sites.show.aiReport.totalTokens') },
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

const isDocumentEditing = computed(() => Boolean(editingDocumentId.value));

const showDocumentTabs = computed(() => documentsLoaded.value);

const canSaveDocument = computed(() => {
    if (!String(documentForm.title || '').trim() || documentSaving.value) {
        return false;
    }

    if (editingDocumentId.value) {
        return true;
    }

    return Boolean(documentFile.value);
});

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

    const strategy = pagespeedIntegration.value?.strategy_label || t('common.connected');
    const urls = pagespeedIntegration.value?.page_urls;

    if (Array.isArray(urls) && urls.length) {
        return `${strategy} · ${urls.length} URL`;
    }

    return strategy;
});

const pagespeedPageUrlsLabel = computed(() => {
    const urls = pagespeedIntegration.value?.page_urls;

    if (!Array.isArray(urls) || !urls.length) {
        return '';
    }

    if (urls.length <= 2) {
        return urls.join(', ');
    }

    return `${urls[0]}, ${urls[1]} ${t('sites.show.pagespeed.andMore', { count: urls.length - 2 })}`;
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
        return new Date(value).toLocaleString(intlLocale());
    } catch {
        return value;
    }
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    try {
        return new Date(`${value}T00:00:00`).toLocaleDateString(intlLocale());
    } catch {
        return value;
    }
}

function formatCoveragePeriod(from, to) {
    const fromLabel = formatDate(from);
    const toLabel = formatDate(to);
    const days = aiReportPeriodDays(from, to);

    if (days === null) {
        return `${fromLabel} — ${toLabel}`;
    }

    if (from === to) {
        return `${fromLabel} (1 ${pluralDays(1)})`;
    }

    return `${fromLabel} — ${toLabel} (${days} ${pluralDays(days)})`;
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
        return t('sites.show.format.durationSeconds', { seconds: secs });
    }

    return t('sites.show.format.durationMinutes', { minutes: mins, seconds: secs.toString().padStart(2, '0') });
}

function formatGscDevice(value) {
    const map = {
        DESKTOP: t('sites.show.format.deviceDesktop'),
        MOBILE: t('sites.show.format.deviceMobile'),
        TABLET: t('sites.show.format.deviceTablet'),
    };

    return map[String(value || '').toUpperCase()] || value || '—';
}

function formatInspectionLabel(value) {
    if (!value) {
        return '—';
    }

    const map = {
        PASS: 'OK',
        FAIL: t('sites.show.inspection.fail'),
        NEUTRAL: t('sites.show.inspection.neutral'),
        PARTIAL: t('sites.show.inspection.partial'),
        SUCCESSFUL: t('sites.show.inspection.successful'),
        SOFT_404: 'Soft 404',
        NOT_FOUND: '404',
        SERVER_ERROR: t('sites.show.inspection.serverError'),
        BLOCKED_ROBOTS_TXT: 'robots.txt',
        ACCESS_DENIED: '401',
        ACCESS_FORBIDDEN: '403',
        REDIRECT_ERROR: t('sites.show.inspection.redirectError'),
        INDEXING_ALLOWED: t('sites.show.inspection.allowed'),
        BLOCKED_BY_META_TAG: 'noindex (meta)',
        BLOCKED_BY_HTTP_HEADER: 'noindex (header)',
        ALLOWED: t('sites.show.inspection.allowed'),
        DISALLOWED: t('sites.show.inspection.disallowed'),
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

const commitFilesModalTitle = computed(() => {
    const sha = commitFilesDetail.value?.short_sha || '';

    return t('sites.show.github.changesModalTitle', { sha: sha || '…' });
});

function commitFileStatusLabel(status) {
    const map = {
        added: t('sites.show.github.statusAdded'),
        removed: t('sites.show.github.statusRemoved'),
        modified: t('sites.show.github.statusModified'),
        renamed: t('sites.show.github.statusRenamed'),
        copied: t('sites.show.github.statusCopied'),
        changed: t('sites.show.github.statusChanged'),
        unchanged: t('sites.show.github.statusUnchanged'),
    };

    return map[String(status)] || status || '—';
}

function toggleCommitFilePatch(filename) {
    expandedCommitPatches.value = {
        ...expandedCommitPatches.value,
        [filename]: !expandedCommitPatches.value[filename],
    };
}

function openCommitFilesModal(detail) {
    commitFilesDetail.value = detail;
    commitFilesError.value = '';
    expandedCommitPatches.value = {};
    commitFilesModalOpen.value = true;
}

async function onCommitFilesAction(row) {
    if (!site.value || !row?.id || commitFilesBusyId.value) {
        return;
    }

    commitFilesBusyId.value = row.id;
    commitFilesError.value = '';

    try {
        if (row.has_files) {
            commitFilesLoading.value = true;
            commitFilesModalOpen.value = true;
            commitFilesDetail.value = null;
            const detail = await getSiteGithubCommitFiles(site.value.id, row.id);
            openCommitFilesModal(detail);
        } else {
            const detail = await fetchSiteGithubCommitFiles(site.value.id, row.id);
            const index = commitRows.value.findIndex((item) => item.id === row.id);
            if (index !== -1) {
                commitRows.value[index] = {
                    ...commitRows.value[index],
                    has_files: true,
                    files_fetched_at: detail.files_fetched_at,
                };
            }
            openCommitFilesModal(detail);
        }
    } catch (e) {
        const message = e.response?.data?.message
            || (row.has_files
                ? t('sites.show.errors.loadCommitFiles')
                : t('sites.show.errors.fetchCommitFiles'));

        if (row.has_files) {
            commitFilesDetail.value = null;
            commitFilesError.value = message;
            commitFilesModalOpen.value = true;
        } else {
            toast.show({ ok: false, message });
        }
    } finally {
        commitFilesBusyId.value = null;
        commitFilesLoading.value = false;
    }
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

function formatCruxOverallCategory(value) {
    if (!value) {
        return '—';
    }

    const map = {
        FAST: t('sites.show.crux.fast'),
        AVERAGE: t('sites.show.crux.average'),
        SLOW: t('sites.show.crux.slow'),
    };

    return map[String(value).toUpperCase()] || value;
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
    aiReportPrompt.value = '';
    aiReportPromptIsSystem.value = true;
    aiReportPromptOpen.value = false;
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
        return t('sites.show.sharing.badgeLink');
    }

    if (visibility === 'password') {
        return t('sites.show.sharing.badgePassword');
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
        toast.show({ ok: true, message: t('sites.show.sharing.copied') });
    } catch {
        toast.show({ ok: false, message: t('sites.show.sharing.copyFailed') });
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
        toast.show({ ok: true, message: t('sites.show.sharing.saved') });

        if (report.sharing?.visibility === 'private') {
            aiReportSharingOpen.value = false;
        }
    } catch (e) {
        aiReportSharingErrors.password = e.response?.data?.errors?.password?.[0] || '';
        aiReportSharingError.value = e.response?.data?.message
            || e.response?.data?.errors?.visibility?.[0]
            || (aiReportSharingErrors.password ? '' : t('sites.show.sharing.saveFailed'));
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
    return Number(value).toLocaleString(intlLocale());
}

function aiServiceOptionLabel(service) {
    const model = service.settings?.model;

    return model ? `${service.name} — ${model}` : service.name;
}

function aiReportCardTitle(report) {
    return report.created_at
        ? formatDateTime(report.created_at)
        : t('sites.show.aiReport.noDate');
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
    const category = new Intl.PluralRules(intlLocale()).select(Math.abs(count));

    return t(`common.days.${category}`);
}

function applyAiReport(report) {
    selectedAiReportId.value = report?.id ? String(report.id) : '';
    aiReportReply.value = report?.reply || '';
    aiReportCharts.value = Array.isArray(report?.charts) ? report.charts : [];
    aiReportModel.value = report?.tool?.label || report?.tool?.model || '';
    aiReportMeta.value = formatDataCounts(report?.data_counts);
    aiReportUsage.value = report?.usage ?? null;
    aiReportPromptIsSystem.value = report?.use_system_prompt !== false;
    aiReportPrompt.value = report?.prompt || '';
    applyAiReportSharing(report?.sharing);

    if (report?.period?.from) {
        period.from = report.period.from;
        periodDefaultsFromCoverageApplied.value = true;
    }

    if (report?.period?.to) {
        period.to = report.period.to;
        periodDefaultsFromCoverageApplied.value = true;
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

    const countLabels = [
        ['website', 'countsWebsite'],
        ['analytics', 'countsAnalytics'],
        ['search_console', 'countsSearchConsole'],
        ['search_console_queries', 'countsQueries'],
        ['search_console_pages', 'countsPages'],
        ['search_console_devices', 'countsDevices'],
        ['search_console_countries', 'countsCountries'],
        ['search_console_appearances', 'countsAppearances'],
        ['search_console_sitemaps', 'countsSitemaps'],
        ['search_console_url_inspections', 'countsUrlInspections'],
        ['pagespeed_lab', 'countsPagespeedLab'],
        ['pagespeed_crux', 'countsCrux'],
        ['github_commits', 'countsGithub'],
        ['events', 'countsEvents'],
        ['documents', 'countsDocuments'],
    ];

    const parts = countLabels
        .filter(([field]) => counts[field])
        .map(([field, labelKey]) => t(`sites.show.aiReport.${labelKey}`, { count: counts[field] }));

    return parts.length ? t('sites.show.aiReport.countsSummary', { parts: parts.join(', ') }) : '';
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
        aiReportsError.value = e.response?.data?.message || t('sites.show.errors.loadReports');
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
        aiServicesError.value = e.response?.data?.message || t('sites.show.errors.loadAiServices');
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
    aiReportPrompt.value = '';
    applyAiReportSharing(report.sharing);

    try {
        const fullReport = await getSiteAiReport(site.value.id, report.id);
        applyAiReport(fullReport);
    } catch (e) {
        clearAiReportView();
        selectedAiReportId.value = '';
        aiReportError.value = e.response?.data?.message || t('sites.show.errors.loadReport');
    } finally {
        aiReportLoading.value = false;
    }
}

async function onGenerateAiReport() {
    if (!canGenerateAiReport.value || !site.value) {
        return;
    }

    aiReportGenerating.value = true;
    aiReportProcessActive.value = true;
    aiReportAttempt.value = 0;
    aiReportError.value = '';
    clearAiReportView();
    selectedAiReportId.value = '';

    const payload = {
        ai_service_id: Number(aiReportServiceId.value),
        from: period.from,
        to: period.to,
        use_system_prompt: aiReportUseSystemPrompt.value,
    };

    if (!aiReportUseSystemPrompt.value) {
        payload.prompt = aiReportCustomPrompt.value.trim();
    }

    try {
        for (let attempt = 1; attempt <= AI_REPORT_MAX_ATTEMPTS; attempt += 1) {
            aiReportAttempt.value = attempt;

            try {
                const result = await generateSiteAiReport(site.value.id, payload);

                if (result.ok) {
                    const report = result.data;

                    if (report) {
                        upsertAiReportListItem(report);
                        applyAiReport(report);
                    }

                    return;
                }

                const canRetry = Boolean(result.retryable) && attempt < AI_REPORT_MAX_ATTEMPTS;

                if (canRetry) {
                    aiReportAttempt.value = attempt + 1;

                    await new Promise((resolve) => {
                        setTimeout(resolve, AI_REPORT_RETRY_DELAY_MS);
                    });

                    continue;
                }

                aiReportError.value = result.message || t('sites.show.errors.generateReport');
                aiReportMeta.value = formatDataCounts(result.data_counts);

                return;
            } catch (e) {
                const canRetry = attempt < AI_REPORT_MAX_ATTEMPTS
                    && !e.response?.status;

                if (canRetry) {
                    aiReportAttempt.value = attempt + 1;

                    await new Promise((resolve) => {
                        setTimeout(resolve, AI_REPORT_RETRY_DELAY_MS);
                    });

                    continue;
                }

                aiReportError.value = e.response?.data?.message
                    || e.response?.data?.errors?.ai_service_id?.[0]
                    || t('sites.show.errors.generateReport');

                return;
            }
        }
    } finally {
        aiReportGenerating.value = false;
        aiReportProcessActive.value = false;
        aiReportAttempt.value = 0;
    }
}

function onTogglePreprocessItem(key) {
    const selected = aiReportPreprocessSelectedKeys.value;

    if (selected.includes(key)) {
        aiReportPreprocessSelectedKeys.value = selected.filter((item) => item !== key);

        return;
    }

    aiReportPreprocessSelectedKeys.value = [...selected, key];
}

function onToggleAllPreprocessItems(event) {
    if (event.target.checked) {
        aiReportPreprocessSelectedKeys.value = aiReportPreprocessItems.value.map((item) => item.key);

        return;
    }

    aiReportPreprocessSelectedKeys.value = [];
}

async function onPreprocessAiReport() {
    if (!canPreprocessAiReport.value || !site.value) {
        return;
    }

    aiReportPreprocessing.value = true;
    aiReportPreprocessAttempt.value = 0;
    aiReportError.value = '';
    aiReportPreprocessError.value = '';
    aiReportPreprocessItems.value = [];
    aiReportPreprocessSelectedKeys.value = [];

    const payload = {
        ai_service_id: Number(aiReportServiceId.value),
        from: period.from,
        to: period.to,
    };

    try {
        for (let attempt = 1; attempt <= AI_REPORT_PREPROCESS_MAX_ATTEMPTS; attempt += 1) {
            aiReportPreprocessAttempt.value = attempt;

            try {
                const result = await preprocessSiteAiReport(site.value.id, payload);

                if (result.ok) {
                    const items = Array.isArray(result.items) ? result.items : [];
                    aiReportPreprocessItems.value = items;
                    aiReportPreprocessSelectedKeys.value = items.map((item) => item.key);
                    aiReportPreprocessModalOpen.value = true;

                    return;
                }

                const canRetry = Boolean(result.retryable) && attempt < AI_REPORT_PREPROCESS_MAX_ATTEMPTS;

                if (canRetry) {
                    await new Promise((resolve) => {
                        setTimeout(resolve, AI_REPORT_RETRY_DELAY_MS);
                    });

                    continue;
                }

                aiReportError.value = result.message || t('sites.show.errors.preprocessReport');

                return;
            } catch (e) {
                const canRetry = attempt < AI_REPORT_PREPROCESS_MAX_ATTEMPTS
                    && !e.response?.status;

                if (canRetry) {
                    await new Promise((resolve) => {
                        setTimeout(resolve, AI_REPORT_RETRY_DELAY_MS);
                    });

                    continue;
                }

                aiReportError.value = e.response?.data?.message
                    || e.response?.data?.errors?.ai_service_id?.[0]
                    || t('sites.show.errors.preprocessReport');

                return;
            }
        }
    } finally {
        aiReportPreprocessing.value = false;
        aiReportPreprocessAttempt.value = 0;
    }
}

async function onConfirmAiReportPreprocess() {
    if (!canConfirmAiReportPreprocess.value || !site.value) {
        return;
    }

    const selectedItems = aiReportPreprocessItems.value.filter((item) => (
        aiReportPreprocessSelectedKeys.value.includes(item.key)
    ));

    if (!selectedItems.length) {
        return;
    }

    aiReportPreprocessFetching.value = true;
    aiReportPreprocessError.value = '';

    try {
        const result = await applySiteAiReportPreprocess(site.value.id, {
            from: period.from,
            to: period.to,
            items: selectedItems.map((item) => ({
                key: item.key,
                type: item.type,
                reason: item.reason || '',
                commit_id: item.commit_id ?? null,
                sha: item.sha ?? null,
                url: item.url ?? null,
                snapshot_id: item.snapshot_id ?? null,
            })),
        });

        const failed = (result.results || []).filter((row) => !row.ok);

        if (result.ok && failed.length === 0) {
            aiReportPreprocessModalOpen.value = false;
            toast.show({
                ok: true,
                message: t('sites.show.toast.preprocessFetched'),
            });

            return;
        }

        aiReportPreprocessError.value = result.message
            || failed[0]?.message
            || t('sites.show.aiReport.preprocess.fetchFailed');

        toast.show({
            ok: false,
            message: aiReportPreprocessError.value,
        });
    } catch (e) {
        aiReportPreprocessError.value = e.response?.data?.message
            || t('sites.show.aiReport.preprocess.fetchFailed');
        toast.show({
            ok: false,
            message: aiReportPreprocessError.value,
        });
    } finally {
        aiReportPreprocessFetching.value = false;
    }
}

watch(activeSiteView, (view) => {
    if (view === 'events' && site.value) {
        loadEvents();

        return;
    }

    if (view === 'documents' && site.value) {
        loadDocuments();

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
    if (!site.value || eventsLoading.value) {
        return;
    }

    eventsLoading.value = true;
    eventsError.value = '';

    try {
        eventRows.value = await listSiteEvents(site.value.id);
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
        eventsError.value = e.response?.data?.message || t('sites.show.errors.loadEvents');
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

function onEventRowClick(row) {
    if (busy.value || eventSaving.value || deletingEventId.value) {
        return;
    }

    startEditEvent(row);
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

            eventRows.value = [
                updated,
                ...eventRows.value.filter((item) => item.id !== updated.id),
            ].sort((a, b) => {
                if (a.occurred_on === b.occurred_on) {
                    return b.id - a.id;
                }

                return a.occurred_on < b.occurred_on ? 1 : -1;
            });

            toast.show({ ok: true, message: t('sites.show.toast.eventUpdated') });
            resetEventForm();
            activeEventTab.value = eventRows.value.length ? 'saved' : 'new';
        } else {
            const created = await createSiteEvent(site.value.id, payload);

            eventRows.value = [created, ...eventRows.value].sort((a, b) => {
                if (a.occurred_on === b.occurred_on) {
                    return b.id - a.id;
                }

                return a.occurred_on < b.occurred_on ? 1 : -1;
            });

            toast.show({ ok: true, message: t('sites.show.toast.eventAdded') });
            resetEventForm();
            activeEventTab.value = eventRows.value.length ? 'saved' : 'new';
        }
    } catch (e) {
        eventFormError.value = firstValidationError(e.response?.data?.errors)
            || e.response?.data?.message
            || t('sites.show.errors.saveEvent');
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

    if (!window.confirm(t('sites.show.confirm.deleteEvent'))) {
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

        toast.show({ ok: true, message: t('sites.show.toast.eventDeleted') });
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || t('sites.show.errors.deleteEvent'),
        });
    } finally {
        deletingEventId.value = null;
    }
}

async function loadDocuments() {
    if (!site.value || documentsLoading.value) {
        return;
    }

    documentsLoading.value = true;
    documentsError.value = '';

    try {
        documentRows.value = await listSiteDocuments(site.value.id);
        documentsLoaded.value = true;

        if (
            editingDocumentId.value
            && !documentRows.value.some((item) => item.id === editingDocumentId.value)
        ) {
            resetDocumentForm();
        }

        syncDocumentTab();
    } catch (e) {
        documentRows.value = [];
        documentsLoaded.value = true;
        documentsError.value = e.response?.data?.message || t('sites.show.errors.loadDocuments');
        syncDocumentTab();
    } finally {
        documentsLoading.value = false;
    }
}

function syncDocumentTab() {
    if (!documentRows.value.length) {
        if (activeDocumentTab.value !== 'saved') {
            activeDocumentTab.value = 'new';
        }

        return;
    }

    if (activeDocumentTab.value !== 'new') {
        activeDocumentTab.value = 'saved';
    }
}

function clearDocumentFileInput() {
    documentFile.value = null;

    if (documentFileInput.value) {
        documentFileInput.value.value = '';
    }
}

function resetDocumentForm() {
    editingDocumentId.value = null;
    editingDocumentFilename.value = '';
    documentFormError.value = '';
    documentForm.title = '';
    documentForm.description = '';
    clearDocumentFileInput();
}

function onBackFromDocumentEdit() {
    resetDocumentForm();
    activeDocumentTab.value = documentRows.value.length ? 'saved' : 'new';
}

function startEditDocument(row) {
    editingDocumentId.value = row.id;
    editingDocumentFilename.value = row.original_filename || '';
    documentFormError.value = '';
    documentForm.title = row.title || '';
    documentForm.description = row.description || '';
    clearDocumentFileInput();
}

function onDocumentFileChange(event) {
    const file = event?.target?.files?.[0] || null;
    documentFile.value = file;
}

function documentCardDescription(row) {
    const text = String(row?.description || '').trim();

    if (!text) {
        return '';
    }

    if (text.length <= 120) {
        return text;
    }

    return `${text.slice(0, 117).trimEnd()}…`;
}

async function onSubmitDocument() {
    if (!canSaveDocument.value || !site.value) {
        return;
    }

    documentSaving.value = true;
    documentFormError.value = '';

    const payload = {
        title: String(documentForm.title || '').trim(),
        description: String(documentForm.description || '').trim() || null,
        document: documentFile.value || null,
    };

    try {
        if (editingDocumentId.value) {
            const updated = await updateSiteDocument(site.value.id, editingDocumentId.value, payload);

            documentRows.value = [
                updated,
                ...documentRows.value.filter((item) => item.id !== updated.id),
            ].sort((a, b) => b.id - a.id);

            toast.show({ ok: true, message: t('sites.show.toast.documentUpdated') });
            resetDocumentForm();
            activeDocumentTab.value = documentRows.value.length ? 'saved' : 'new';
        } else {
            if (!payload.document) {
                documentFormError.value = t('sites.show.errors.attachMarkdown');

                return;
            }

            const created = await createSiteDocument(site.value.id, payload);
            documentRows.value = [created, ...documentRows.value].sort((a, b) => b.id - a.id);

            toast.show({ ok: true, message: t('sites.show.toast.documentAdded') });
            resetDocumentForm();
            activeDocumentTab.value = documentRows.value.length ? 'saved' : 'new';
        }
    } catch (e) {
        documentFormError.value = firstValidationError(e.response?.data?.errors)
            || e.response?.data?.message
            || t('sites.show.errors.saveDocument');
    } finally {
        documentSaving.value = false;
    }
}

async function onDeleteEditingDocument() {
    if (!editingDocumentId.value) {
        return;
    }

    await onDeleteDocument({ id: editingDocumentId.value });
}

async function onDeleteDocument(row) {
    if (!site.value || !row?.id) {
        return;
    }

    if (!window.confirm(t('sites.show.confirm.deleteDocument'))) {
        return;
    }

    deletingDocumentId.value = row.id;

    try {
        await deleteSiteDocument(site.value.id, row.id);
        documentRows.value = documentRows.value.filter((item) => item.id !== row.id);

        if (editingDocumentId.value === row.id) {
            resetDocumentForm();
            activeDocumentTab.value = documentRows.value.length ? 'saved' : 'new';
        } else {
            syncDocumentTab();
        }

        toast.show({ ok: true, message: t('sites.show.toast.documentDeleted') });
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || t('sites.show.errors.deleteDocument'),
        });
    } finally {
        deletingDocumentId.value = null;
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

async function loadMetricsCoverage() {
    if (!site.value) {
        return;
    }

    try {
        metricsCoverage.value = await getSiteMetricsCoverage(site.value.id);
    } catch {
        metricsCoverage.value = null;
    } finally {
        metricsCoverageLoaded.value = true;
    }
}

function applyCoveragePeriodDefaults() {
    if (periodDefaultsFromCoverageApplied.value) {
        return false;
    }

    const bounds = metricsCoverageBounds.value;

    if (!bounds) {
        return false;
    }

    period.from = bounds.from;
    period.to = bounds.to;
    periodDefaultsFromCoverageApplied.value = true;

    return true;
}

async function onPeriodChange() {
    if (!period.from || !period.to || period.from > period.to) {
        return;
    }

    periodDefaultsFromCoverageApplied.value = true;
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
        listsError.value = e.response?.data?.message || t('sites.show.errors.loadGoogleLists');
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
        githubReposError.value = e.response?.data?.message || t('sites.show.errors.loadGithubRepos');
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
        githubBranchesError.value = e.response?.data?.message || t('sites.show.errors.loadGithubBranches');
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
    pagespeedForm.pageUrlsText = Array.isArray(pagespeedIntegration.value?.page_urls)
        ? pagespeedIntegration.value.page_urls.join('\n')
        : '';
    pagespeedModalOpen.value = true;
}

async function reload() {
    loading.value = true;
    error.value = '';
    stopWebDataPolling();

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
        pagespeedForm.pageUrlsText = Array.isArray(pagespeedIntegration.value?.page_urls)
            ? pagespeedIntegration.value.page_urls.join('\n')
            : '';

        preferConnectedMetricsTab();
        await loadMetricsCoverage();
        applyCoveragePeriodDefaults();

        await Promise.all([
            loadMetrics(),
            loadEvents(),
        ]);
        startWebDataPolling();
    } catch (e) {
        error.value = e.response?.data?.message || t('sites.show.errors.loadSite');
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
            message: e.response?.data?.message || t('sites.show.errors.startGoogleAuth'),
        });
        busy.value = false;
    }
}

async function onDisconnectGoogle() {
    if (!window.confirm(t('sites.show.confirm.disconnectGoogle'))) {
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
        pagespeedForm.pageUrlsText = '';
        pagespeedLabRows.value = [];
        pagespeedCruxRows.value = [];
        ga4Properties.value = [];
        gscSites.value = [];
        gaModalOpen.value = false;
        gscModalOpen.value = false;
        pagespeedModalOpen.value = false;
        preferConnectedMetricsTab();
        toast.show({ ok: true, message: t('sites.show.toast.googleDisconnected') });
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || t('sites.show.errors.disconnectGoogle'),
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
            message: e.response?.data?.message || t('sites.show.errors.startGithubAuth'),
        });
        busy.value = false;
    }
}

async function onDisconnectGithub() {
    if (!window.confirm(t('sites.show.confirm.disconnectGithub'))) {
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
        toast.show({ ok: true, message: t('sites.show.toast.githubDisconnected') });
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || t('sites.show.errors.disconnectGithub'),
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
            message: t('sites.show.toast.gaLinked'),
        });
        gaModalOpen.value = false;
        await loadMetrics();
        preferConnectedMetricsTab();
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || t('sites.show.errors.saveBinding'),
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
            message: t('sites.show.toast.gscLinked'),
        });
        gscModalOpen.value = false;
        await loadMetrics();
        preferConnectedMetricsTab();
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || t('sites.show.errors.saveBinding'),
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
            message: t('sites.show.toast.repoLinked'),
        });
        githubModalOpen.value = false;
        preferConnectedMetricsTab();
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || t('sites.show.errors.saveRepository'),
        });
    } finally {
        busy.value = false;
    }
}

async function onClearGithubIntegration() {
    if (!window.confirm(t('sites.show.confirm.unlinkRepo'))) {
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
        toast.show({ ok: true, message: t('sites.show.toast.repoUnlinked') });
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || t('sites.show.errors.unlinkRepository'),
        });
    } finally {
        busy.value = false;
    }
}

async function onSavePagespeedIntegration() {
    busy.value = true;

    try {
        const pageUrls = pagespeedForm.pageUrlsText
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean);

        pagespeedIntegration.value = await upsertSitePageSpeedIntegration(site.value.id, {
            strategy: pagespeedForm.strategy,
            page_urls: pageUrls,
        });
        pagespeedForm.pageUrlsText = Array.isArray(pagespeedIntegration.value?.page_urls)
            ? pagespeedIntegration.value.page_urls.join('\n')
            : '';
        toast.show({
            ok: true,
            message: t('sites.show.toast.cruxConnected'),
        });
        pagespeedModalOpen.value = false;
        preferConnectedMetricsTab();
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || t('sites.show.errors.saveConnection'),
        });
    } finally {
        busy.value = false;
    }
}

async function onDisconnectPagespeedIntegration() {
    if (!window.confirm(t('sites.show.confirm.disconnectCrux'))) {
        return;
    }

    busy.value = true;

    try {
        await deleteSitePageSpeedIntegration(site.value.id);
        pagespeedIntegration.value = null;
        pagespeedForm.strategy = 'mobile';
        pagespeedForm.pageUrlsText = '';
        pagespeedLabRows.value = [];
        pagespeedCruxRows.value = [];
        pagespeedModalOpen.value = false;
        preferConnectedMetricsTab();
        toast.show({ ok: true, message: t('sites.show.toast.cruxDisconnected') });
    } catch (e) {
        toast.show({
            ok: false,
            message: e.response?.data?.message || t('sites.show.errors.disconnectCrux'),
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
    syncProcessActive.value = true;
    syncProcessMessage.value = '';

    const basePayload = {
        from: period.from,
        to: period.to,
    };
    const errors = [];
    let syncedAny = false;

    try {
        if ((gaConnected.value || gscConnected.value) && selectedGoogleSyncMetrics.value.length) {
            syncProcessMessage.value = t('sites.process.sync.google');

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
                errors.push(e.response?.data?.message || t('sites.show.errors.loadGoogleData'));
            }
        }

        if (githubConnected.value && selectedGithubSyncMetrics.value.length) {
            syncProcessMessage.value = t('sites.process.sync.github');

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
                errors.push(e.response?.data?.message || t('sites.show.errors.loadCommits'));
            }
        }

        if (pagespeedConnected.value && selectedPageSpeedSyncMetrics.value.length) {
            syncProcessMessage.value = t('sites.process.sync.pagespeed');

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
                errors.push(e.response?.data?.message || t('sites.show.errors.loadCruxData'));
            }
        }

        syncProcessMessage.value = t('sites.process.sync.refreshMetrics');
        await loadMetricsCoverage();
        applyCoveragePeriodDefaults();
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
                message: t('sites.show.toast.periodImported'),
            });
        }
    } finally {
        busy.value = false;
        syncProcessActive.value = false;
        syncProcessMessage.value = '';
    }
}

onMounted(async () => {
    if (route.query.google === 'connected') {
        toast.show({ ok: true, message: t('sites.googleConnected') });
    } else if (route.query.google === 'error') {
        toast.show({
            ok: false,
            message: route.query.message || t('sites.googleConnectFailed'),
        });
    } else if (route.query.github === 'connected') {
        toast.show({ ok: true, message: t('sites.githubConnected') });
    } else if (route.query.github === 'error') {
        toast.show({
            ok: false,
            message: route.query.message || t('sites.githubConnectFailed'),
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
