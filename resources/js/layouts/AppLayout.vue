<template>
    <div class="h-screen min-h-0 overflow-hidden flex bg-gradient-to-br from-gray-50 to-gray-100 text-slate-900">
        <a href="#main" class="skip-link">Skip to content</a>

        <!-- Mobile Menu Overlay -->
        <div
            v-if="showSidebar && mobileMenuOpen"
            class="modal-backdrop lg:hidden"
            @click="mobileMenuOpen = false"
        ></div>

        <!-- Sidebar: full CRM menu (including on customer / lead workspace) -->
        <aside
            v-if="showSidebar"
            :class="[
                'bg-gradient-to-b from-primary-600 via-primary-700 to-primary-800 text-white flex flex-col fixed left-0 top-0 bottom-0 z-modal transition-transform duration-300',
                'w-[86%] max-w-[20rem] lg:w-64 lg:max-w-64 lg:min-w-64 overflow-x-hidden box-border',
                mobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
            ]"
        >
            <div class="h-16 shrink-0 w-full max-w-full min-w-0 flex items-center justify-between gap-2 px-3 font-semibold text-lg border-b border-slate-200 bg-white overflow-hidden">
                <router-link to="/" class="flex items-center gap-2 min-w-0 flex-1 overflow-hidden">
                    <BrandLogo
                        :src="branding.logoUrl || branding.faviconUrl"
                        :company-name="branding.companyName"
                        img-class="h-9 w-auto max-w-full object-contain object-left bg-transparent"
                        text-class="text-slate-900 truncate text-sm font-bold"
                    />
                </router-link>
                <button
                    type="button"
                    @click="mobileMenuOpen = false"
                    class="lg:hidden shrink-0 text-slate-500 hover:text-slate-900 p-2 min-h-[44px] min-w-[44px] flex items-center justify-center rounded-md hover:bg-slate-100"
                    aria-label="Close menu"
                >
                    <XMarkIcon class="w-6 h-6" aria-hidden="true" />
                </button>
            </div>
            <nav class="flex-1 min-h-0 py-4 space-y-1.5 overflow-y-auto overflow-x-hidden overscroll-contain">
                <template v-for="(item, idx) in navItemsVisible" :key="item.to || item.heading || item.label + idx">
                    <!-- Section heading -->
                    <div
                        v-if="item.heading"
                        class="px-5 pt-5 pb-1 text-[10px] font-semibold uppercase tracking-[0.08em] text-white/45 select-none first:pt-1"
                    >
                        {{ item.heading }}
                    </div>
                    <!-- Submenu group (e.g. Templates) -->
                    <div v-else-if="item.children" class="space-y-0">
                        <button
                            type="button"
                            @click="toggleGroup(item.label)"
                            :title="item.description || item.label"
                            :class="[
                                'w-full flex items-center justify-between gap-2 px-3 py-2.5 min-h-[44px] lg:min-h-0 text-sm hover:bg-white/10 transition-colors text-left rounded-lg mx-2 border-l-4',
                                isGroupActive(item)
                                    ? 'bg-white/15 border-white text-white shadow-sm'
                                    : 'border-transparent text-white/95',
                            ]"
                        >
                            <span class="flex items-center gap-3 min-w-0">
                                <SidebarNavIcon v-if="item.icon" :name="item.icon" class="text-white" />
                                <span class="truncate">{{ item.label }}</span>
                            </span>
                            <ChevronRightIcon
                                class="icon-sm text-white/70 transition-transform"
                                :class="{ 'rotate-90': isGroupExpanded(item.label) }"
                                aria-hidden="true"
                            />
                        </button>
                        <div
                            v-show="isGroupExpanded(item.label)"
                            class="bg-black/15 border-l border-white/25 ml-3 mr-2 rounded-r-lg py-1"
                        >
                            <router-link
                                v-for="child in item.children"
                                :key="child.to"
                                :to="child.to"
                                :title="child.description || child.label"
                                @click="mobileMenuOpen = false"
                                :class="[
                                    'flex items-center gap-3 px-3 py-2 min-h-[44px] lg:min-h-0 text-sm transition-colors rounded-lg mx-1 pl-4 border-l-4',
                                    isChildActive(child)
                                        ? 'bg-white/15 border-white text-white shadow-sm'
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
                        :title="item.description || item.label"
                        @click="mobileMenuOpen = false"
                        :class="[
                            'flex items-center gap-3 mx-2 px-3 py-2.5 min-h-[44px] lg:min-h-0 text-sm rounded-lg transition-colors border-l-4',
                            isNavItemActive(item)
                                ? 'bg-white/15 border-white text-white shadow-sm'
                                : 'border-transparent hover:bg-white/10 text-white/95',
                        ]"
                    >
                        <SidebarNavIcon v-if="item.icon" :name="item.icon" class="text-white shrink-0" />
                        <span class="truncate">{{ item.label }}</span>
                    </router-link>
                </template>
            </nav>
        </aside>

        <!-- Main content -->
        <div :class="['flex-1 flex flex-col min-h-0 min-w-0 w-full overflow-hidden', showSidebar ? 'lg:ml-64' : '']">
            <!-- Top header - Only show if sidebar is visible -->
            <header v-if="showSidebar" class="shrink-0 min-h-14 sm:min-h-16 bg-white border-b border-slate-200 flex flex-wrap items-center justify-between gap-y-2 gap-x-2 sm:gap-x-3 px-3 sm:px-4 lg:px-6 py-2 z-30">
                <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1 sm:flex-initial">
                    <button
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="lg:hidden p-2 -ml-1 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg shrink-0 touch-manipulation"
                        aria-label="Toggle menu"
                    >
                        <Bars3Icon class="w-6 h-6" aria-hidden="true" />
                    </button>
                    <div class="min-w-0">
                        <!-- Ancestor trail sits above the name it leads to. -->
                        <Breadcrumbs class="hidden sm:block mb-0.5" />
                        <h1 class="text-page-title text-slate-900 truncate min-w-0">
                            {{ pageTitle }}
                        </h1>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 sm:gap-3 flex-shrink-0 min-w-0">
                    <button
                        type="button"
                        class="hidden md:flex items-center gap-2 rounded-control border border-slate-200 bg-white pl-2.5 pr-2 py-1.5 text-slate-500 hover:bg-slate-50 hover:text-slate-800 transition-colors min-h-[40px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                        aria-label="Search"
                        @click="commandPalette?.show()"
                    >
                        <MagnifyingGlassIcon class="icon-sm" aria-hidden="true" />
                        <span class="text-xs">Search</span>
                        <kbd class="kbd">Ctrl K</kbd>
                    </button>
                    <router-link
                        v-if="todayAppointmentCount > 0"
                        to="/appointments"
                        class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-control bg-primary-50 text-primary-800 border border-primary-200 hover:bg-primary-100 text-xs sm:text-sm font-medium shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                    >
                        <SidebarNavIcon name="calendar" class="w-4 h-4" />
                        <span class="truncate">{{ todayAppointmentCount }} appt{{ todayAppointmentCount !== 1 ? 's' : '' }} today</span>
                    </router-link>

                    <!--
                        A deploy replaces every hashed asset, so a tab left open
                        across one is holding filenames that no longer exist and
                        404s on the next screen it opens. The app knew a new
                        version had arrived and only wrote it to the console.
                    -->
                    <button
                        v-if="pwa.updateAvailable"
                        type="button"
                        class="inline-flex min-h-[40px] shrink-0 items-center gap-1.5 rounded-control border
                               border-warning-300 bg-warning-50 px-2.5 text-xs font-medium text-warning-900
                               touch-manipulation transition-colors hover:bg-warning-100
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-warning-500/40"
                        @click="reloadForUpdate"
                    >
                        <ArrowPathIcon class="icon-sm shrink-0" aria-hidden="true" />
                        <span class="hidden sm:inline">Update ready</span>
                        <span class="sm:hidden">Update</span>
                    </button>

                    <span
                        v-if="shiftLocation.tracking.value"
                        class="inline-flex min-h-[40px] shrink-0 items-center gap-1.5 rounded-control border
                               border-success-200 bg-success-50 px-2.5 text-xs font-medium text-success-800"
                        :title="`Location is being recorded while you are clocked in.${
                            shiftLocation.lastSentAt.value ? ' Last sent ' + shiftLocation.lastSentAt.value.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : ''
                        }`"
                    >
                        <MapPinIcon class="icon-sm shrink-0" aria-hidden="true" />
                        <span class="hidden sm:inline">On shift</span>
                    </span>

                    <NotificationBell />

                    <!-- User menu -->
                    <Menu as="div" class="relative shrink-0">
                        <MenuButton
                            class="flex items-center gap-2 rounded-control border border-slate-200 bg-white pl-1.5 pr-2 py-1.5 hover:bg-slate-50 transition-colors min-h-[40px] touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                        >
                            <span
                                class="grid place-items-center w-7 h-7 rounded-md bg-primary-600 text-white text-[11px] font-semibold shrink-0"
                                aria-hidden="true"
                            >{{ userInitials }}</span>
                            <span class="hidden sm:block min-w-0 text-left leading-tight">
                                <span class="block text-xs font-semibold text-slate-800 truncate max-w-[10rem]">{{ user?.name }}</span>
                                <span class="block text-[11px] text-slate-500 truncate max-w-[10rem]">{{ user?.role?.name }}</span>
                            </span>
                            <span class="sr-only">Open user menu</span>
                            <ChevronDownIcon class="icon-sm text-slate-500" aria-hidden="true" />
                        </MenuButton>

                        <MenuItems class="popover-panel absolute right-0 mt-2 w-56 focus-visible:outline-none">
                            <div class="px-3 py-2 border-b border-slate-100 sm:hidden">
                                <p class="text-xs font-semibold text-slate-800 truncate">{{ user?.name }}</p>
                                <p class="text-[11px] text-slate-500 truncate">{{ user?.role?.name }}</p>
                            </div>
                            <MenuItem v-slot="{ active }">
                                <router-link
                                    to="/profile"
                                    :class="['popover-item', active ? 'bg-slate-50' : '']"
                                >
                                    <SidebarNavIcon name="id-card" class="w-4 h-4 text-slate-500" />
                                    My profile
                                </router-link>
                            </MenuItem>
                            <MenuItem v-if="user?.id" v-slot="{ active }">
                                <router-link
                                    :to="`/employees/${user.id}/edit`"
                                    :class="['popover-item', active ? 'bg-slate-50' : '']"
                                >
                                    <SidebarNavIcon name="wallet" class="w-4 h-4 text-slate-500" />
                                    Bank &amp; documents
                                </router-link>
                            </MenuItem>
                            <MenuItem v-slot="{ active }">
                                <router-link
                                    to="/change-password"
                                    :class="['popover-item', active ? 'bg-slate-50' : '']"
                                >
                                    <SidebarNavIcon name="shield" class="w-4 h-4 text-slate-500" />
                                    Change password
                                </router-link>
                            </MenuItem>
                            <div class="my-1.5 divider"></div>
                            <MenuItem v-slot="{ active }">
                                <button
                                    type="button"
                                    :class="[
                                        'popover-item text-danger-700',
                                        active ? 'bg-danger-50' : '',
                                    ]"
                                    @click="logout()"
                                >
                                    <ArrowRightOnRectangleIcon class="icon-sm text-danger-700" aria-hidden="true" />
                                    Log out
                                </button>
                            </MenuItem>
                        </MenuItems>
                    </Menu>
                </div>
            </header>

            <!-- Content -->
            <div class="flex-1 flex flex-col min-h-0 min-w-0 overflow-hidden">
                <main id="main" class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden overscroll-y-contain bg-surface-sunken px-3 py-4 sm:px-4 sm:py-5 lg:px-6">
                    <RouterView />
                </main>
            </div>
        </div>

        <CommandPalette ref="commandPalette" />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter, RouterLink, RouterView } from 'vue-router';
import { lockBodyScroll, releaseAllBodyScrollLocks, unlockBodyScroll } from '@/composables/useBodyScrollLock';
import { useAuthStore } from '@/stores/auth';
import { useBrandingStore } from '@/stores/branding';
import { useToastStore } from '@/stores/toast';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue';
import {
    ArrowRightOnRectangleIcon,
    Bars3Icon,
    ChevronDownIcon,
    ChevronRightIcon,
    MagnifyingGlassIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import SidebarNavIcon from '@/components/SidebarNavIcon.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { ArrowPathIcon, MapPinIcon } from '@heroicons/vue/24/outline';
import { useShiftLocation } from '@/composables/useShiftLocation';
import { usePwaStore } from '@/stores/pwa';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import BrandLogo from '@/components/BrandLogo.vue';
import CommandPalette from '@/components/CommandPalette.vue';
import { usePageTitle } from '@/composables/usePageTitle';
import axios from 'axios';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const branding = useBrandingStore();
const toast = useToastStore();
const pwa = usePwaStore();

/**
 * Location while on shift. Lives in the shell so it survives navigation - a
 * timer inside a page dies the moment somebody opens a lead.
 */
const shiftLocation = useShiftLocation();

/**
 * Take the waiting build. The service worker has already installed it; a plain
 * reload is what hands the tab over to it.
 */
function reloadForUpdate() {
    window.location.reload();
}
const mobileMenuOpen = ref(false);

/**
 * Hold the page still while the drawer is open.
 *
 * Without this the list behind the menu scrolls under your finger, so the
 * drawer feels like it is sliding around rather than sitting on top - which is
 * the difference between an app and a web page pretending to be one. Only on
 * small screens; the desktop sidebar is part of the layout, not an overlay.
 */
const drawerLock = {};

watch(mobileMenuOpen, (open) => {
    if (typeof window === 'undefined') return;

    if (open && window.matchMedia('(max-width: 1023px)').matches) {
        lockBodyScroll(drawerLock);
    } else {
        unlockBodyScroll(drawerLock);
    }
});

/**
 * A lock whose owner disappeared - a dialog removed by the navigation itself,
 * a component torn down mid-transition - would otherwise leave the page
 * unscrollable with nothing on screen to explain it, which reads as a freeze.
 */
watch(() => route.fullPath, () => {
    mobileMenuOpen.value = false;
    releaseAllBodyScrollLocks();
});

onUnmounted(() => unlockBodyScroll(drawerLock));
const expandedGroups = ref(new Set());
const commandPalette = ref(null);
const todayAppointmentCount = ref(0);

/**
 * Full nav tree (role-based), grouped into five sections so related work sits
 * together. `section` keys map to the users.nav_permissions whitelist.
 * An item is one of: { heading }, { to, label, section, icon }, or { label, children }.
 */
const navItems = computed(() => {
    if (!auth.user) return [];

    const userRole = auth.user?.role?.name;
    const isAdmin = userRole === 'Admin' || userRole === 'Manager' || userRole === 'System Admin';
    const isOwner = userRole === 'Admin' || userRole === 'System Admin';
    // A marketer should not need full Admin just to run campaigns.
    const canMarket = isAdmin || userRole === 'Marketing';

    const items = [
        { to: '/', label: 'Dashboard', section: 'dashboard', icon: 'dashboard' },
    ];

    // Personal daily log: field roles, and Manager (isAdmin elsewhere, but still logs their own day).
    if (!isAdmin || userRole === 'Manager') {
        items.push({ to: '/today-activity', label: "Today's Activity", section: 'today_activity', icon: 'activity' });
    }

    // Sales
    items.push(
        { heading: 'Sales' },
        { to: '/customers?type=prospect', label: 'Prospects', section: 'prospects', icon: 'user-plus' },
        { to: '/customers?type=customer', label: 'Customers', section: 'customers', icon: 'users' },
        { to: '/leads', label: 'All Leads', section: 'all_leads', icon: 'list' },
        { to: '/leads/pipeline', label: 'Lead Pipeline', section: 'lead_pipeline', icon: 'funnel' },
        { to: '/followups', label: 'Follow-ups', section: 'followups', icon: 'followup' },
        { to: '/appointments', label: 'Appointments', section: 'appointments', icon: 'calendar' },
    );

    // Service
    items.push(
        { heading: 'Service' },
        { to: '/tickets', label: 'Tickets', section: 'tickets', icon: 'ticket' },
        { to: '/pos-support', label: 'POS Support', section: 'pos_support', icon: 'device' },
        { to: '/invoices', label: 'Invoices', section: 'invoices', icon: 'invoice' },
        { to: '/products', label: 'Products', section: 'products', icon: 'cube' },
    );

    // Marketing
    if (canMarket) {
        items.push(
            { heading: 'Marketing' },
            { to: '/email-management', label: 'Email', section: 'marketing_email', icon: 'mail' },
            { to: '/sms-management', label: 'SMS', section: 'marketing_sms', icon: 'sms' },
            { to: '/whatsapp-management', label: 'WhatsApp', section: 'marketing_whatsapp', icon: 'message' },
            { to: '/whatsapp-inbox', label: 'WhatsApp Inbox', section: 'marketing_whatsapp', icon: 'message' },
            { to: '/marketing/agent', label: 'Marketing agent', section: 'marketing_agent', icon: 'mail' },
            { to: '/cold-calling', label: 'Cold calling', section: 'marketing_cold_calling', icon: 'phone' },
            {
                label: 'Templates',
                icon: 'template',
                children: [
                    { to: '/templates', label: 'Email templates', section: 'marketing_templates', icon: 'template' },
                    { to: '/whatsapp-templates', label: 'WhatsApp templates', section: 'marketing_templates', icon: 'message' },
                ],
            },
        );
    }

    // People & money
    if (isAdmin) {
        items.push(
            { heading: 'People & money' },
            { to: '/employees', label: 'Employees', section: 'employees', icon: 'id-card' },
            { to: '/hr', label: 'Employee records', section: 'hr_records', icon: 'users', activeExact: ['/hr'], activePrefixes: ['/hr/employees'] },
            { to: '/hr/attendance-report', label: 'Attendance', section: 'hr_attendance', icon: 'clipboard' },
            { to: '/team-map', label: 'Where the team is', section: 'hr_attendance', icon: 'map-pin' },
            { to: '/employees/goals', label: 'Targets', section: 'employees', icon: 'funnel' },
            { to: '/salaries/list', label: 'Salary slips', section: 'salary_slips', icon: 'document' },
            { to: '/expenses', label: 'Expenses', section: 'expenses', icon: 'currency' },
            {
                to: '/commission/allocate',
                label: 'Commission workspace',
                section: 'commission_management',
                icon: 'pound',
                description: 'Allocate commission for won product sales.',
            },
        );
    }

    // Insights - every read-only report lives here, nowhere else.
    items.push({ heading: 'Insights' });
    items.push({ to: '/report/my-report', label: 'My work', section: 'report', icon: 'chart-bar' });
    if (isAdmin) {
        items.push(
            // Three rows are gone: Target vs Achievement, Products by
            // Employee and Employee Performance were each a slice of the
            // Business Reports tabs, all three led by revenue figures that are
            // £0 by design. Their addresses redirect to the matching tab.
            { to: '/reports', label: 'Business Reports', section: 'report', icon: 'chart-pie' },
            { to: '/todays-report', label: "Today's Report", section: 'todays_report', icon: 'document' },
            { to: '/reports/geo', label: 'Geo map', section: 'report', icon: 'chart-pie' },
            // Real money, and not CRM reporting - payroll, spend and payouts
            // belong together and away from the sales reports.
            { to: '/salaries/reports', label: 'Salary Reports', section: 'salary_reports', icon: 'chart-bar' },
            { to: '/expenses/monthly-report', label: 'Expense Report', section: 'expenses', icon: 'currency' },
            { to: '/commission/report', label: 'Commission Reports', section: 'commission_management', icon: 'chart-bar' },
        );
    }

    // System
    if (isOwner) {
        items.push(
            { heading: 'System' },
            { to: '/import-export', label: 'Import & export', section: 'settings', icon: 'document' },
            { to: '/settings', label: 'Settings', section: 'settings', icon: 'cog' },
            { to: '/access-manager', label: 'Access Manager', section: 'access_manager', icon: 'shield' },
        );
    }

    return items;
});

const navItemsVisible = computed(() => {
    if (!auth.user) return [];
    const allow = (key) => auth.navSectionAllowed(key);
    const out = [];
    for (const item of navItems.value) {
        if (item.heading) {
            out.push(item);
            continue;
        }
        if (item.children) {
            const children = item.children.filter((c) => !c.section || allow(c.section));
            if (children.length) out.push({ ...item, children });
            continue;
        }
        if (item.section && !allow(item.section)) continue;
        out.push(item);
    }
    // Drop headings whose whole group turned out to be hidden.
    return out.filter((item, i) => {
        if (!item.heading) return true;
        const next = out[i + 1];
        return !!next && !next.heading;
    });
});

// Auto-expand the group that contains the current route
function ensureActiveGroupExpanded() {
    const items = navItemsVisible.value;
    for (const item of items) {
        if (item.children && item.children.some(c => navItemMatchesCurrentRoute(c))) {
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
    return item.children && item.children.some(c => navItemMatchesCurrentRoute(c));
}

function isChildActive(child) {
    return navItemMatchesCurrentRoute(child);
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

    // Only starts if the server says this person is clocked in, so somebody
    // who is not on shift is never asked for location permission at all.
    shiftLocation.start();
});

const user = computed(() => auth.user);
// Shared with Breadcrumbs so the heading and the trail cannot disagree.
const pageTitle = usePageTitle();

function isNavItemActive(item) {
    return navItemMatchesCurrentRoute(item);
}

function navItemMatchesCurrentRoute(item) {
    if (!item?.to) {
        return false;
    }
    if (item.to === '/leads/pipeline') {
        return route.path.startsWith('/leads/pipeline');
    }
    if (item.to === '/leads') {
        return route.path === '/leads';
    }
    if (item.to.startsWith('/customers') && item.to.includes('type=')) {
        const type = item.to.includes('type=prospect') ? 'prospect' : 'customer';
        return route.path === '/customers' && (route.query.type || 'prospect') === type;
    }
    if (item.activeExact?.includes(route.path)) {
        return true;
    }
    if (item.activePrefixes?.some(path => route.path === path || route.path.startsWith(`${path}/`))) {
        return true;
    }
    return route.path === item.to || (item.to !== '/' && route.path.startsWith(`${item.to}/`));
}
// Keep CRM navigation visible on customer / lead workspace so Leads and other sections stay reachable
const showSidebar = computed(() => true);

const userInitials = computed(() => {
    const name = (auth.user?.name || '').trim();
    if (!name) return '?';
    const parts = name.split(/\s+/).filter(Boolean);
    return ((parts[0]?.[0] || '') + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
});

// Headless UI's Menu owns the dropdown's open state, outside-click and Escape,
// so the hand-rolled document listeners that used to live here are gone.

/**
 * The router redirects here with ?denied=<page> when a role or sidebar
 * permission blocks a route. Tell the user instead of moving them silently.
 */
watch(
    () => route.query.denied,
    (denied) => {
        if (!denied) return;
        toast.show({
            type: 'warning',
            title: 'No access',
            message: `You don't have permission to open ${denied}.`,
        });
        const query = { ...route.query };
        delete query.denied;
        router.replace({ path: route.path, query });
    },
    { immediate: true },
);

const logout = () => auth.logout();
</script>


