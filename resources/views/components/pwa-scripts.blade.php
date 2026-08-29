{{--
    Registers the service worker outside the Vue bundle, so the Filament panel
    is installable on its own. Safe to include twice: registering the same
    scope is idempotent.
--}}
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
                .catch(function (error) {
                    console.error('[PWA] Service Worker registration failed:', error);
                });
        });
    }
</script>
