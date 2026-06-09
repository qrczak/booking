import { defineStore } from 'pinia';
import api from '../lib/axios';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('token'),
    }),
    getters: {
        isAuthenticated: (state) => !!state.token,
    },
    actions: {
        async login(email, password) {
            const { data } = await api.post('/login', { email, password });
            this.setSession(data);
        },
        async register(payload) {
            const { data } = await api.post('/register', payload);
            this.setSession(data);
        },
        async logout() {
            try {
                await api.post('/logout');
            } finally {
                this.clearSession();
            }
        },
        setSession(data) {
            this.user = data.user;
            this.token = data.token;
            localStorage.setItem('token', data.token);
        },
        clearSession() {
            this.user = null;
            this.token = null;
            localStorage.removeItem('token');
        },
    },
});
