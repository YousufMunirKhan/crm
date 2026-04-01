import './bootstrap';
import '../css/app.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';

const app = createApp(App);
const pinia = createPinia();
app.use(pinia);
app.use(router);

import { useAuthStore } from './stores/auth';
import { useBrandingStore } from './stores/branding';

const auth = useAuthStore();
const branding = useBrandingStore();

Promise.all([auth.bootstrap(), branding.loadPublic()]).then(() => {
    app.mount('#app');
});
