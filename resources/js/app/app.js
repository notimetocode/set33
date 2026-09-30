import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import { getStoredLocale, setLocale } from '../shared/i18n';
import { FontAwesomeIcon } from '../shared/icons';

const browserLanguage = (navigator.language || '').toLowerCase();
const storedLocale = getStoredLocale();

setLocale(storedLocale ?? (browserLanguage.startsWith('ru') ? 'ru' : 'en'));

createApp(App)
    .component('FontAwesomeIcon', FontAwesomeIcon)
    .use(router)
    .mount('#app');
