import { defineStore } from 'pinia';
import axios from 'axios';
import router from '@/router';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('auth_token'),
        initialized: false,
    }),

    getters: {
        isAuthenticated: (state) => !!state.user && !!state.token,
        role: (state) => state.user?.role?.name ?? null,
    },

    actions: {
        async bootstrap() {
            // Set token from localStorage if available
            if (this.token) {
                axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
            }

            try {
                const { data } = await axios.get('/api/auth/me');
                this.user = data;
                this.initialized = true;
            } catch (error) {
                // Token invalid or expired
                this.user = null;
                this.token = null;
                localStorage.removeItem('auth_token');
                delete axios.defaults.headers.common['Authorization'];
                this.initialized = true;
            }
        },

        async login(payload) {
            try {
                const { data } = await axios.post('/api/auth/login', payload);
                this.user = data.user;
                this.token = data.token;
                
                // Store token in localStorage
                localStorage.setItem('auth_token', this.token);
                
                // Set axios default header
                axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
                
                router.push('/');
            } catch (error) {
                throw error;
            }
        },

        async logout() {
            try {
                if (this.token) {
                    await axios.post('/api/auth/logout');
                }
            } catch (error) {
                console.error('Logout error:', error);
            } finally {
                this.user = null;
                this.token = null;
                localStorage.removeItem('auth_token');
                delete axios.defaults.headers.common['Authorization'];
                router.push('/login');
            }
        },
    },
});


