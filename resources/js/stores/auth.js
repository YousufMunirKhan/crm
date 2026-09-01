import { defineStore } from 'pinia';
import axios from 'axios';
import router from '@/router';
import { navLegacyGrants } from '@/constants/navSections';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('auth_token'),
        initialized: false,
    }),

    getters: {
        isAuthenticated: (state) => !!state.user && !!state.token,
        role: (state) => state.user?.role?.name ?? null,
        /**
         * Whether this user may see a section.
         *
         * The server resolves this and sends the answer as `nav_sections`. This
         * used to be a second implementation of the same precedence rules, and
         * the two had already drifted apart - POS Support was special-cased
         * here and not on the server, and neither knew about the per-user
         * grants that now exist. Two implementations mean two answers, and the
         * one that matters is the server's, because that is the one guarding
         * the data.
         *
         * The old client-side chain is kept only as a fallback for a session
         * whose token predates this change and has not refreshed yet.
         */
        navSectionAllowed: (state) => (sectionKey) => {
            if (!state.user || !sectionKey) return true;
            if (sectionKey === 'dashboard') return true;

            const resolved = state.user.nav_sections;
            if (resolved && typeof resolved === 'object' && sectionKey in resolved) {
                return !!resolved[sectionKey];
            }

            const roleName = state.user.role?.name;
            if (roleName === 'Admin' || roleName === 'System Admin') return true;

            if (sectionKey === 'pos_support') {
                if (roleName === 'Manager') return true;
                const userP = state.user.nav_permissions;
                if (userP && typeof userP === 'object' && Object.keys(userP).length > 0) {
                    return !!userP.pos_support;
                }
                const roleP = state.user.role?.nav_permissions;
                if (roleP && typeof roleP === 'object' && Object.keys(roleP).length > 0) {
                    return !!roleP.pos_support;
                }
                return false;
            }

            const userP = state.user.nav_permissions;
            if (userP && typeof userP === 'object' && Object.keys(userP).length > 0) {
                return !!userP[sectionKey] || navLegacyGrants(userP, sectionKey);
            }
            const roleP = state.user.role?.nav_permissions;
            if (roleP && typeof roleP === 'object' && Object.keys(roleP).length > 0) {
                return !!roleP[sectionKey] || navLegacyGrants(roleP, sectionKey);
            }
            return true;
        },
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

        async login(payload, next = null) {
            try {
                const { data } = await axios.post('/api/auth/login', payload);
                this.user = data.user;
                this.token = data.token;
                
                // Store token in localStorage
                localStorage.setItem('auth_token', this.token);
                
                // Set axios default header
                axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
                
                // Back to the screen they were thrown out of, when there
                // was one. Guarded to a path on this site so the parameter
                // cannot be used to bounce somebody elsewhere.
                const target = typeof next === 'string' && /^\/(?!\/)/.test(next) ? next : '/';

                router.push(target);
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


