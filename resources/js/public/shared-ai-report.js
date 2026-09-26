import { createApp } from 'vue';
import AppAiReportBody from '../shared/components/AppAiReportBody.vue';

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

    createApp(AppAiReportBody, {
        source: payload.reply || '',
        charts: Array.isArray(payload.charts) ? payload.charts : [],
    }).mount(root);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountSharedAiReport);
} else {
    mountSharedAiReport();
}
