import { defineStore } from 'pinia';
import axios from 'axios';
import { applyFavicon } from '@/utils/branding';

export const useBrandingStore = defineStore('branding', {
    state: () => ({
        logoUrl: '',
        faviconUrl: '',
        loaded: false,
    }),
    actions: {
        async loadPublic(force = false) {
            if (this.loaded && !force) return;
            try {
                const { data } = await axios.get('/api/settings/public');
                this.logoUrl = data.logo_url || '';
                this.faviconUrl = data.favicon_url || '';
                applyFavicon(this.faviconUrl || null);
            } catch (_) {
                /* keep defaults */
            } finally {
                this.loaded = true;
            }
        },
    },
});
