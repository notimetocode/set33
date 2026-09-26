<template>
    <div class="page-shared-ai-report__content">
        <div class="page-shared-ai-report__toolbar">
            <button
                type="button"
                class="btn btn-outline-primary btn-sm page-shared-ai-report__pdf-btn"
                :disabled="exporting || !source"
                @click="onDownloadPdf"
            >
                <FontAwesomeIcon
                    :icon="['fas', 'file-pdf']"
                    aria-hidden="true"
                />
                <span>{{ exporting ? 'Сохранение…' : 'Скачать PDF' }}</span>
            </button>
        </div>

        <div
            ref="exportEl"
            class="page-shared-ai-report__export"
        >
            <AppAiReportBody
                :source="source"
                :charts="charts"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import AppAiReportBody from '../shared/components/AppAiReportBody.vue';
import { FontAwesomeIcon } from '../shared/icons';
import {
    buildAiReportPdfFilename,
    downloadAiReportPdf,
} from '../shared/exportAiReportPdf';

const props = defineProps({
    source: {
        type: String,
        default: '',
    },
    charts: {
        type: Array,
        default: () => [],
    },
    siteName: {
        type: String,
        default: '',
    },
    periodFrom: {
        type: String,
        default: '',
    },
    periodTo: {
        type: String,
        default: '',
    },
    formedAt: {
        type: String,
        default: '',
    },
});

const exportEl = ref(null);
const exporting = ref(false);

const pdfSubtitle = computed(() => {
    const parts = [];

    if (props.periodFrom || props.periodTo) {
        parts.push(`Период: ${formatDate(props.periodFrom) || '—'} — ${formatDate(props.periodTo) || '—'}`);
    }

    if (props.formedAt) {
        parts.push(`сформирован ${formatDateTime(props.formedAt)}`);
    }

    return parts.join(' · ');
});

/**
 * @param {string} value
 * @returns {string}
 */
function formatDate(value) {
    if (!value) {
        return '';
    }

    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        const parsed = new Date(value);

        if (Number.isNaN(parsed.getTime())) {
            return String(value);
        }

        return parsed.toLocaleDateString('ru-RU');
    }

    return date.toLocaleDateString('ru-RU');
}

/**
 * @param {string} value
 * @returns {string}
 */
function formatDateTime(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

async function onDownloadPdf() {
    if (!exportEl.value || exporting.value || !props.source) {
        return;
    }

    exporting.value = true;

    try {
        await downloadAiReportPdf({
            element: exportEl.value,
            filename: buildAiReportPdfFilename({
                siteName: props.siteName,
                periodFrom: props.periodFrom,
                periodTo: props.periodTo,
            }),
            title: props.siteName || 'AI-отчёт',
            subtitle: pdfSubtitle.value,
        });
    } catch (error) {
        window.alert(error?.message || 'Не удалось сохранить PDF');
    } finally {
        exporting.value = false;
    }
}
</script>
