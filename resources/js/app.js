import './bootstrap';
import '../css/app.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';

const app = createApp(App);
app.use(createPinia());
app.use(router);

// Bootstrap auth before mounting
import { useAuthStore } from './stores/auth';
const auth = useAuthStore();
auth.bootstrap().then(() => {
    app.mount('#app');
});
