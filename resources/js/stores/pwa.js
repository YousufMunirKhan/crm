import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const usePwaStore = defineStore('pwa', () => {
    // State
    const deferredPrompt = ref(null);
    const isInstallable = ref(false);
    const isInstalled = ref(false);
    const isIOS = ref(false);
    const isAndroid = ref(false);
    const isStandalone = ref(false);
    const showIOSModal = ref(false);
    const pwaEnabled = ref(true); // Can be controlled by admin settings
    const serviceWorkerRegistered = ref(false);

    // Detect platform
    const detectPlatform = () => {
        const userAgent = navigator.userAgent || navigator.vendor || window.opera;
        
        // Detect iOS
        isIOS.value = /iPad|iPhone|iPod/.test(userAgent) && !window.MSStream;
        
        // Detect Android
        isAndroid.value = /android/i.test(userAgent);
        
        // Detect if running in standalone mode (already installed)
        isStandalone.value = window.matchMedia('(display-mode: standalone)').matches ||
                            window.navigator.standalone === true ||
                            document.referrer.includes('android-app://');
        
        // If already in standalone mode, it's installed
        if (isStandalone.value) {
            isInstalled.value = true;
        }
    };

    // Register service worker
    const registerServiceWorker = async () => {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/service-worker.js', {
                    scope: '/'
                });
                
                serviceWorkerRegistered.value = true;
                console.log('[PWA] Service Worker registered:', registration.scope);

                // Check for updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            console.log('[PWA] New version available');
                        }
                    });
                });

                return registration;
            } catch (error) {
                console.error('[PWA] Service Worker registration failed:', error);
                return null;
            }
        }
        return null;
    };

    // Initialize PWA
    const initialize = () => {
        detectPlatform();
        
        // Listen for beforeinstallprompt event (Chrome, Edge, etc.)
        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent the mini-infobar from appearing on mobile
            e.preventDefault();
            // Store the event for later use
            deferredPrompt.value = e;
            // Show install button
            isInstallable.value = true;
            console.log('[PWA] Install prompt captured');
        });

        // Listen for successful installation
        window.addEventListener('appinstalled', () => {
            console.log('[PWA] App installed successfully');
            isInstalled.value = true;
            isInstallable.value = false;
            deferredPrompt.value = null;
        });

        // Register service worker
        registerServiceWorker();
    };

    // Trigger install prompt
    const promptInstall = async () => {
        if (!deferredPrompt.value) {
            // If iOS, show instructions modal
            if (isIOS.value) {
                showIOSModal.value = true;
                return { outcome: 'ios-modal' };
            }
            return { outcome: 'no-prompt' };
        }

        // Show the install prompt
        deferredPrompt.value.prompt();
        
        // Wait for the user's response
        const { outcome } = await deferredPrompt.value.userChoice;
        
        console.log('[PWA] User choice:', outcome);
        
        // Clear the deferred prompt
        deferredPrompt.value = null;
        
        if (outcome === 'accepted') {
            isInstallable.value = false;
        }
        
        return { outcome };
    };

    // Close iOS modal
    const closeIOSModal = () => {
        showIOSModal.value = false;
    };

    // Load PWA enabled setting from API
    const loadSettings = async () => {
        try {
            const response = await fetch('/api/settings/pwa');
            if (response.ok) {
                const data = await response.json();
                pwaEnabled.value = data.enabled ?? true;
            }
        } catch (error) {
            console.log('[PWA] Failed to load settings, using default');
        }
    };

    // Computed
    const shouldShowInstallButton = computed(() => {
        return pwaEnabled.value && 
               !isInstalled.value && 
               !isStandalone.value &&
               (isInstallable.value || isIOS.value);
    });

    const isMobile = computed(() => {
        return isIOS.value || isAndroid.value;
    });

    return {
        // State
        deferredPrompt,
        isInstallable,
        isInstalled,
        isIOS,
        isAndroid,
        isStandalone,
        showIOSModal,
        pwaEnabled,
        serviceWorkerRegistered,
        
        // Computed
        shouldShowInstallButton,
        isMobile,
        
        // Actions
        initialize,
        promptInstall,
        closeIOSModal,
        loadSettings,
        registerServiceWorker,
    };
});

