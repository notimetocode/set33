import { createRouter, createWebHistory } from 'vue-router';
import { isAuthenticated } from '../api/auth';
import LoginView from '../views/LoginView.vue';
import DashboardView from '../views/DashboardView.vue';
import UsersIndexView from '../views/users/IndexView.vue';

const router = createRouter({
    history: createWebHistory('/admin'),
    routes: [
        { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
        {
            path: '/',
            name: 'dashboard',
            component: DashboardView,
            meta: {
                auth: true,
                breadcrumbs: [{ label: 'Обзор' }],
            },
        },
        {
            path: '/users',
            name: 'users.index',
            component: UsersIndexView,
            meta: {
                auth: true,
                breadcrumbs: [
                    { label: 'Обзор', name: 'dashboard' },
                    { label: 'Пользователи' },
                ],
            },
        },
        { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
});

router.beforeEach((to) => {
    const authed = isAuthenticated();

    if (to.meta.auth && !authed) {
        return { name: 'login' };
    }

    if (to.meta.guest && authed) {
        return { name: 'dashboard' };
    }

    return true;
});

export default router;
