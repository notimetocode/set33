<script setup>
import { computed } from 'vue';
import { useI18n } from '../../shared/i18n';

const props = defineProps({
    audit: {
        type: Object,
        default: null,
    },
});

const { t } = useI18n();

function yn(value) {
    return value ? t('sites.show.website.audit.yes') : t('sites.show.website.audit.no');
}

function present(value) {
    return value ? t('sites.show.website.audit.present') : t('sites.show.website.audit.missing');
}

function statusCode(code) {
    if (typeof code !== 'number') {
        return '—';
    }

    return t('sites.show.website.audit.statusCode', { code });
}

function fill(template, vars) {
    return Object.entries(vars).reduce(
        (text, [key, value]) => text.replaceAll(`{${key}}`, String(value)),
        template,
    );
}

const sections = computed(() => {
    const data = props.audit;

    if (!data || typeof data !== 'object') {
        return [];
    }

    const ssl = data.ssl || {};
    const www = data.www_redirect || {};
    const notFound = data.not_found_page || {};
    const metaRobots = data.meta_robots || {};
    const robots = data.robots_txt || {};
    const sitemap = data.sitemap || {};
    const content = data.content || {};
    const headings = content.headings || {};
    const headingCounts = headings.counts || {};
    const schemaOrg = content.schema_org || {};
    const externalLinks = content.external_links || {};
    const internalLinks = content.internal_links || {};
    const googleAnalytics = data.google_analytics || {};
    const yandexMetrika = data.yandex_metrika || {};

    const formatSsl = () => {
        if (!ssl.enabled) {
            return t('sites.show.website.audit.sslMissing');
        }

        const parts = [ssl.valid
            ? t('sites.show.website.audit.sslOk')
            : t('sites.show.website.audit.sslExpired')];

        if (ssl.issuer) {
            parts.push(fill(t('sites.show.website.audit.sslIssuer'), { issuer: ssl.issuer }));
        }

        if (ssl.valid_to) {
            let expiry = fill(t('sites.show.website.audit.sslValidTo'), { date: ssl.valid_to });

            if (typeof ssl.days_remaining === 'number') {
                expiry += ` ${fill(t('sites.show.website.audit.sslDaysLeft'), { days: ssl.days_remaining })}`;
            }

            parts.push(expiry);
        }

        return parts.join(' · ');
    };

    /** @type {Array<{ key: string, title: string, rows: Array<{ key: string, label: string, value: string, ok?: boolean, href?: string }> }>} */
    const result = [
        {
            key: 'server',
            title: t('sites.show.website.audit.sectionServer'),
            rows: [
                {
                    key: 'http_status',
                    label: t('sites.show.website.audit.httpStatus'),
                    value: statusCode(data.http_status),
                    ok: typeof data.http_status === 'number'
                        && data.http_status >= 200
                        && data.http_status < 400,
                },
                {
                    key: 'response_time',
                    label: t('sites.show.website.audit.responseTime'),
                    value: typeof data.response_time_ms === 'number'
                        ? fill(t('sites.show.website.audit.ms'), { ms: data.response_time_ms })
                        : '—',
                    ok: typeof data.response_time_ms === 'number' && data.response_time_ms <= 600,
                },
                {
                    key: 'ssl',
                    label: t('sites.show.website.audit.ssl'),
                    value: formatSsl(),
                    ok: Boolean(ssl.valid),
                },
                {
                    key: 'https_redirect',
                    label: t('sites.show.website.audit.httpsRedirect'),
                    value: yn(Boolean(data.https_redirect)),
                },
                {
                    key: 'www_redirect',
                    label: t('sites.show.website.audit.wwwRedirect'),
                    value: www.checked
                        ? (www.present
                            ? `${www.from_host} → ${www.to_host}`
                            : t('sites.show.website.audit.missing'))
                        : '—',
                    ok: Boolean(www.present),
                },
                {
                    key: 'not_found',
                    label: t('sites.show.website.audit.notFoundStatus'),
                    value: notFound.checked ? statusCode(notFound.http_status) : '—',
                    ok: Boolean(notFound.returns_404),
                },
                {
                    key: 'not_found_home',
                    label: t('sites.show.website.audit.notFoundHomeLink'),
                    value: notFound.checked ? yn(Boolean(notFound.home_link_present)) : '—',
                    ok: Boolean(notFound.home_link_present),
                },
                {
                    key: 'ip',
                    label: t('sites.show.website.audit.ip'),
                    value: data.ip_address || t('sites.show.website.audit.missing'),
                    ok: Boolean(data.ip_address),
                },
            ],
        },
        {
            key: 'indexing',
            title: t('sites.show.website.audit.sectionIndexing'),
            rows: [
                {
                    key: 'meta_robots',
                    label: t('sites.show.website.audit.metaRobots'),
                    value: metaRobots.content
                        || (metaRobots.indexing_allowed === false
                            ? t('sites.show.website.audit.no')
                            : t('sites.show.website.audit.yes')),
                    ok: metaRobots.indexing_allowed !== false,
                },
                {
                    key: 'robots',
                    label: t('sites.show.website.audit.robots'),
                    value: present(Boolean(robots.present)),
                    ok: Boolean(robots.present),
                },
                {
                    key: 'robots_indexing',
                    label: t('sites.show.website.audit.robotsIndexing'),
                    value: robots.present
                        ? yn(robots.indexing_allowed !== false)
                        : '—',
                    ok: robots.indexing_allowed !== false,
                },
                {
                    key: 'sitemap',
                    label: t('sites.show.website.audit.sitemap'),
                    value: sitemap.present
                        ? (sitemap.url || present(true))
                        : present(false),
                    ok: Boolean(sitemap.present),
                    href: sitemap.present && sitemap.url ? sitemap.url : undefined,
                },
            ],
        },
        {
            key: 'content',
            title: t('sites.show.website.audit.sectionContent'),
            rows: [
                {
                    key: 'headings',
                    label: t('sites.show.website.audit.headings'),
                    value: fill(t('sites.show.website.audit.headingsSummary'), {
                        h1: headingCounts.h1 || 0,
                        h2: headingCounts.h2 || 0,
                        h3: headingCounts.h3 || 0,
                        h4: headingCounts.h4 || 0,
                        h5: headingCounts.h5 || 0,
                        h6: headingCounts.h6 || 0,
                    }),
                    ok: (headingCounts.h1 || 0) === 1,
                },
                {
                    key: 'text_length',
                    label: t('sites.show.website.audit.textLength'),
                    value: fill(t('sites.show.website.audit.chars'), { count: content.text_length || 0 }),
                    ok: (content.text_length || 0) > 0,
                },
                {
                    key: 'word_count',
                    label: t('sites.show.website.audit.wordCount'),
                    value: fill(t('sites.show.website.audit.words'), { count: content.word_count || 0 }),
                    ok: (content.word_count || 0) > 0,
                },
                {
                    key: 'nausea',
                    label: t('sites.show.website.audit.nausea'),
                    value: fill(t('sites.show.website.audit.nauseaValue'), { value: content.nausea || 0 }),
                    ok: (content.nausea || 0) > 0 && (content.nausea || 0) <= 10,
                },
                {
                    key: 'html_size',
                    label: t('sites.show.website.audit.htmlSize'),
                    value: fill(t('sites.show.website.audit.htmlSizeValue'), {
                        size: Math.max(0, Math.round((content.html_size_bytes || 0) / 1024)),
                    }),
                    ok: (content.html_size_bytes || 0) > 0,
                },
                {
                    key: 'external_links',
                    label: t('sites.show.website.audit.externalLinks'),
                    value: fill(t('sites.show.website.audit.links'), {
                        total: externalLinks.total || 0,
                        indexable: externalLinks.indexable || 0,
                    }),
                },
                {
                    key: 'internal_links',
                    label: t('sites.show.website.audit.internalLinks'),
                    value: fill(t('sites.show.website.audit.links'), {
                        total: internalLinks.total || 0,
                        indexable: internalLinks.indexable || 0,
                    }),
                    ok: (internalLinks.total || 0) > 0,
                },
                {
                    key: 'schema',
                    label: t('sites.show.website.audit.schemaOrg'),
                    value: schemaOrg.present
                        ? (Array.isArray(schemaOrg.types) && schemaOrg.types.length
                            ? schemaOrg.types.join(', ')
                            : present(true))
                        : present(false),
                    ok: Boolean(schemaOrg.present),
                },
                {
                    key: 'adult',
                    label: t('sites.show.website.audit.adultContent'),
                    value: content.adult_content
                        ? t('sites.show.website.audit.adultYes')
                        : t('sites.show.website.audit.adultNo'),
                    ok: !content.adult_content,
                },
            ],
        },
        {
            key: 'metadata',
            title: t('sites.show.website.audit.sectionMetadata'),
            rows: [
                {
                    key: 'viewport',
                    label: t('sites.show.website.audit.viewport'),
                    value: data.viewport_present ? (data.viewport || present(true)) : present(false),
                    ok: Boolean(data.viewport_present),
                },
                {
                    key: 'charset',
                    label: t('sites.show.website.audit.charset'),
                    value: data.charset || present(false),
                    ok: Boolean(data.charset),
                },
                {
                    key: 'ga',
                    label: t('sites.show.website.audit.googleAnalytics'),
                    value: googleAnalytics.present
                        ? (Array.isArray(googleAnalytics.ids) && googleAnalytics.ids.length
                            ? googleAnalytics.ids.join(', ')
                            : present(true))
                        : present(false),
                    ok: Boolean(googleAnalytics.present),
                },
                {
                    key: 'metrika',
                    label: t('sites.show.website.audit.yandexMetrika'),
                    value: yandexMetrika.present
                        ? (Array.isArray(yandexMetrika.counter_ids) && yandexMetrika.counter_ids.length
                            ? yandexMetrika.counter_ids.join(', ')
                            : present(true))
                        : present(false),
                    ok: Boolean(yandexMetrika.present),
                },
                {
                    key: 'favicon',
                    label: t('sites.show.website.audit.favicon'),
                    value: present(Boolean(data.favicon_present)),
                    ok: Boolean(data.favicon_present),
                },
            ],
        },
    ];

    return result;
});
</script>

<template>
    <div
        v-if="sections.length"
        class="site-website-audit"
    >
        <h3 class="site-website-audit__title">
            {{ t('sites.show.website.audit.title') }}
        </h3>

        <section
            v-for="section in sections"
            :key="section.key"
            class="site-website-audit__section"
        >
            <h4 class="site-website-audit__section-title">
                {{ section.title }}
            </h4>

            <dl class="page-app-sites__website-meta site-website-audit__rows">
                <div
                    v-for="row in section.rows"
                    :key="row.key"
                    class="page-app-sites__website-meta-row"
                >
                    <dt>{{ row.label }}</dt>
                    <dd>
                        <span
                            v-if="typeof row.ok === 'boolean'"
                            class="site-website-audit__badge"
                            :class="row.ok ? 'is-ok' : 'is-bad'"
                            aria-hidden="true"
                        />
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
        </section>
    </div>
</template>
