import { createApp } from 'vue';
import SiteAuditApp from './SiteAuditApp.vue';

function mountSiteAudit() {
    const root = document.querySelector('[data-site-audit]');
    const dataEl = document.getElementById('site-audit-bootstrap');

    if (!root || !dataEl) {
        return;
    }

    let payload = {};

    try {
        payload = JSON.parse(dataEl.textContent || '{}');
    } catch {
        payload = {};
    }

    createApp(SiteAuditApp, {
        initialUrl: typeof payload.url === 'string' ? payload.url : '',
        apiUrl: typeof payload.apiUrl === 'string' ? payload.apiUrl : '/api/public/site-audits',
        i18n: payload.i18n && typeof payload.i18n === 'object' ? payload.i18n : {},
    }).mount(root);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountSiteAudit);
} else {
    mountSiteAudit();
}
