<template>
    <div class="h-screen min-h-0 overflow-hidden flex bg-gradient-to-br from-gray-50 to-gray-100 text-slate-900">
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
                'bg-gradient-to-b from-[#2563EB] via-[#1d4ed8] to-[#0D9488] text-white flex flex-col fixed left-0 top-0 bottom-0 z-50 transition-transform duration-300',
                'w-64 max-w-64 min-w-64 overflow-x-hidden box-border',
                mobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
            ]"
        >
            <div class="h-16 shrink-0 w-full max-w-full min-w-0 flex items-center justify-between gap-2 px-3 font-semibold text-lg border-b border-white/10 bg-[#2563EB]/95 backdrop-blur-sm overflow-hidden">
                <router-link to="/" class="flex items-center gap-2 min-w-0 flex-1 overflow-hidden">
                    <img
                        v-if="branding.logoUrl"
                        :src="branding.logoUrl"
                        alt="Company logo"
                        class="h-9 w-auto max-w-full object-contain object-left bg-transparent drop-shadow-sm"
                    />
                    <img
                        v-else-if="branding.faviconUrl"
                        :src="branding.faviconUrl"
                        alt=""
                        class="h-9 w-9 shrink-0 rounded-lg object-cover bg-white/15 ring-1 ring-white/20 shadow-sm"
                    />
                    <span v-else class="text-white truncate text-sm font-bold">Switch & Save CRM</span>
                </router-link>
                <button
                    type="button"
                    @click="mobileMenuOpen = false"
                    class="lg:hidden shrink-0 text-white/80 hover:text-white p-1 rounded-md hover:bg-white/10"
                    aria-label="Close menu"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <nav class="flex-1 min-h-0 py-4 space-y-1.5 overflow-y-auto overflow-x-hidden overscroll-contain">
                <template v-for="(item, idx) in navItemsVisible" :key="item.to || item.label + idx">
                    <!-- Submenu group (e.g. Marketing) -->
                    <div v-if="item.children" class="space-y-0">
                        <button
                            type="button"
                            @click="toggleGroup(item.label)"
                            :class="[
                                'w-full flex items-center justify-between gap-2 px-3 py-2.5 text-sm hover:bg-white/10 transition-colors text-left rounded-lg mx-2 border-l-4',
                                isGroupActive(item)
                                    ? 'bg-white/15 border-emerald-300 text-white shadow-sm'
                                    : 'border-transparent text-white/95',
                            ]"
                        >
                            <span class="flex items-center gap-3 min-w-0">
                                <SidebarNavIcon v-if="item.icon" :name="item.icon" class="text-white" />
                                <span class="truncate">{{ item.label }}</span>
                            </span>
                            <svg
                                class="w-4 h-4 text-white/70 transition-transform shrink-0"
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
                            class="bg-black/15 border-l border-white/25 ml-3 mr-2 rounded-r-lg py-1"
                        >
                            <router-link
                                v-for="child in item.children"
                                :key="child.to"
                                :to="child.to"
                                @click="mobileMenuOpen = false"
                                :class="[
                                    'flex items-center gap-3 px-3 py-2 text-sm transition-colors rounded-lg mx-1 pl-4 border-l-4',
                                    isChildActive(child)
                                        ? 'bg-white/15 border-emerald-300 text-white shadow-sm'
                                        : 'border-transparent hover:bg-white/10 text-white/95',
                                ]"
                            >
                                <SidebarNavIcon v-if="child.icon" :name="child.icon" class="text-white shrink-0" />
                                <span class="truncate">{{ child.label }}</span>
                            </router-link>
                        </div>
                    </div>
                    <!-- Single link -->
                    <router-link
                        v-else
                        :to="item.to"
                        @click="mobileMenuOpen = false"
                        :class="[
                            'flex items-center gap-3 mx-2 px-3 py-2.5 text-sm rounded-lg transition-colors border-l-4',
                            isNavItemActive(item)
                                ? 'bg-white/15 border-emerald-300 text-white shadow-sm'
                                : 'border-transparent hover:bg-white/10 text-white/95',
                        ]"
                    >
                        <SidebarNavIcon v-if="item.icon" :name="item.icon" class="text-white shrink-0" />
                        <span class="truncate">{{ item.label }}</span>
                    </router-link>
                </template>
            </nav>
            <div class="p-4 border-t border-white/15 bg-[#0D9488]/90 backdrop-blur-sm">
                <div class="text-xs text-white mb-2 font-medium">{{ user?.name }}</div>
                <div class="text-xs text-white/85">{{ user?.role?.name }}</div>
            </div>
        </aside>

        <!-- Main content -->
        <div :class="['flex-1 flex flex-col min-h-0 min-w-0 w-full overflow-hidden', showSidebar ? 'lg:ml-64' : '']">
            <!-- Top header - Only show if sidebar is visible -->
            <header v-if="showSidebar" class="shrink-0 min-h-14 sm:min-h-16 bg-gradient-to-r from-emerald-50/90 via-white to-teal-50/75 border-b border-slate-200/70 flex flex-wrap items-center justify-between gap-y-2 gap-x-2 sm:gap-x-3 px-3 sm:px-4 lg:px-6 py-2 z-30 backdrop-blur-sm">
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
                    <h1
                        class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-green-500 bg-clip-text text-transparent truncate min-w-0"
                    >
                        {{ pageTitle }}
                    </h1>
                </div>
                <div class="flex items-center gap-2 sm:gap-4 flex-shrink-0 min-w-0">
                    <router-link
                        v-if="todayAppointmentCount > 0"
                        to="/appointments"
                        class="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-100/80 hover:bg-emerald-100/90 text-sm font-medium shrink-0"
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
                        class="px-3 py-2 sm:py-2.5 lg:px-4 text-sm rounded-lg bg-[#7C3AED] text-white hover:bg-[#6d28d9] shadow-sm shadow-violet-600/25 transition shrink-0 touch-manipulation font-medium"
                        @click="logout"
                    >
                        Logout
                    </button>
                </div>
            </header>

            <!-- Content -->
            <div class="flex-1 flex flex-col min-h-0 min-w-0 overflow-hidden">
                <main class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden overscroll-y-contain bg-gradient-to-br from-gray-50 to-gray-100 px-3 py-4 sm:px-4 sm:py-5 lg:px-6">
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
import { useBrandingStore } from '@/stores/branding';
import SidebarNavIcon from '@/components/SidebarNavIcon.vue';
import axios from 'axios';

const route = useRoute();
const auth = useAuthStore();
const branding = useBrandingStore();
const mobileMenuOpen = ref(false);
const expandedGroups = ref(new Set());
const todayAppointmentCount = ref(0);

/** Full nav tree (role-based). `section` keys map to users.nav_permissions whitelist. */
const navItems = computed(() => {
    if (!auth.user) return [];

    const userRole = auth.user?.role?.name;
    const isAdmin = userRole === 'Admin' || userRole === 'Manager' || userRole === 'System Admin';

    const uid = auth.user?.id;
    const items = [
        { to: '/', label: 'Dashboard', section: 'dashboard', icon: 'dashboard' },
        ...(uid ? [{ to: `/employees/${uid}/edit`, label: 'Bank & documents', section: 'dashboard', icon: 'wallet' }] : []),
        { to: '/appointments', label: 'Appointments', section: 'appointments', icon: 'calendar' },
        { to: '/followups', label: 'Follow-ups', section: 'followups', icon: 'followup' },
        { to: '/customers?type=prospect', label: 'Prospects', section: 'prospects', icon: 'user-plus' },
        { to: '/customers?type=customer', label: 'Customers', section: 'customers', icon: 'users' },
    ];

    items.push(
        { to: '/leads/pipeline', label: 'Lead Pipeline', section: 'leads_pipeline', icon: 'funnel' },
        { to: '/products', label: 'Products', section: 'products', icon: 'cube' },
        { to: '/tickets', label: 'Tickets', section: 'tickets', icon: 'ticket' },
        { to: '/pos-support', label: 'POS Support', section: 'pos_support', icon: 'device' },
        { to: '/invoices', label: 'Invoices', section: 'invoices', icon: 'invoice' },
    );

    if (!isAdmin) {
        items.push({ to: '/today-activity', label: "Today's Activity", section: 'today_activity', icon: 'activity' });
    }

    const reportChildren = [
        { to: '/report/my-report', label: 'My Report', section: 'report', icon: 'chart-bar' },
    ];
    if (isAdmin) {
        reportChildren.push({ to: '/report/target-achievement', label: 'Target vs Achievement', section: 'report', icon: 'target' });
        reportChildren.push({ to: '/report/products-by-employee', label: 'Products by Employee', section: 'report', icon: 'shopping' });
        reportChildren.push({ to: '/reports', label: 'Reports & Analytics', section: 'report', icon: 'chart-pie' });
    }
    items.push({
        label: 'Report',
        icon: 'chart-pie',
        children: reportChildren,
    });
    if (isAdmin) {
        items.push({ to: '/todays-report', label: "Today's Report", section: 'todays_report', icon: 'document' });
        items.push({
            label: 'Marketing',
            icon: 'megaphone',
            children: [
                { to: '/email-management', label: 'Email Management', section: 'marketing', icon: 'mail' },
                { to: '/sms-management', label: 'SMS Management', section: 'marketing', icon: 'sms' },
                { to: '/bulk-whatsapp', label: 'Bulk Whatsapp', section: 'marketing', icon: 'message' },
                { to: '/templates', label: 'Templates', section: 'marketing', icon: 'template' },
                { to: '/whatsapp-templates', label: 'WhatsApp Templates', section: 'marketing', icon: 'message' },
            ],
        });
    }

    if (isAdmin) {
        items.push({ to: '/employees', label: 'Employees', section: 'employees', icon: 'id-card' });
        items.push({ to: '/hr', label: 'HR', section: 'hr', icon: 'briefcase' });
        items.push({ to: '/expenses', label: 'Expenses', section: 'expenses', icon: 'currency' });
        items.push({ to: '/salaries/list', label: 'Salary Slips', section: 'salary_slips', icon: 'document' });
        items.push({ to: '/salaries/reports', label: 'Salary Reports', section: 'salary_reports', icon: 'clipboard' });
    }

    if (userRole === 'Admin' || userRole === 'System Admin') {
        items.push({ to: '/access-manager', label: 'Access Manager', section: 'access_manager', icon: 'shield' });
        items.push({ to: '/settings', label: 'Settings', section: 'settings', icon: 'cog' });
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
    await branding.loadPublic();
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


