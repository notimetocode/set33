import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import { setLocale } from '../shared/i18n';

// Admin panel is Russian-only; this keeps shared components (loader, modal, …) in Russian.
setLocale('ru');

createApp(App).use(router).mount('#admin-app');
