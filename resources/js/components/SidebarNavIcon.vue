<template>
    <svg
        class="sidebar-nav-icon"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <!-- Icons chosen to match menu meaning; outline style for sidebar -->
        <template v-if="name === 'dashboard'">
            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
            <path d="M9 22V12h6v10" />
        </template>
        <template v-else-if="name === 'wallet'">
            <path d="M21 12V7H5a2 2 0 010-4h14v4" />
            <path d="M3 5v14a2 2 0 002 2h16v-5" />
            <path d="M18 12a2 2 0 100 4 2 2 0 000-4z" />
        </template>
        <template v-else-if="name === 'calendar'">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
            <path d="M16 2v4M8 2v4M3 10h18" />
        </template>
        <template v-else-if="name === 'followup'">
            <path d="M9 11l3 3L22 4" />
            <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
        </template>
        <template v-else-if="name === 'user-plus'">
            <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
            <circle cx="8.5" cy="7" r="4" />
            <path d="M20 8v6M23 11h-6" />
        </template>
        <template v-else-if="name === 'users'">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
        </template>
        <template v-else-if="name === 'id-card'">
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <circle cx="9" cy="12" r="2" />
            <path d="M15 10h3M15 14h3" />
        </template>
        <template v-else-if="name === 'funnel'">
            <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z" />
        </template>
        <template v-else-if="name === 'cube'">
            <path d="M21 16V8l-9-5-9 5v8l9 5 9-5z" />
            <path d="M3.27 6.96L12 12.01l8.73-5.05" />
            <path d="M12 22.08V12" />
        </template>
        <template v-else-if="name === 'ticket'">
            <path d="M3 7v2a2 2 0 001 1v11a2 2 0 002 2h12a2 2 0 002-2V10a2 2 0 001-1V7M3 7h18M5 7V5a2 2 0 012-2h10a2 2 0 012 2v2" />
        </template>
        <template v-else-if="name === 'device'">
            <rect x="5" y="2" width="14" height="20" rx="2" ry="2" />
            <path d="M12 18h.01" />
        </template>
        <template v-else-if="name === 'invoice'">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
            <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" />
        </template>
        <template v-else-if="name === 'activity'">
            <path d="M18 20V10M12 20V4M6 20v-6" />
        </template>
        <template v-else-if="name === 'chart-bar'">
            <path d="M12 20V10M18 20V4M6 20v-4" />
        </template>
        <template v-else-if="name === 'target'">
            <circle cx="12" cy="12" r="10" />
            <circle cx="12" cy="12" r="6" />
            <circle cx="12" cy="12" r="2" />
        </template>
        <template v-else-if="name === 'shopping'">
            <circle cx="9" cy="21" r="1" />
            <circle cx="20" cy="21" r="1" />
            <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
        </template>
        <template v-else-if="name === 'chart-pie'">
            <path d="M21.21 15.89A10 10 0 118 2.83" />
            <path d="M22 12A10 10 0 0012 2v10z" />
        </template>
        <template v-else-if="name === 'megaphone'">
            <path d="M3 11l18-5v12L3 13v-2z" />
            <path d="M11.6 16.8a3 3 0 11-5.8-1.6" />
        </template>
        <template v-else-if="name === 'mail'">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
            <path d="M22 6l-10 7L2 6" />
        </template>
        <template v-else-if="name === 'sms'">
            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
        </template>
        <template v-else-if="name === 'message'">
            <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z" />
        </template>
        <template v-else-if="name === 'template'">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
            <path d="M3 9h18M9 21V9" />
        </template>
        <template v-else-if="name === 'briefcase'">
            <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
            <path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2" />
        </template>
        <template v-else-if="name === 'currency'">
            <path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6" />
        </template>
        <template v-else-if="name === 'document'">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
            <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" />
        </template>
        <template v-else-if="name === 'clipboard'">
            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2" />
            <path d="M9 5a2 2 0 012-2h2a2 2 0 012 2v0a2 2 0 01-2 2H9a2 2 0 01-2-2v0z" />
        </template>
        <template v-else-if="name === 'shield'">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
        </template>
        <template v-else-if="name === 'cog'">
            <circle cx="12" cy="12" r="3" />
            <path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" />
        </template>
        <template v-else>
            <circle cx="12" cy="12" r="10" />
            <path d="M12 16v-4M12 8h.01" />
        </template>
    </svg>
</template>

<script setup>
defineProps({
    name: {
        type: String,
        default: 'default',
    },
});
</script>

<style scoped>
.sidebar-nav-icon {
    width: 1.25rem;
    height: 1.25rem;
    flex-shrink: 0;
    opacity: 0.95;
}
</style>
