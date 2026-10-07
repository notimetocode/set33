<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    initialUrl: {
        type: String,
        default: '',
    },
    apiUrl: {
        type: String,
        required: true,
    },
    i18n: {
        type: Object,
        default: () => ({}),
    },
});

const t = (key) => props.i18n[key] ?? key;

const RESPONSE_TIME_MAX_MS = 2000;
const RESPONSE_TIME_GREEN_MS = 600;
const RESPONSE_TIME_EXCELLENT_MS = 300;
const RESPONSE_TIME_BAD_MS = 1200;

/** Practical SERP target for <title> (~50–60 chars desktop; 30–60 is the common OK band). */
const TITLE_LENGTH_MIN = 30;
const TITLE_LENGTH_MAX = 60;

/** Practical SERP target for meta description (~150–160 desktop; 120–160 survives mobile too). */
const DESCRIPTION_LENGTH_MIN = 120;
const DESCRIPTION_LENGTH_MAX = 160;

const status = ref('idle');
const errorMessage = ref('');
const report = ref(null);

/**
 * @param {number} ms
 * @returns {{
 *   ms: number,
 *   percent: number,
 *   greenPercent: number,
 *   yellowPercent: number,
 *   rating: 'excellent'|'good'|'bad'|'very_bad',
 *   label: string,
 *   hint: string,
 *   ok: boolean,
 * }}
 */
function buildResponseTimeBar(ms) {
    const value = Math.max(0, Math.round(ms));
    let rating = 'very_bad';

    if (value <= RESPONSE_TIME_EXCELLENT_MS) {
        rating = 'excellent';
    } else if (value <= RESPONSE_TIME_GREEN_MS) {
        rating = 'good';
    } else if (value <= RESPONSE_TIME_BAD_MS) {
        rating = 'bad';
    }

    return {
        ms: value,
        percent: Math.min(100, (value / RESPONSE_TIME_MAX_MS) * 100),
        greenPercent: (RESPONSE_TIME_GREEN_MS / RESPONSE_TIME_MAX_MS) * 100,
        yellowPercent: (RESPONSE_TIME_BAD_MS / RESPONSE_TIME_MAX_MS) * 100,
        rating,
        label: t(`response_time_${rating}`),
        hint: t(`response_time_${rating}_hint`),
        ok: value <= RESPONSE_TIME_GREEN_MS,
    };
}

/**
 * @param {'title'|'description'} field
 * @param {number} length
 * @returns {{
 *   length: number,
 *   rating: 'good'|'bad',
 *   label: string,
 *   meta: string,
 *   hint: string,
 *   ok: boolean,
 * }}
 */
function buildMetaLengthRating(field, length) {
    const value = Math.max(0, Math.round(length));
    const min = field === 'title' ? TITLE_LENGTH_MIN : DESCRIPTION_LENGTH_MIN;
    const max = field === 'title' ? TITLE_LENGTH_MAX : DESCRIPTION_LENGTH_MAX;
    const range = `${min}–${max}`;
    const inRange = value >= min && value <= max;
    let hintKey = `${field}_length_good_hint`;

    if (!inRange) {
        hintKey = value < min
            ? `${field}_length_short_hint`
            : `${field}_length_long_hint`;
    }

    const chars = (t('content_chars') || '').replace(':count', String(value));
    const recommended = (t('length_recommended') || '').replace(':range', range);

    return {
        length: value,
        rating: inRange ? 'good' : 'bad',
        label: t(inRange ? 'length_rating_good' : 'length_rating_bad'),
        meta: `${chars} · ${recommended}`,
        hint: t(hintKey),
        ok: inRange,
    };
}

function toDisplayDomain(value) {
    const raw = String(value || '').trim();

    if (!raw) {
        return '';
    }

    try {
        const withScheme = /^https?:\/\//i.test(raw) ? raw : `https://${raw}`;
        const host = new URL(withScheme).hostname;

        return host.replace(/^www\./i, '') || raw;
    } catch {
        return raw
            .replace(/^https?:\/\//i, '')
            .replace(/^www\./i, '')
            .split('/')[0]
            .split('?')[0]
            .split('#')[0];
    }
}

const displayDomain = computed(() => {
    if (report.value?.final_url) {
        return toDisplayDomain(report.value.final_url);
    }

    return toDisplayDomain(props.initialUrl);
});

const heroHeadline = computed(() => {
    if (status.value === 'loading' || status.value === 'idle') {
        return t('loading');
    }

    if (status.value === 'error') {
        return t('status_failed');
    }

    return displayDomain.value || t('title');
});

const heroLead = computed(() => {
    if (status.value === 'loading' || status.value === 'idle') {
        return t('loading_hint');
    }

    if (status.value === 'error') {
        return errorMessage.value;
    }

    return t('page_lead');
});

const checkItems = computed(() => {
    const data = report.value;

    if (!data) {
        return [];
    }

    const flag = (present) => (present ? t('present') : t('missing'));
    const yn = (value) => (value ? t('yes') : t('no'));
    const statusCode = (code) => (code == null
        ? '—'
        : t('status_code').replace(':code', String(code)));
    const fill = (template, replacements) => Object.entries(replacements).reduce(
        (text, [key, value]) => text.replace(`:${key}`, String(value)),
        template,
    );

    const www = data.www_redirect || {};
    const notFound = data.not_found_page || {};
    const ssl = data.ssl || {};
    const robots = data.robots_txt || {};
    const metaRobots = data.meta_robots || {};
    const icons = data.icons || {};
    const content = data.content || {};
    const titleInfo = content.title || {};
    const descriptionInfo = content.description || {};
    const headings = content.headings || { counts: {}, items: [] };
    const headingCounts = headings.counts || {};
    const openGraph = content.open_graph || {};
    const schemaOrg = content.schema_org || {};
    const externalLinks = content.external_links || {};
    const internalLinks = content.internal_links || {};

    const formatSslValue = () => {
        if (!ssl.enabled) {
            return t('ssl_missing');
        }

        const parts = [ssl.valid ? t('ssl_ok') : t('ssl_expired')];

        if (ssl.subject) {
            parts.push(fill(t('ssl_subject'), { subject: ssl.subject }));
        }

        if (ssl.issuer) {
            parts.push(fill(t('ssl_issuer'), { issuer: ssl.issuer }));
        }

        if (ssl.valid_to) {
            let expiry = fill(t('ssl_valid_to'), { date: ssl.valid_to });

            if (typeof ssl.days_remaining === 'number') {
                expiry += ` ${fill(t('ssl_days_left'), { days: ssl.days_remaining })}`;
            }

            parts.push(expiry);
        }

        return parts.join(' ');
    };

    return [
        {
            section: t('section_server'),
            icon: 'server',
            items: [
                {
                    label: t('label_response_time'),
                    ok: typeof data.response_time_ms === 'number'
                        && data.response_time_ms <= RESPONSE_TIME_GREEN_MS,
                    value: typeof data.response_time_ms === 'number'
                        ? null
                        : '—',
                    responseTime: typeof data.response_time_ms === 'number'
                        ? buildResponseTimeBar(data.response_time_ms)
                        : null,
                },
                {
                    label: t('label_http_status'),
                    ok: data.http_status >= 200 && data.http_status < 400,
                    value: statusCode(data.http_status),
                },
                {
                    label: t('label_ssl'),
                    ok: Boolean(ssl.valid),
                    value: formatSslValue(),
                },
                {
                    label: t('label_www_redirect'),
                    ok: Boolean(www.present),
                    value: www.checked
                        ? (www.present
                            ? `${www.from_host} → ${www.to_host}`
                            : t('missing'))
                        : '—',
                },
                {
                    label: t('label_not_found_status'),
                    ok: Boolean(notFound.returns_404),
                    value: notFound.checked
                        ? statusCode(notFound.http_status)
                        : '—',
                },
                {
                    label: t('label_not_found_home_link'),
                    ok: Boolean(notFound.home_link_present),
                    value: notFound.checked
                        ? yn(Boolean(notFound.home_link_present))
                        : '—',
                },
                {
                    label: t('label_ip'),
                    ok: Boolean(data.ip_address),
                    value: data.ip_address || t('missing'),
                },
            ],
        },
        {
            section: t('section_indexing'),
            icon: 'indexing',
            items: [
                {
                    label: t('label_meta_robots'),
                    ok: metaRobots.indexing_allowed !== false,
                    value: metaRobots.content
                        ? metaRobots.content
                        : (metaRobots.indexing_allowed === false ? t('no') : t('yes')),
                },
                {
                    label: t('label_robots'),
                    ok: Boolean(robots.present),
                    value: robots.present ? flag(true) : flag(false),
                },
                {
                    label: t('label_robots_indexing'),
                    ok: robots.indexing_allowed !== false,
                    value: robots.present
                        ? yn(robots.indexing_allowed !== false)
                        : '—',
                },
                {
                    label: t('label_sitemap'),
                    ok: Boolean(data.sitemap?.present),
                    value: data.sitemap?.present
                        ? (data.sitemap.url || flag(true))
                        : flag(false),
                },
            ],
        },
        {
            section: t('section_content'),
            icon: 'content',
            items: [
                {
                    label: t('label_page_title'),
                    ok: Boolean(titleInfo.text)
                        && titleInfo.count === 1
                        && (titleInfo.length || 0) >= TITLE_LENGTH_MIN
                        && (titleInfo.length || 0) <= TITLE_LENGTH_MAX,
                    value: titleInfo.text || flag(false),
                    lengthRating: titleInfo.text
                        ? buildMetaLengthRating('title', titleInfo.length || 0)
                        : null,
                    detail: titleInfo.text && (titleInfo.count || 0) !== 1
                        ? fill(t('content_tags_found'), { count: titleInfo.count || 0 })
                        : null,
                },
                {
                    label: t('label_page_description'),
                    ok: Boolean(descriptionInfo.text)
                        && descriptionInfo.count === 1
                        && (descriptionInfo.length || 0) >= DESCRIPTION_LENGTH_MIN
                        && (descriptionInfo.length || 0) <= DESCRIPTION_LENGTH_MAX,
                    value: descriptionInfo.text || flag(false),
                    lengthRating: descriptionInfo.text
                        ? buildMetaLengthRating('description', descriptionInfo.length || 0)
                        : null,
                    detail: descriptionInfo.text && (descriptionInfo.count || 0) !== 1
                        ? fill(t('content_tags_found'), { count: descriptionInfo.count || 0 })
                        : null,
                },
                {
                    label: t('label_headings'),
                    ok: (headingCounts.h1 || 0) === 1,
                    value: fill(t('content_headings_summary'), {
                        h1: headingCounts.h1 || 0,
                        h2: headingCounts.h2 || 0,
                        h3: headingCounts.h3 || 0,
                        h4: headingCounts.h4 || 0,
                        h5: headingCounts.h5 || 0,
                        h6: headingCounts.h6 || 0,
                    }),
                    headings: Array.isArray(headings.items) ? headings.items.slice(0, 12) : [],
                },
                {
                    label: t('label_text_length'),
                    ok: (content.text_length || 0) > 0,
                    value: fill(t('content_chars'), { count: content.text_length || 0 }),
                },
                {
                    label: t('label_word_count'),
                    ok: (content.word_count || 0) > 0,
                    value: fill(t('content_words'), { count: content.word_count || 0 }),
                },
                {
                    label: t('label_nausea'),
                    ok: (content.nausea || 0) > 0 && (content.nausea || 0) <= 10,
                    value: fill(t('content_nausea'), { value: content.nausea || 0 }),
                },
                {
                    label: t('label_html_size'),
                    ok: (content.html_size_bytes || 0) > 0,
                    value: fill(t('content_html_size'), {
                        size: Math.max(1, Math.round((content.html_size_bytes || 0) / 1024)),
                    }),
                },
                {
                    label: t('label_external_links'),
                    ok: true,
                    value: fill(t('content_links'), {
                        total: externalLinks.total || 0,
                        indexable: externalLinks.indexable || 0,
                    }),
                },
                {
                    label: t('label_internal_links'),
                    ok: (internalLinks.total || 0) > 0,
                    value: fill(t('content_links'), {
                        total: internalLinks.total || 0,
                        indexable: internalLinks.indexable || 0,
                    }),
                },
                {
                    label: t('label_adult_content'),
                    ok: !content.adult_content,
                    value: content.adult_content ? t('content_adult_yes') : t('content_adult_no'),
                },
                {
                    label: t('label_open_graph_block'),
                    ok: Boolean(openGraph.present),
                    value: openGraph.present ? null : t('content_og_missing'),
                    og: openGraph.present ? openGraph : null,
                },
                {
                    label: t('label_schema_org'),
                    ok: Boolean(schemaOrg.present),
                    value: schemaOrg.present ? null : t('content_schema_no'),
                    schema: schemaOrg.present
                        ? {
                            formats: [
                                {
                                    key: 'json-ld',
                                    label: 'JSON-LD',
                                    count: schemaOrg.json_ld_count || 0,
                                },
                                {
                                    key: 'microdata',
                                    label: 'Microdata',
                                    count: schemaOrg.microdata_count || 0,
                                },
                            ].filter((format) => format.count > 0),
                            items: Array.isArray(schemaOrg.items) ? schemaOrg.items.slice(0, 20) : [],
                        }
                        : null,
                },
            ],
        },
        {
            section: t('section_metadata'),
            icon: 'metadata',
            items: [
                {
                    label: t('label_viewport'),
                    ok: data.viewport_present,
                    value: data.viewport_present ? data.viewport : flag(false),
                },
                {
                    label: t('label_charset'),
                    ok: Boolean(data.charset),
                    value: data.charset || flag(false),
                },
                {
                    label: t('label_canonical'),
                    ok: data.canonical_present,
                    value: data.canonical_present ? data.canonical_url : flag(false),
                },
                {
                    label: t('label_favicon'),
                    ok: Boolean(icons.favicon),
                    value: icons.favicon ? flag(true) : flag(false),
                },
                {
                    label: t('label_apple_touch_icon'),
                    ok: Boolean(icons.apple_touch_icon),
                    value: icons.apple_touch_icon ? flag(true) : flag(false),
                },
                {
                    label: t('label_google_analytics'),
                    ok: data.google_analytics?.present,
                    value: data.google_analytics?.present
                        ? (data.google_analytics.ids?.join(', ') || flag(true))
                        : flag(false),
                },
                {
                    label: t('label_yandex_metrika'),
                    ok: data.yandex_metrika?.present,
                    value: data.yandex_metrika?.present
                        ? (data.yandex_metrika.counter_ids?.join(', ') || flag(true))
                        : flag(false),
                },
            ],
        },
    ];
});

async function runAudit() {
    const url = props.initialUrl.trim();

    if (!url) {
        status.value = 'error';
        errorMessage.value = t('error_missing_url');

        return;
    }

    status.value = 'loading';
    errorMessage.value = '';
    report.value = null;

    try {
        const response = await fetch(props.apiUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ url }),
        });

        const payload = await response.json().catch(() => ({}));

        if (response.status === 429) {
            status.value = 'error';
            errorMessage.value = t('error_throttle');

            return;
        }

        if (!response.ok) {
            const validationMessage = payload?.errors?.url?.[0]
                || payload?.message
                || t('error_generic');

            status.value = 'error';
            errorMessage.value = validationMessage;

            return;
        }

        report.value = payload.data || null;

        if (!report.value) {
            status.value = 'error';
            errorMessage.value = t('error_generic');

            return;
        }

        status.value = 'ready';
    } catch {
        status.value = 'error';
        errorMessage.value = t('error_generic');
    }
}

onMounted(() => {
    runAudit();
});
</script>

<template>
    <div class="page-site-audit__app">
        <section class="page-site-audit__hero" aria-labelledby="site-audit-heading">
            <div class="page-site-audit__dots" aria-hidden="true"></div>

            <div class="container page-site-audit__hero-inner">
                <p class="page-site-audit__eyebrow">{{ t('title') }}</p>
                <h1 id="site-audit-heading" class="page-site-audit__headline">
                    {{ heroHeadline }}
                </h1>
                <p class="page-site-audit__lead">{{ heroLead }}</p>
                <p
                    v-if="status === 'ready'"
                    class="page-site-audit__meta"
                    :class="report?.status === 'ready'
                        ? 'page-site-audit__meta--ready'
                        : 'page-site-audit__meta--failed'"
                >
                    {{ report?.status === 'ready' ? t('status_ready') : t('status_failed') }}
                    <template v-if="report?.error">
                        — {{ report.error }}
                    </template>
                </p>
                <p
                    v-else-if="(status === 'loading' || status === 'idle') && displayDomain"
                    class="page-site-audit__meta"
                >
                    {{ displayDomain }}
                </p>
            </div>
        </section>

        <section class="page-site-audit__content">
            <div class="page-site-audit__dots page-site-audit__dots--flat" aria-hidden="true"></div>

            <div class="container page-site-audit__content-inner">
                <div
                    v-if="status === 'loading' || status === 'idle'"
                    class="page-site-audit__panel page-site-audit__loading"
                    role="status"
                    aria-live="polite"
                >
                    <div class="page-site-audit__spinner" aria-hidden="true"></div>
                    <p v-if="displayDomain" class="page-site-audit__loading-url">{{ displayDomain }}</p>
                </div>

                <div
                    v-else-if="status === 'error'"
                    class="page-site-audit__panel page-site-audit__error"
                    role="alert"
                >
                    <p class="page-site-audit__error-message">{{ errorMessage }}</p>
                    <div class="page-site-audit__actions page-site-audit__actions--center">
                        <button
                            type="button"
                            class="btn btn-primary"
                            @click="runAudit"
                        >
                            {{ t('retry') }}
                        </button>
                        <a :href="t('home_url')" class="btn btn-secondary">{{ t('back_home') }}</a>
                    </div>
                </div>

                <template v-else>
                    <div class="page-site-audit__sections">
                        <section
                            v-for="group in checkItems"
                            :key="group.section"
                            class="page-site-audit__panel page-site-audit__section"
                        >
                            <h2 class="page-site-audit__section-title">
                                <span class="page-site-audit__section-icon" aria-hidden="true">
                                    <svg
                                        v-if="group.icon === 'server'"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <rect x="3" y="4" width="18" height="6" rx="1.5" stroke="currentColor" stroke-width="1.75"/>
                                        <rect x="3" y="14" width="18" height="6" rx="1.5" stroke="currentColor" stroke-width="1.75"/>
                                        <circle cx="7" cy="7" r="1" fill="currentColor"/>
                                        <circle cx="7" cy="17" r="1" fill="currentColor"/>
                                    </svg>
                                    <svg
                                        v-else-if="group.icon === 'indexing'"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <circle cx="11" cy="11" r="6.25" stroke="currentColor" stroke-width="1.75"/>
                                        <path d="m16 16 4.25 4.25" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                                        <path d="M9 11h4M11 9v4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                                    </svg>
                                    <svg
                                        v-else-if="group.icon === 'content'"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <path d="M7 4.75h10A1.25 1.25 0 0 1 18.25 6v12A1.25 1.25 0 0 1 17 19.25H7A1.25 1.25 0 0 1 5.75 18V6A1.25 1.25 0 0 1 7 4.75Z" stroke="currentColor" stroke-width="1.75"/>
                                        <path d="M8.5 8.5h7M8.5 12h7M8.5 15.5h4.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                                    </svg>
                                    <svg
                                        v-else
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <path d="M8 7h11M8 12h11M8 17h7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                                        <path d="M4.5 7h.01M4.5 12h.01M4.5 17h.01" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                    </svg>
                                </span>
                                <span>{{ group.section }}</span>
                            </h2>
                            <ul class="page-site-audit__list">
                                <li
                                    v-for="item in group.items"
                                    :key="item.label"
                                    class="page-site-audit__row"
                                >
                                    <div class="page-site-audit__row-label">
                                        <span
                                            class="page-site-audit__status"
                                            :class="item.ok
                                                ? 'page-site-audit__status--ok'
                                                : 'page-site-audit__status--miss'"
                                            aria-hidden="true"
                                        >
                                            <svg
                                                v-if="item.ok"
                                                viewBox="0 0 16 16"
                                                fill="none"
                                                xmlns="http://www.w3.org/2000/svg"
                                            >
                                                <path
                                                    d="M3.5 8.2 6.4 11l6.1-6.5"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                />
                                            </svg>
                                            <svg
                                                v-else
                                                viewBox="0 0 16 16"
                                                fill="none"
                                                xmlns="http://www.w3.org/2000/svg"
                                            >
                                                <path
                                                    d="M4.5 4.5 11.5 11.5M11.5 4.5 4.5 11.5"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                    stroke-linecap="round"
                                                />
                                            </svg>
                                        </span>
                                        <span class="page-site-audit__row-title">{{ item.label }}</span>
                                    </div>
                                    <div class="page-site-audit__row-content">
                                        <div
                                            v-if="item.responseTime"
                                            class="page-site-audit__rt"
                                            :data-rating="item.responseTime.rating"
                                        >
                                            <p class="page-site-audit__rt-summary">
                                                <span
                                                    class="page-site-audit__rt-label"
                                                    :class="`page-site-audit__rt-label--${item.responseTime.rating}`"
                                                >{{ item.responseTime.label }}</span>
                                                <span class="page-site-audit__rt-ms">
                                                    {{ item.responseTime.ms }} {{ t('ms') }}
                                                </span>
                                            </p>
                                            <div
                                                class="page-site-audit__rt-bar"
                                                role="img"
                                                :aria-label="`${item.responseTime.label}: ${item.responseTime.ms} ${t('ms')}`"
                                            >
                                                <div class="page-site-audit__rt-track">
                                                    <div
                                                        class="page-site-audit__rt-green"
                                                        :style="{ width: `${item.responseTime.greenPercent}%` }"
                                                    ></div>
                                                    <div
                                                        class="page-site-audit__rt-yellow"
                                                        :style="{
                                                            left: `${item.responseTime.greenPercent}%`,
                                                            width: `${item.responseTime.yellowPercent - item.responseTime.greenPercent}%`,
                                                        }"
                                                    ></div>
                                                    <div
                                                        class="page-site-audit__rt-marker"
                                                        :style="{ left: `${item.responseTime.percent}%` }"
                                                    >
                                                        <span class="page-site-audit__rt-marker-dot"></span>
                                                    </div>
                                                </div>
                                                <div class="page-site-audit__rt-scale" aria-hidden="true">
                                                    <span>0</span>
                                                    <span
                                                        class="page-site-audit__rt-scale-mark page-site-audit__rt-scale-mark--green"
                                                        :style="{ left: `${item.responseTime.greenPercent}%` }"
                                                    >600</span>
                                                    <span
                                                        class="page-site-audit__rt-scale-mark page-site-audit__rt-scale-mark--yellow"
                                                        :style="{ left: `${item.responseTime.yellowPercent}%` }"
                                                    >1200</span>
                                                    <span>2000 {{ t('ms') }}</span>
                                                </div>
                                            </div>
                                            <p class="page-site-audit__rt-hint">{{ item.responseTime.hint }}</p>
                                        </div>
                                        <p
                                            v-if="item.value"
                                            class="page-site-audit__row-value"
                                        >{{ item.value }}</p>
                                        <div
                                            v-if="item.lengthRating"
                                            class="page-site-audit__length"
                                            :data-rating="item.lengthRating.rating"
                                        >
                                            <p class="page-site-audit__length-summary">
                                                <span
                                                    class="page-site-audit__length-label"
                                                    :class="`page-site-audit__length-label--${item.lengthRating.rating}`"
                                                >{{ item.lengthRating.label }}</span>
                                                <span class="page-site-audit__length-meta">{{ item.lengthRating.meta }}</span>
                                            </p>
                                            <p class="page-site-audit__length-hint">{{ item.lengthRating.hint }}</p>
                                        </div>
                                        <p
                                            v-if="item.detail"
                                            class="page-site-audit__row-detail"
                                        >{{ item.detail }}</p>
                                        <div
                                            v-if="item.headings?.length"
                                            class="page-site-audit__headings"
                                        >
                                            <table class="page-site-audit__headings-table">
                                                <thead>
                                                    <tr>
                                                        <th>{{ t('content_heading_type') }}</th>
                                                        <th>{{ t('content_heading_text') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="(heading, index) in item.headings"
                                                        :key="`${heading.level}-${index}`"
                                                    >
                                                        <td>H{{ heading.level }}</td>
                                                        <td>{{ heading.text }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div
                                            v-if="item.schema"
                                            class="page-site-audit__schema"
                                        >
                                            <ul
                                                v-if="item.schema.formats.length"
                                                class="page-site-audit__schema-stats"
                                            >
                                                <li
                                                    v-for="format in item.schema.formats"
                                                    :key="format.key"
                                                    class="page-site-audit__schema-stat"
                                                >
                                                    <span class="page-site-audit__schema-stat-label">{{ format.label }}</span>
                                                    <span class="page-site-audit__schema-stat-count">{{ format.count }}</span>
                                                </li>
                                            </ul>
                                            <ul
                                                v-if="item.schema.items.length"
                                                class="page-site-audit__schema-list"
                                            >
                                                <li
                                                    v-for="(schemaItem, index) in item.schema.items"
                                                    :key="`${schemaItem.type}-${schemaItem.format}-${index}`"
                                                    class="page-site-audit__schema-item"
                                                >
                                                    <div class="page-site-audit__schema-item-head">
                                                        <span class="page-site-audit__schema-item-type">{{ schemaItem.type }}</span>
                                                        <span class="page-site-audit__schema-item-format">{{
                                                            schemaItem.format === 'json-ld' ? 'JSON-LD' : 'Microdata'
                                                        }}</span>
                                                    </div>
                                                    <p
                                                        v-if="schemaItem.name"
                                                        class="page-site-audit__schema-item-name"
                                                    >{{ schemaItem.name }}</p>
                                                    <p
                                                        v-if="schemaItem.url"
                                                        class="page-site-audit__schema-item-url"
                                                    >{{ schemaItem.url }}</p>
                                                </li>
                                            </ul>
                                        </div>
                                        <div
                                            v-if="item.og"
                                            class="page-site-audit__og-card"
                                        >
                                            <img
                                                v-if="item.og.image_url"
                                                :src="item.og.image_url"
                                                alt=""
                                                class="page-site-audit__og-image"
                                            >
                                            <div class="page-site-audit__og-body">
                                                <p class="page-site-audit__og-title">
                                                    {{ item.og.title || '—' }}
                                                </p>
                                                <p
                                                    v-if="item.og.description"
                                                    class="page-site-audit__og-description"
                                                >
                                                    {{ item.og.description }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </section>
                    </div>

                    <footer class="page-site-audit__panel page-site-audit__next">
                        <div class="page-site-audit__next-glow" aria-hidden="true"></div>
                        <div class="page-site-audit__next-inner">
                            <p class="page-site-audit__next-eyebrow">{{ t('footer_label') }}</p>
                            <h2 class="page-site-audit__next-title">{{ t('footer_title') }}</h2>
                            <p class="page-site-audit__next-lead">{{ t('footer_lead') }}</p>
                            <ul class="page-site-audit__next-points">
                                <li>{{ t('footer_point_1') }}</li>
                                <li>{{ t('footer_point_2') }}</li>
                                <li>{{ t('footer_point_3') }}</li>
                            </ul>
                            <div class="page-site-audit__actions">
                                <a
                                    :href="t('register_url')"
                                    class="btn btn-primary btn-lg page-site-audit__next-cta"
                                    data-public-auth-cta="register"
                                    data-public-auth-keep
                                    :data-label-register="t('cta_register')"
                                    :data-label-authed="t('cta_register')"
                                >{{ t('cta_register') }}</a>
                            </div>
                        </div>
                    </footer>
                </template>
            </div>
        </section>
    </div>
</template>
