import { createApp } from 'vue';
import SharedAiReportApp from './SharedAiReportApp.vue';

function mountSharedAiReport() {
    const root = document.querySelector('[data-shared-ai-report]');
    const dataEl = document.getElementById('shared-ai-report-data');

    if (!root || !dataEl) {
        return;
    }

    let payload = {};

    try {
        payload = JSON.parse(dataEl.textContent || '{}');
    } catch {
        payload = {};
    }

    createApp(SharedAiReportApp, {
        source: payload.reply || '',
        charts: Array.isArray(payload.charts) ? payload.charts : [],
        siteName: payload.site?.name || '',
        periodFrom: payload.period?.from || '',
        periodTo: payload.period?.to || '',
        formedAt: payload.created_at || '',
    }).mount(root);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountSharedAiReport);
} else {
    mountSharedAiReport();
}
