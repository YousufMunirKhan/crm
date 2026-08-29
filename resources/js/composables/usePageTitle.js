import { computed } from 'vue';
import { useRoute } from 'vue-router';

/**
 * Resolves the human title for the current route.
 *
 * Some routes serve two things depending on a query parameter - /customers is
 * both the Prospects list and the Customers list - so a static meta.title left
 * the heading and the breadcrumb saying "Customers" while the page itself said
 * "Prospects". Both the header and the breadcrumbs read from here so they can
 * never disagree again.
 */
export function resolveRouteTitle(route) {
    const isProspect = route.query.type === 'prospect';

    switch (route.name) {
        case 'customers':
            return isProspect ? 'Prospects' : 'Customers';
        case 'customer-add':
            return isProspect ? 'Add prospect' : 'Add customer';
        case 'customer-edit':
            return isProspect ? 'Edit prospect' : 'Edit customer';
        default:
            return route.meta.title || 'Dashboard';
    }
}

export function usePageTitle() {
    const route = useRoute();

    return computed(() => resolveRouteTitle(route));
}
