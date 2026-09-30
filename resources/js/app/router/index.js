import { createRouter, createWebHistory } from 'vue-router';
import { isAuthenticated } from '../api/auth';
import { dynamicBreadcrumbLabel } from '../../shared/dynamicBreadcrumbLabel';
import { t } from '../../shared/i18n';
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
                        breadcrumbs: () => [{ label: t('breadcrumbs.profile') }],
                    },
                },
                {
                    path: 'sites',
                    name: 'sites.index',
                    component: SitesIndexView,
                    meta: {
                        breadcrumbs: () => [{ label: t('breadcrumbs.sites') }],
                    },
                },
                {
                    path: 'sites/create',
                    name: 'sites.create',
                    component: SitesFormView,
                    meta: {
                        breadcrumbs: () => [
                            { label: t('breadcrumbs.sites'), name: 'sites.index' },
                            { label: t('breadcrumbs.new') },
                        ],
                    },
                },
                {
                    path: 'sites/:id',
                    name: 'sites.show',
                    component: SitesShowView,
                    meta: {
                        breadcrumbs: () => [
                            { label: t('breadcrumbs.sites'), name: 'sites.index' },
                            { label: dynamicBreadcrumbLabel.value || t('breadcrumbs.site') },
                        ],
                    },
                },
                {
                    path: 'sites/:id/edit',
                    name: 'sites.edit',
                    component: SitesFormView,
                    meta: {
                        breadcrumbs: () => [
                            { label: t('breadcrumbs.sites'), name: 'sites.index' },
                            { label: t('breadcrumbs.edit') },
                        ],
                    },
                },
                {
                    path: 'ai-services',
                    name: 'ai-services.index',
                    component: AiServicesIndexView,
                    meta: {
                        breadcrumbs: () => [{ label: t('breadcrumbs.aiServices') }],
                    },
                },
                {
                    path: 'ai-services/create',
                    name: 'ai-services.create',
                    component: AiServicesFormView,
                    meta: {
                        breadcrumbs: () => [
                            { label: t('breadcrumbs.aiServices'), name: 'ai-services.index' },
                            { label: t('breadcrumbs.new') },
                        ],
                    },
                },
                {
                    path: 'ai-services/:id',
                    name: 'ai-services.show',
                    component: AiServicesShowView,
                    meta: {
                        breadcrumbs: () => [
                            { label: t('breadcrumbs.aiServices'), name: 'ai-services.index' },
                            { label: t('breadcrumbs.test') },
                        ],
                    },
                },
                {
                    path: 'ai-services/:id/edit',
                    name: 'ai-services.edit',
                    component: AiServicesFormView,
                    meta: {
                        breadcrumbs: () => [
                            { label: t('breadcrumbs.aiServices'), name: 'ai-services.index' },
                            { label: t('breadcrumbs.edit') },
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
