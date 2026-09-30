<template>
    <div
        class="metrics-coverage-timeline"
        :aria-label="t('shared.coverage.aria')"
    >
        <AppLoader
            v-if="loading"
            block
            :label="t('shared.coverage.loading')"
        />
        <p
            v-else-if="emptyMessage"
            class="metrics-coverage-timeline__empty"
        >
            {{ emptyMessage }}
        </p>
        <template v-else>
            <div class="metrics-coverage-timeline__axis">
                <span>{{ formatDate(axisMin) }}</span>
                <span>{{ formatDate(axisMax) }}</span>
            </div>

            <ul class="metrics-coverage-timeline__list">
                <li
                    v-for="row in rows"
                    :key="row.key"
                    class="metrics-coverage-timeline__row"
                >
                    <span class="metrics-coverage-timeline__label">{{ row.label }}</span>
                    <div
                        class="metrics-coverage-timeline__track"
                        role="img"
                        :aria-label="`${row.label}: ${row.periodLabel}`"
                    >
                        <span
                            class="metrics-coverage-timeline__bar"
                            :style="row.barStyle"
                            aria-hidden="true"
                        />
                    </div>
                    <span class="metrics-coverage-timeline__period">{{ row.periodLabel }}</span>
                </li>
            </ul>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from '../i18n';
import AppLoader from './AppLoader.vue';

const { t, intlLocale } = useI18n();

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    selectedFrom: {
        type: String,
        default: '',
    },
    selectedTo: {
        type: String,
        default: '',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    empty: {
        type: Boolean,
        default: false,
    },
});

const MS_PER_DAY = 24 * 60 * 60 * 1000;

function parseDay(value) {
    if (!value) {
        return null;
    }

    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.getTime();
}

function toDateString(ms) {
    const date = new Date(ms);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
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

function periodDays(from, to) {
    const fromMs = parseDay(from);
    const toMs = parseDay(to);

    if (fromMs === null || toMs === null || toMs < fromMs) {
        return null;
    }

    return Math.round((toMs - fromMs) / MS_PER_DAY) + 1;
}

function pluralDays(count) {
    const category = new Intl.PluralRules(intlLocale()).select(Math.abs(count));

    return t(`common.days.${category}`);
}

function formatPeriod(from, to) {
    const fromLabel = formatDate(from);
    const toLabel = formatDate(to);
    const days = periodDays(from, to);

    if (days === null) {
        return `${fromLabel} — ${toLabel}`;
    }

    if (from === to) {
        return `${fromLabel} (1 ${pluralDays(1)})`;
    }

    return `${fromLabel} — ${toLabel} (${days} ${pluralDays(days)})`;
}

function rangeStyle(fromMs, toMs, axisMinMs, axisSpan) {
    if (fromMs === null || toMs === null || axisSpan <= 0) {
        return null;
    }

    const inclusiveEnd = toMs + MS_PER_DAY;
    const left = ((fromMs - axisMinMs) / axisSpan) * 100;
    const width = ((inclusiveEnd - fromMs) / axisSpan) * 100;

    return {
        left: `${Math.max(0, Math.min(100, left))}%`,
        width: `${Math.max(0.8, Math.min(100 - Math.max(0, left), width))}%`,
    };
}

function intersectRange(fromMs, toMs, selectedFromMs, selectedToMs) {
    if (
        fromMs === null
        || toMs === null
        || selectedFromMs === null
        || selectedToMs === null
        || selectedToMs < selectedFromMs
        || toMs < selectedFromMs
        || fromMs > selectedToMs
    ) {
        return null;
    }

    return {
        fromMs: Math.max(fromMs, selectedFromMs),
        toMs: Math.min(toMs, selectedToMs),
    };
}

const selectedBounds = computed(() => {
    const fromMs = parseDay(props.selectedFrom);
    const toMs = parseDay(props.selectedTo);

    if (fromMs === null || toMs === null || toMs < fromMs) {
        return null;
    }

    return {
        fromMs,
        toMs,
        span: (toMs - fromMs) + MS_PER_DAY,
        minDate: props.selectedFrom,
        maxDate: props.selectedTo,
    };
});

const overlappingItems = computed(() => {
    const selected = selectedBounds.value;

    if (!selected) {
        return [];
    }

    return props.items
        .map((item) => {
            const intersection = intersectRange(
                parseDay(item?.from),
                parseDay(item?.to),
                selected.fromMs,
                selected.toMs,
            );

            if (!intersection) {
                return null;
            }

            return {
                key: item.key,
                label: item.label,
                from: toDateString(intersection.fromMs),
                to: toDateString(intersection.toMs),
                fromMs: intersection.fromMs,
                toMs: intersection.toMs,
            };
        })
        .filter(Boolean);
});

const emptyMessage = computed(() => {
    if (props.empty || !props.items.length) {
        return t('shared.coverage.noData');
    }

    if (!selectedBounds.value) {
        return t('shared.coverage.setPeriod');
    }

    if (!overlappingItems.value.length) {
        return t('shared.coverage.noPeriodData');
    }

    return '';
});

const axisMin = computed(() => selectedBounds.value?.minDate || '');
const axisMax = computed(() => selectedBounds.value?.maxDate || '');

const rows = computed(() => {
    const selected = selectedBounds.value;

    if (!selected) {
        return [];
    }

    return overlappingItems.value.map((item) => ({
        key: item.key,
        label: item.label,
        periodLabel: formatPeriod(item.from, item.to),
        barStyle: rangeStyle(item.fromMs, item.toMs, selected.fromMs, selected.span),
    }));
});
</script>
