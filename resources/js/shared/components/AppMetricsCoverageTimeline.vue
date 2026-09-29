<template>
    <div
        class="metrics-coverage-timeline"
        aria-label="Периоды выгруженных данных"
    >
        <h3 class="metrics-coverage-timeline__title">Выгруженные данные</h3>

        <AppLoader
            v-if="loading"
            block
            label="Загрузка периодов…"
        />
        <p
            v-else-if="empty || !items.length"
            class="metrics-coverage-timeline__empty"
        >
            Выгруженных данных пока нет.
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
                            v-if="selectedStyle"
                            class="metrics-coverage-timeline__selected"
                            :style="selectedStyle"
                            aria-hidden="true"
                        />
                        <span
                            class="metrics-coverage-timeline__bar"
                            :style="row.barStyle"
                            aria-hidden="true"
                        />
                    </div>
                    <span class="metrics-coverage-timeline__period">{{ row.periodLabel }}</span>
                </li>
            </ul>

            <p
                v-if="selectedStyle"
                class="metrics-coverage-timeline__legend"
            >
                <span
                    class="metrics-coverage-timeline__legend-swatch"
                    aria-hidden="true"
                />
                Выбранный период отчёта
            </p>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import AppLoader from './AppLoader.vue';

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
        return new Date(`${value}T00:00:00`).toLocaleDateString('ru-RU');
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

function formatPeriod(from, to) {
    const fromLabel = formatDate(from);
    const toLabel = formatDate(to);
    const days = periodDays(from, to);

    if (days === null) {
        return `${fromLabel} — ${toLabel}`;
    }

    if (from === to) {
        return `${fromLabel} (1 день)`;
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

const axisBounds = computed(() => {
    const dayValues = [];

    for (const item of props.items) {
        const fromMs = parseDay(item?.from);
        const toMs = parseDay(item?.to);

        if (fromMs !== null) {
            dayValues.push(fromMs);
        }

        if (toMs !== null) {
            dayValues.push(toMs);
        }
    }

    const selectedFromMs = parseDay(props.selectedFrom);
    const selectedToMs = parseDay(props.selectedTo);

    if (
        selectedFromMs !== null
        && selectedToMs !== null
        && selectedToMs >= selectedFromMs
    ) {
        dayValues.push(selectedFromMs, selectedToMs);
    }

    if (!dayValues.length) {
        return null;
    }

    const minMs = Math.min(...dayValues);
    const maxMs = Math.max(...dayValues);

    return {
        minMs,
        maxMs,
        span: (maxMs - minMs) + MS_PER_DAY,
        minDate: toDateString(minMs),
        maxDate: toDateString(maxMs),
    };
});

const axisMin = computed(() => axisBounds.value?.minDate || '');
const axisMax = computed(() => axisBounds.value?.maxDate || '');

const selectedStyle = computed(() => {
    const bounds = axisBounds.value;

    if (!bounds) {
        return null;
    }

    return rangeStyle(
        parseDay(props.selectedFrom),
        parseDay(props.selectedTo),
        bounds.minMs,
        bounds.span,
    );
});

const rows = computed(() => {
    const bounds = axisBounds.value;

    if (!bounds) {
        return [];
    }

    return props.items
        .map((item) => {
            const fromMs = parseDay(item?.from);
            const toMs = parseDay(item?.to);
            const barStyle = rangeStyle(fromMs, toMs, bounds.minMs, bounds.span);

            if (!barStyle) {
                return null;
            }

            return {
                key: item.key,
                label: item.label,
                periodLabel: formatPeriod(item.from, item.to),
                barStyle,
            };
        })
        .filter(Boolean);
});
</script>
