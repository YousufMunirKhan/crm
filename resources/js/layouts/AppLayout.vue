<template>
    <div class="min-h-screen flex bg-slate-50 text-slate-900">
        <!-- Mobile Menu Overlay -->
        <div
            v-if="showSidebar && mobileMenuOpen"
            class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
            @click="mobileMenuOpen = false"
        ></div>

        <!-- Sidebar - Hidden for customer detail view -->
        <aside
            v-if="showSidebar"
            :class="[
                'bg-slate-900 text-slate-100 flex flex-col fixed left-0 top-0 bottom-0 z-50 transition-transform duration-300',
                'w-64',
                mobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
            ]"
        >
            <div class="h-16 flex items-center justify-between px-4 font-semibold text-lg border-b border-slate-200 shrink-0 bg-white">
                <router-link to="/" class="flex items-center gap-2 min-w-0">
                    <img
                        v-if="companyLogo"
                        :src="companyLogo"
                        alt="Switch & Save CRM"
                        class="h-9 w-auto max-w-[180px] object-contain object-left bg-transparent"
                    />
                    <span v-else class="text-slate-100 truncate">Switch & Save CRM</span>
                </router-link>
                <button
                    @click="mobileMenuOpen = false"
                    class="lg:hidden text-slate-400 hover:text-white"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <nav class="flex-1 py-4 space-y-1 overflow-y-auto">
                <template v-for="(item, idx) in navItemsVisible" :key="item.to || item.label + idx">
                    <!-- Submenu group (e.g. Marketing) -->
                    <div v-if="item.children" class="space-y-0">
                        <button
                            type="button"
                            @click="toggleGroup(item.label)"
                            :class="[
                                'w-full flex items-center justify-between px-6 py-2.5 text-sm hover:bg-slate-800 transition-colors text-left',
                                isGroupActive(item) ? 'bg-slate-800 border-r-2 border-blue-500' : ''
                            ]"
                        >
                            <span>{{ item.label }}</span>
                            <svg
                                class="w-4 h-4 text-slate-400 transition-transform shrink-0"
                                :class="{ 'rotate-90': isGroupExpanded(item.label) }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                        <div
                            v-show="isGroupExpanded(item.label)"
                            class="bg-slate-800/50 border-l-2 border-slate-600 ml-2"
                        >
                            <router-link
                                v-for="child in item.children"
                                :key="child.to"
                                :to="child.to"
                                @click="mobileMenuOpen = false"
                                :class="[
                                    'block px-6 py-2.5 text-sm hover:bg-slate-700 transition-colors pl-8',
                                    isChildActive(child) ? 'bg-slate-700 border-r-2 border-blue-500' : ''
                                ]"
                            >
                                {{ child.label }}
                            </router-link>
                        </div>
                    </div>
                    <!-- Single link -->
                    <router-link
                        v-else
                        :to="item.to"
                        @click="mobileMenuOpen = false"
                        :class="[
                            'block px-6 py-2.5 text-sm hover:bg-slate-800 transition-colors',
                            isNavItemActive(item) ? 'bg-slate-800 border-r-2 border-blue-500' : ''
                        ]"
                    >
                        {{ item.label }}
                    </router-link>
                </template>
            </nav>
            <div class="p-4 border-t border-slate-700">
                <div class="text-xs text-slate-400 mb-2">{{ user?.name }}</div>
                <div class="text-xs text-slate-500">{{ user?.role?.name }}</div>
            </div>
        </aside>

        <!-- Main content -->
        <div :class="['flex-1 flex flex-col w-full min-w-0 overflow-x-hidden', showSidebar ? 'lg:ml-64' : '']">
            <!-- Top header - Only show if sidebar is visible -->
            <header v-if="showSidebar" class="min-h-14 sm:min-h-16 bg-white border-b border-slate-200 flex flex-wrap items-center justify-between gap-y-2 gap-x-2 sm:gap-x-3 px-3 sm:px-4 lg:px-6 py-2 sticky top-0 z-30">
                <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1 sm:flex-initial">
                    <button
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="lg:hidden p-2 -ml-1 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg shrink-0 touch-manipulation"
                        aria-label="Toggle menu"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="font-semibold text-slate-800 text-sm sm:text-base truncate">
                        {{ pageTitle }}
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:gap-4 flex-shrink-0 min-w-0">
                    <router-link
                        v-if="todayAppointmentCount > 0"
                        to="/appointments"
                        class="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-amber-100 text-amber-800 hover:bg-amber-200 text-sm font-medium shrink-0"
                    >
                        <span>📅</span>
                        <span>You have {{ todayAppointmentCount }} appointment{{ todayAppointmentCount !== 1 ? 's' : '' }} — Check details</span>
                    </router-link>
                    <p class="text-slate-600 text-xs sm:text-sm text-right truncate max-w-[100px] sm:max-w-none" :title="`${user?.name || ''} (${user?.role?.name || ''})`">
                        <span class="hidden sm:inline">Welcome back, </span>
                        <span class="font-medium text-slate-800 truncate">{{ user?.name }}</span>
                        <span v-if="user?.role?.name" class="hidden sm:inline text-slate-500"> ({{ user.role.name }})</span>
                    </p>
                    <button
                        class="px-3 py-2 sm:py-2.5 lg:px-4 text-sm rounded-lg bg-slate-900 text-white hover:bg-slate-800 transition shrink-0 touch-manipulation"
                        @click="logout"
                    >
                        Logout
                    </button>
                </div>
            </header>

            <!-- Content -->
            <div class="flex-1 overflow-hidden min-w-0">
                <main class="h-full overflow-y-auto overflow-x-hidden bg-slate-50 px-3 py-4 sm:px-4 sm:py-5 lg:px-6">
                    <RouterView />
                </main>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, RouterLink, RouterView } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';

const route = useRoute();
const auth = useAuthStore();
const mobileMenuOpen = ref(false);
const companyLogo = ref('');
const expandedGroups = ref(new Set());
const todayAppointmentCount = ref(0);

/** Full nav tree (role-based). `section` keys map to users.nav_permissions whitelist. */
const navItems = computed(() => {
    if (!auth.user) return [];

    const userRole = auth.user?.role?.name;
    const isAdmin = userRole === 'Admin' || userRole === 'Manager' || userRole === 'System Admin';

    const uid = auth.user?.id;
    const items = [
        { to: '/', label: 'Dashboard', section: 'dashboard' },
        ...(uid ? [{ to: `/employees/${uid}/edit`, label: 'Bank & documents', section: 'dashboard' }] : []),
        { to: '/appointments', label: 'Appointments', section: 'appointments' },
        { to: '/followups', label: 'Follow-ups', section: 'followups' },
        { to: '/customers?type=prospect', label: 'Prospects', section: 'prospects' },
        { to: '/customers?type=customer', label: 'Customers', section: 'customers' },
    ];

    items.push(
        { to: '/leads/pipeline', label: 'Lead Pipeline', section: 'leads_pipeline' },
        { to: '/products', label: 'Products', section: 'products' },
        { to: '/tickets', label: 'Tickets', section: 'tickets' },
        ...(isAdmin ? [{ to: '/pos-support', label: 'POS Support', section: 'pos_support' }] : []),
        { to: '/invoices', label: 'Invoices', section: 'invoices' },
    );

    if (!isAdmin) {
        items.push({ to: '/today-activity', label: "Today's Activity", section: 'today_activity' });
    }

    const reportChildren = [
        { to: '/report/my-report', label: 'My Report', section: 'report' },
    ];
    if (isAdmin) {
        reportChildren.push({ to: '/report/target-achievement', label: 'Target vs Achievement', section: 'report' });
        reportChildren.push({ to: '/report/products-by-employee', label: 'Products by Employee', section: 'report' });
        reportChildren.push({ to: '/reports', label: 'Reports & Analytics', section: 'report' });
    }
    items.push({
        label: 'Report',
        children: reportChildren,
    });
    if (isAdmin) {
        items.push({ to: '/todays-report', label: "Today's Report", section: 'todays_report' });
        items.push({
            label: 'Marketing',
            children: [
                { to: '/email-management', label: 'Email Management', section: 'marketing' },
                { to: '/sms-management', label: 'SMS Management', section: 'marketing' },
                { to: '/bulk-whatsapp', label: 'Bulk Whatsapp', section: 'marketing' },
                { to: '/templates', label: 'Templates', section: 'marketing' },
                { to: '/whatsapp-templates', label: 'WhatsApp Templates', section: 'marketing' },
            ],
        });
    }

    if (isAdmin) {
        items.push({ to: '/employees', label: 'Employees', section: 'employees' });
        items.push({ to: '/hr', label: 'HR', section: 'hr' });
        items.push({ to: '/expenses', label: 'Expenses', section: 'expenses' });
        items.push({ to: '/salaries/list', label: 'Salary Slips', section: 'salary_slips' });
        items.push({ to: '/salaries/reports', label: 'Salary Reports', section: 'salary_reports' });
    }

    if (userRole === 'Admin' || userRole === 'System Admin') {
        items.push({ to: '/access-manager', label: 'Access Manager', section: 'access_manager' });
        items.push({ to: '/settings', label: 'Settings', section: 'settings' });
    }

    return items;
});

const navItemsVisible = computed(() => {
    if (!auth.user) return [];
    const allow = (key) => auth.navSectionAllowed(key);
    const out = [];
    for (const item of navItems.value) {
        if (item.children) {
            const children = item.children.filter((c) => !c.section || allow(c.section));
            if (children.length) out.push({ ...item, children });
            continue;
        }
        if (item.section && !allow(item.section)) continue;
        out.push(item);
    }
    return out;
});

// Auto-expand the group that contains the current route
function ensureActiveGroupExpanded() {
    const items = navItemsVisible.value;
    for (const item of items) {
        if (item.children && item.children.some(c => route.path === c.to || (c.to !== '/' && route.path.startsWith(c.to)))) {
            expandedGroups.value = new Set([...expandedGroups.value, item.label]);
            return;
        }
    }
}
watch(() => route.path, ensureActiveGroupExpanded);
watch(navItemsVisible, (items) => { if (items.length) ensureActiveGroupExpanded(); }, { immediate: true });

function toggleGroup(label) {
    const next = new Set(expandedGroups.value);
    if (next.has(label)) next.delete(label);
    else next.add(label);
    expandedGroups.value = next;
}

function isGroupExpanded(label) {
    return expandedGroups.value.has(label);
}

function isGroupActive(item) {
    return item.children && item.children.some(c => route.path === c.to || (c.to !== '/' && route.path.startsWith(c.to)));
}

function isChildActive(child) {
    return route.path === child.to || (child.to !== '/' && route.path.startsWith(child.to));
}

// Ensure auth is initialized, load logo, appointment count, and expand active nav group
onMounted(async () => {
    if (!auth.initialized) {
        await auth.bootstrap();
    }
    ensureActiveGroupExpanded();
    try {
        const response = await axios.get('/api/settings/public');
        companyLogo.value = response.data.logo_url || '';
    } catch (e) {
        // ignore
    }
    try {
        const res = await axios.get('/api/appointments/today-count');
        todayAppointmentCount.value = res.data?.count ?? 0;
    } catch (e) {
        // ignore
    }
});

const user = computed(() => auth.user);
const pageTitle = computed(() => route.meta.title || 'Dashboard');

function isNavItemActive(item) {
    if (item.to.startsWith('/customers') && item.to.includes('type=')) {
        const type = item.to.includes('type=prospect') ? 'prospect' : 'customer';
        return route.path === '/customers' && (route.query.type || 'prospect') === type;
    }
    return route.path === item.to || (item.to !== '/' && route.path.startsWith(item.to));
}
const showSidebar = computed(() => route.name !== 'customer-lead'); // Hide sidebar for customer detail view

const logout = () => auth.logout();
</script>


