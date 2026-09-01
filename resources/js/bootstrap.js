import axios from 'axios';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Accept'] = 'application/json';

// Set CSRF token
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

/**
 * Surfaces failures the user would otherwise never see.
 *
 * Roughly 169 catch blocks across the app only console.log, so a failed
 * request looked like nothing happening: an empty report, a send that
 * silently did not send. This handles the cases that are never expected and
 * always worth telling someone about.
 *
 * Deliberately NOT handled here: 403 and 422. Those are ordinary outcomes
 * that views already report in context, and a global toast would duplicate
 * the message or contradict it.
 */
let lastMessage = '';
let lastShownAt = 0;

function notify(message) {
    // Several parallel requests failing at once should say it once.
    const now = Date.now();
    if (message === lastMessage && now - lastShownAt < 4000) return;
    lastMessage = message;
    lastShownAt = now;

    // Resolved lazily: this module is imported before Pinia is created.
    import('@/stores/toast')
        .then(({ useToastStore }) => useToastStore().error(message))
        .catch(() => console.error(message));
}

axios.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;

        if (status === 401) {
            localStorage.removeItem('auth_token');
            delete axios.defaults.headers.common['Authorization'];

            if (window.location.pathname !== '/login') {
                // Tokens last twelve hours, so this lands on somebody in the
                // middle of a working day. Being dropped on a blank login form
                // with no explanation reads as the app having broken, and
                // whatever they were doing is gone. Say what happened, and come
                // back to the same screen afterwards.
                const from = window.location.pathname + window.location.search;
                const query = new URLSearchParams({ expired: '1' });

                if (from !== '/' && from !== '/login') {
                    query.set('next', from);
                }

                window.location.href = `/login?${query.toString()}`;
            }

            return Promise.reject(error);
        }

        if (!error.response) {
            // No response at all: offline, DNS, or the server is down.
            if (error.code !== 'ERR_CANCELED') {
                notify('Cannot reach the server. Check your connection and try again.');
            }
        } else if (status === 419) {
            notify('Your session expired. Refresh the page and try again.');
        } else if (status === 429) {
            notify('Too many attempts. Please wait a moment and try again.');
        } else if (status >= 500) {
            notify(
                error.response?.data?.message
                    || 'Something went wrong on our side. The error has been logged.'
            );
        }

        return Promise.reject(error);
    }
);
