<template>
    <div class="app-ai-report-body">
        <template
            v-for="(segment, index) in segments"
            :key="`${segment.kind}-${index}-${segment.chartId || ''}`"
        >
            <AppMarkdown
                v-if="segment.kind === 'markdown' && segment.source"
                :source="segment.source"
            />
            <AppChart
                v-else-if="segment.kind === 'chart' && segment.chart"
                :chart="segment.chart"
            />
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import AppChart from './AppChart.vue';
import AppMarkdown from './AppMarkdown.vue';

const props = defineProps({
    source: {
        type: String,
        default: '',
    },
    charts: {
        type: Array,
        default: () => [],
    },
});

const chartsById = computed(() => {
    const map = new Map();

    for (const chart of props.charts || []) {
        if (chart?.id && !map.has(chart.id)) {
            map.set(chart.id, chart);
        }
    }

    return map;
});

const segments = computed(() => {
    const text = props.source || '';
    const pattern = /\{\{chart:([a-z0-9_]+)\}\}/g;
    const result = [];
    let lastIndex = 0;
    let match = pattern.exec(text);

    while (match) {
        const before = text.slice(lastIndex, match.index);

        if (before.trim()) {
            result.push({ kind: 'markdown', source: before });
        }

        const chartId = match[1];
        const chart = chartsById.value.get(chartId);

        if (chart) {
            result.push({ kind: 'chart', chartId, chart });
        }

        lastIndex = match.index + match[0].length;
        match = pattern.exec(text);
    }

    const after = text.slice(lastIndex);

    if (after.trim() || result.length === 0) {
        result.push({ kind: 'markdown', source: after });
    }

    return result;
});
</script>
