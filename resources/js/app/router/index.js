import { createRouter, createWebHistory } from 'vue-router';
import { isAuthenticated } from '../api/auth';
import AppLayout from '../layouts/AppLayout.vue';
import LoginView from '../views/LoginView.vue';
import RegisterView from '../views/RegisterView.vue';
import ProfileView from '../views/ProfileView.vue';
import AiServicesIndexView from '../views/ai-services/IndexView.vue';
import AiServicesFormView from '../views/ai-services/FormView.vue';
import AiServicesShowView from '../views/ai-services/ShowView.vue';
import SitesIndexView from '../views/sites/IndexView.vue';
import SitesFormView from '../views/sites/FormView.vue';
import SitesShowView from '../views/sites/ShowView.vue';

const router = createRouter({
    history: createWebHistory('/app'),
    routes: [
        { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
        { path: '/register', name: 'register', component: RegisterView, meta: { guest: true } },
        {
            path: '/',
            component: AppLayout,
            meta: { auth: true },
            children: [
                {
                    path: '',
                    redirect: { name: 'sites.index' },
                },
                {
                    path: 'profile',
                    name: 'profile',
                    component: ProfileView,
                    meta: {
                        breadcrumbs: [{ label: 'Личные данные' }],
                    },
                },
                {
                    path: 'sites',
                    name: 'sites.index',
                    component: SitesIndexView,
                    meta: {
                        breadcrumbs: [{ label: 'Сайты' }],
                    },
                },
                {
                    path: 'sites/create',
                    name: 'sites.create',
                    component: SitesFormView,
                    meta: {
                        breadcrumbs: [
                            { label: 'Сайты', name: 'sites.index' },
                            { label: 'Новый' },
                        ],
                    },
                },
                {
                    path: 'sites/:id',
                    name: 'sites.show',
                    component: SitesShowView,
                    meta: {
                        breadcrumbs: [
                            { label: 'Сайты', name: 'sites.index' },
                            { label: 'Карточка' },
                        ],
                    },
                },
                {
                    path: 'sites/:id/edit',
                    name: 'sites.edit',
                    component: SitesFormView,
                    meta: {
                        breadcrumbs: [
                            { label: 'Сайты', name: 'sites.index' },
                            { label: 'Изменить' },
                        ],
                    },
                },
                {
                    path: 'ai-services',
                    name: 'ai-services.index',
                    component: AiServicesIndexView,
                    meta: {
                        breadcrumbs: [{ label: 'AI-сервисы' }],
                    },
                },
                {
                    path: 'ai-services/create',
                    name: 'ai-services.create',
                    component: AiServicesFormView,
                    meta: {
                        breadcrumbs: [
                            { label: 'AI-сервисы', name: 'ai-services.index' },
                            { label: 'Новый' },
                        ],
                    },
                },
                {
                    path: 'ai-services/:id',
                    name: 'ai-services.show',
                    component: AiServicesShowView,
                    meta: {
                        breadcrumbs: [
                            { label: 'AI-сервисы', name: 'ai-services.index' },
                            { label: 'Тест' },
                        ],
                    },
                },
                {
                    path: 'ai-services/:id/edit',
                    name: 'ai-services.edit',
                    component: AiServicesFormView,
                    meta: {
                        breadcrumbs: [
                            { label: 'AI-сервисы', name: 'ai-services.index' },
                            { label: 'Изменить' },
                        ],
                    },
                },
            ],
        },
        { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
});

router.beforeEach((to) => {
    const authed = isAuthenticated();
    const needsAuth = to.matched.some((record) => record.meta.auth);
    const guestOnly = to.matched.some((record) => record.meta.guest);

    if (needsAuth && !authed) {
        return { name: 'login' };
    }

    if (guestOnly && authed) {
        return { name: 'sites.index' };
    }

    return true;
});

export default router;
