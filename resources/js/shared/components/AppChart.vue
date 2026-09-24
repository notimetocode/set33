<template>
    <figure class="app-chart">
        <figcaption
            v-if="title"
            class="app-chart__title"
        >
            {{ title }}
        </figcaption>
        <div class="app-chart__canvas-wrap">
            <canvas ref="canvasEl" />
        </div>
    </figure>
</template>

<script setup>
import {
    Chart,
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PieController,
    PointElement,
    Tooltip,
} from 'chart.js';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

Chart.register(
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PieController,
    PointElement,
    Tooltip,
);

const BRAND = '#0B7A4B';
const BRAND_SOFT = 'rgba(11, 122, 75, 0.18)';
const SERIES_COLORS = [
    BRAND,
    '#3DDC97',
    '#065F3C',
    '#5C6670',
    '#86B8A0',
    '#2FCE89',
];

const props = defineProps({
    chart: {
        type: Object,
        required: true,
    },
});

const canvasEl = ref(null);
let chartInstance = null;

const title = computed(() => props.chart?.title || '');

function buildConfig(chart) {
    const type = chart.type === 'pie' ? 'pie' : chart.type;
    const labels = Array.isArray(chart.labels) ? chart.labels : [];
    const series = Array.isArray(chart.series) ? chart.series : [];

    if (type === 'pie') {
        const values = series[0]?.values || [];

        return {
            type: 'pie',
            data: {
                labels,
                datasets: [
                    {
                        data: values,
                        backgroundColor: labels.map((_, index) => SERIES_COLORS[index % SERIES_COLORS.length]),
                        borderColor: '#ffffff',
                        borderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: { family: 'Instrument Sans, system-ui, sans-serif', size: 12 },
                            color: '#5C6670',
                        },
                    },
                    tooltip: {
                        titleFont: { family: 'Instrument Sans, system-ui, sans-serif' },
                        bodyFont: { family: 'Instrument Sans, system-ui, sans-serif' },
                    },
                },
            },
        };
    }

    return {
        type,
        data: {
            labels,
            datasets: series.map((serie, index) => {
                const color = SERIES_COLORS[index % SERIES_COLORS.length];

                return {
                    label: serie.name,
                    data: serie.values,
                    borderColor: color,
                    backgroundColor: type === 'line' ? BRAND_SOFT : color,
                    borderWidth: 2,
                    tension: 0.25,
                    fill: type === 'line' && series.length === 1,
                    pointRadius: type === 'line' ? 3 : 0,
                    pointHoverRadius: 5,
                    borderRadius: type === 'bar' ? 4 : 0,
                };
            }),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: series.length > 1,
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        font: { family: 'Instrument Sans, system-ui, sans-serif', size: 12 },
                        color: '#5C6670',
                    },
                },
                tooltip: {
                    titleFont: { family: 'Instrument Sans, system-ui, sans-serif' },
                    bodyFont: { family: 'Instrument Sans, system-ui, sans-serif' },
                },
            },
            scales: {
                x: {
                    grid: { color: 'rgba(18, 21, 26, 0.06)' },
                    ticks: {
                        color: '#5C6670',
                        font: { family: 'Instrument Sans, system-ui, sans-serif', size: 11 },
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 8,
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(18, 21, 26, 0.06)' },
                    ticks: {
                        color: '#5C6670',
                        font: { family: 'Instrument Sans, system-ui, sans-serif', size: 11 },
                    },
                },
            },
        },
    };
}

function renderChart() {
    if (!canvasEl.value || !props.chart) {
        return;
    }

    if (chartInstance) {
        chartInstance.destroy();
        chartInstance = null;
    }

    chartInstance = new Chart(canvasEl.value, buildConfig(props.chart));
}

onMounted(renderChart);

watch(
    () => props.chart,
    () => renderChart(),
    { deep: true },
);

onBeforeUnmount(() => {
    if (chartInstance) {
        chartInstance.destroy();
        chartInstance = null;
    }
});
</script>
