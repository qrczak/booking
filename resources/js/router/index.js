import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import LoginPage from '../pages/LoginPage.vue';
import RoomsPage from '../pages/RoomsPage.vue';
import BookingFormPage from '../pages/BookingFormPage.vue';
import MyBookingsPage from '../pages/MyBookingsPage.vue';

const routes = [
    { path: '/login', name: 'login', component: LoginPage, meta: { guest: true } },
    { path: '/', redirect: '/rooms' },
    { path: '/rooms', name: 'rooms', component: RoomsPage, meta: { auth: true } },
    { path: '/bookings/new', name: 'bookings.new', component: BookingFormPage, meta: { auth: true } },
    { path: '/bookings', name: 'bookings', component: MyBookingsPage, meta: { auth: true } },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to) => {
    const auth = useAuthStore();
    if (to.meta.auth && !auth.isAuthenticated) {
        return { name: 'login' };
    }
    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'rooms' };
    }
    return true;
});

export default router;
