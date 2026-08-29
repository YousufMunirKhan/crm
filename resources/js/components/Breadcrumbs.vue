<template>
    <nav v-if="crumbs.length > 1" aria-label="Breadcrumb" class="min-w-0">
        <ol class="flex items-center gap-1.5 text-xs text-slate-500 min-w-0">
            <li v-for="(crumb, i) in crumbs" :key="crumb.to || crumb.label" class="flex items-center gap-1.5 min-w-0">
                <ChevronRightIcon v-if="i > 0" class="w-3 h-3 text-slate-500 shrink-0" aria-hidden="true" />

                <router-link
                    v-if="crumb.to && i < crumbs.length - 1"
                    :to="crumb.to"
                    class="truncate hover:text-primary-700 hover:underline"
                >
                    {{ crumb.label }}
                </router-link>
                <span v-else class="truncate text-slate-700 font-medium" aria-current="page">
                    {{ crumb.label }}
                </span>
            </li>
        </ol>
    </nav>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { resolveRouteTitle } from '@/composables/usePageTitle';
import { ChevronRightIcon } from '@heroicons/vue/24/outline';

const route = useRoute();

/**
 * Section each route belongs under, so a detail page shows its parent rather
 * than a flat trail of URL segments.
 */
const PARENTS = {
    '/customers': { label: 'Sales', to: null },
    '/leads': { label: 'Sales', to: null },
    '/followups': { label: 'Sales', to: null },
    '/appointments': { label: 'Sales', to: null },
    '/tickets': { label: 'Service', to: null },
    '/pos-support': { label: 'Service', to: null },
    '/invoices': { label: 'Service', to: null },
    '/products': { label: 'Service', to: null },
    '/email-management': { label: 'Marketing', to: null },
    '/sms-management': { label: 'Marketing', to: null },
    '/whatsapp-management': { label: 'Marketing', to: null },
    '/whatsapp-inbox': { label: 'Marketing', to: null },
    '/cold-calling': { label: 'Marketing', to: null },
    '/templates': { label: 'Marketing', to: null },
    '/employees': { label: 'People & money', to: null },
    '/hr': { label: 'People & money', to: null },
    '/salaries': { label: 'People & money', to: null },
    '/expenses': { label: 'People & money', to: null },
    '/commission': { label: 'People & money', to: null },
    '/reports': { label: 'Insights', to: null },
    '/report': { label: 'Insights', to: null },
    '/todays-report': { label: 'Insights', to: null },
    '/settings': { label: 'System', to: null },
    '/access-manager': { label: 'System', to: null },
};

const crumbs = computed(() => {
    const path = route.path;

    if (path === '/') return [];

    const trail = [{ label: 'Dashboard', to: '/' }];

    const parentKey = Object.keys(PARENTS).find((k) => path === k || path.startsWith(k + '/'));
    if (parentKey) {
        trail.push({ label: PARENTS[parentKey].label, to: null });

        // Link back to the list when we are on a detail or edit page.
        if (path !== parentKey) {
            trail.push({ label: listLabel(parentKey), to: parentKey });
        }
    }

    trail.push({ label: resolveRouteTitle(route), to: null });

    return trail;
});

function listLabel(key) {
    return key
        .replace('/', '')
        .split('-')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}
</script>
