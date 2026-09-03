{{--
    Registers the service worker outside the Vue bundle, so the Filament panel
    is installable on its own. Safe to include twice: registering the same
    scope is idempotent.
--}}
<script>
    if ('serviceWorker' in navigator) {
        var refreshingForPwaUpdate = false;

        navigator.serviceWorker.addEventListener('controllerchange', function () {
            if (refreshingForPwaUpdate) return;
            refreshingForPwaUpdate = true;
            window.location.reload();
        });

        function showPwaUpdateButton(worker) {
            if (!worker || document.getElementById('pwa-update-button')) return;

            var button = document.createElement('button');
            button.id = 'pwa-update-button';
            button.type = 'button';
            button.textContent = 'Update CRM';
            button.style.position = 'fixed';
            button.style.left = '12px';
            button.style.right = '12px';
            button.style.bottom = 'calc(12px + env(safe-area-inset-bottom))';
            button.style.zIndex = '9999';
            button.style.minHeight = '44px';
            button.style.border = '0';
            button.style.borderRadius = '10px';
            button.style.background = '#2563eb';
            button.style.color = '#fff';
            button.style.font = '600 14px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
            button.style.boxShadow = '0 20px 25px -5px rgb(15 23 42 / 0.18), 0 8px 10px -6px rgb(15 23 42 / 0.14)';
            button.addEventListener('click', function () {
                worker.postMessage({ type: 'SKIP_WAITING' });
            });
            document.body.appendChild(button);
        }

        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
                .then(function (registration) {
                    if (registration.waiting && navigator.serviceWorker.controller) {
                        showPwaUpdateButton(registration.waiting);
                    }

                    registration.addEventListener('updatefound', function () {
                        var worker = registration.installing;
                        if (!worker) return;

                        worker.addEventListener('statechange', function () {
                            if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                                showPwaUpdateButton(worker);
                            }
                        });
                    });

                    registration.update().catch(function () {});
                })
                .catch(function (error) {
                    console.error('[PWA] Service Worker registration failed:', error);
                });
        });
    }
</script>
