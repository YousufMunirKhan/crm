import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useToastStore = defineStore('toast', () => {
    const visible = ref(false);
    const message = ref('');
    const title = ref('');
    const type = ref('info'); // success, error, warning, info
    const timeout = ref(null);

    const show = (options) => {
        // Clear any existing timeout
        if (timeout.value) {
            clearTimeout(timeout.value);
        }

        // Set toast properties
        message.value = options.message || '';
        title.value = options.title || '';
        type.value = options.type || 'info';
        visible.value = true;

        // Auto-hide after duration
        const duration = options.duration || 4000;
        timeout.value = setTimeout(() => {
            hide();
        }, duration);
    };

    const hide = () => {
        visible.value = false;
        if (timeout.value) {
            clearTimeout(timeout.value);
            timeout.value = null;
        }
    };

    // Convenience methods
    const success = (message, title = '') => show({ message, title, type: 'success' });
    const error = (message, title = '') => show({ message, title, type: 'error' });
    const warning = (message, title = '') => show({ message, title, type: 'warning' });
    const info = (message, title = '') => show({ message, title, type: 'info' });

    return {
        visible,
        message,
        title,
        type,
        show,
        hide,
        success,
        error,
        warning,
        info
    };
});

