import { createRouter, createWebHistory } from 'vue-router';
import { isAuthenticated } from '../api/auth';
import AppLayout from '../layouts/AppLayout.vue';
import LoginView from '../views/LoginView.vue';
import HomeView from '../views/HomeView.vue';
import ProfileView from '../views/ProfileView.vue';

const router = createRouter({
    history: createWebHistory('/app'),
    routes: [
        { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
        {
            path: '/',
            component: AppLayout,
            meta: { auth: true },
            children: [
                {
                    path: '',
                    name: 'home',
                    component: HomeView,
                    meta: {
                        breadcrumbs: [{ label: 'Главная' }],
                    },
                },
                {
                    path: 'profile',
                    name: 'profile',
                    component: ProfileView,
                    meta: {
                        breadcrumbs: [{ label: 'Личные данные' }],
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
        return { name: 'home' };
    }

    return true;
});

export default router;
