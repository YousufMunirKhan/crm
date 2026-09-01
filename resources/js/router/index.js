import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { resolveRouteTitle } from '@/composables/usePageTitle';
import LoginView from '@/views/LoginView.vue';
import DashboardView from '@/views/DashboardView.vue';
import CustomerLeadView from '@/views/CustomerLeadView.vue';
import LeadsPipelineView from '@/views/LeadsPipelineView.vue';
import TicketsView from '@/views/TicketsView.vue';
import InvoicesView from '@/views/InvoicesView.vue';
import ReportsView from '@/views/ReportsView.vue';
import HrView from '@/views/HrView.vue';
import SalesAgentDashboard from '@/views/SalesAgentDashboard.vue';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: LoginView,
        meta: { guest: true, title: 'Sign in' },
    },
    {
        path: '/reset-password',
        name: 'password.reset',
        component: () => import('@/views/ResetPasswordView.vue'),
        meta: { public: true, title: 'Reset password' },
    },
    {
        path: '/unsubscribe',
        name: 'unsubscribe',
        component: () => import('@/views/UnsubscribeView.vue'),
        meta: { public: true },
    },
    {
        path: '/',
        name: 'dashboard',
        component: DashboardView,
        meta: { requiresAuth: true, title: 'Dashboard' },
    },
    {
        path: '/dashboard/sales',
        name: 'sales-dashboard',
        component: SalesAgentDashboard,
        meta: { requiresAuth: true, title: 'Sales Dashboard' },
    },
    {
        path: '/customers',
        name: 'customers',
        component: () => import('@/views/CustomersView.vue'),
        meta: { requiresAuth: true, title: 'Customers' },
        beforeEnter(to, _from, next) {
            const t = to.query.type;
            if (t === 'customer' || t === 'prospect') {
                next();
                return;
            }
            next({ path: '/customers', query: { ...to.query, type: 'prospect' }, replace: true });
        },
    },
    {
        path: '/customers/add',
        name: 'customer-add',
        component: () => import('@/views/CustomerFormView.vue'),
        meta: { requiresAuth: true, title: 'Add Customer' },
    },
    {
        path: '/customers/:id/edit',
        name: 'customer-edit',
        component: () => import('@/views/CustomerFormView.vue'),
        meta: { requiresAuth: true, title: 'Edit Customer' },
    },
    {
        path: '/customers/:id',
        name: 'customer-lead',
        component: CustomerLeadView,
        meta: { requiresAuth: true, title: 'Customer Details' },
    },
    {
        path: '/leads/pipeline',
        name: 'leads-pipeline',
        component: LeadsPipelineView,
        meta: { requiresAuth: true, title: 'Lead Pipeline' , section: 'lead_pipeline'},
    },
    {
        path: '/leads/:id',
        name: 'lead-workspace',
        component: CustomerLeadView,
        meta: { requiresAuth: true, title: 'Lead', workspaceFromLead: true },
    },
    {
        path: '/leads',
        name: 'leads-list',
        component: () => import('@/views/LeadsListView.vue'),
        meta: { requiresAuth: true, title: 'Leads' , section: 'all_leads'},
    },
    {
        path: '/tickets',
        name: 'tickets',
        component: TicketsView,
        meta: { requiresAuth: true, title: 'Tickets' },
    },
    {
        path: '/tickets/create',
        name: 'ticket-create',
        component: () => import('@/views/TicketCreateView.vue'),
        meta: { requiresAuth: true, title: 'Create Ticket' },
    },
    {
        path: '/tickets/:id/edit',
        name: 'ticket-edit',
        component: () => import('@/views/TicketEditView.vue'),
        meta: { requiresAuth: true, title: 'Edit Ticket' },
    },
    {
        path: '/tickets/:id',
        name: 'ticket-detail',
        component: () => import('@/views/TicketDetailView.vue'),
        meta: { requiresAuth: true, title: 'Ticket Details' },
    },
    {
        path: '/pos-support',
        name: 'pos-support',
        component: () => import('@/views/PosSupportTicketsView.vue'),
        meta: { requiresAuth: true, title: 'POS Support' , section: 'pos_support'},
    },
    {
        path: '/invoices',
        name: 'invoices',
        component: InvoicesView,
        meta: { requiresAuth: true, title: 'Invoices' },
    },
    {
        path: '/invoices/create',
        name: 'invoice-create',
        component: () => import('@/views/InvoiceCreateView.vue'),
        meta: { requiresAuth: true, title: 'Create Invoice' },
    },
    {
        path: '/invoices/:id/edit',
        name: 'invoice-edit',
        component: () => import('@/views/InvoiceCreateView.vue'),
        meta: { requiresAuth: true, title: 'Edit Invoice' },
    },
    {
        path: '/report/my-report',
        name: 'report-my-report',
        component: () => import('@/views/report/ReportMyReportView.vue'),
        meta: { requiresAuth: true, title: 'My work' },
    },
    /*
     * Three reports that each showed a slice of the Business Reports tabs, all
     * three led by revenue figures that are £0 by design. Kept as redirects
     * rather than deleted outright, because people bookmark reports.
     */
    {
        path: '/report/target-achievement',
        redirect: { path: '/reports', query: { tab: 'team' } },
    },
    {
        path: '/report/products-by-employee',
        redirect: { path: '/reports', query: { tab: 'employee' } },
    },
    {
        path: '/report/employee-performance',
        redirect: { path: '/reports', query: { tab: 'employee' } },
    },
    {
        path: '/reports',
        name: 'reports',
        component: ReportsView,
        meta: { requiresAuth: true, title: 'Business Reports', roles: ['Admin', 'Manager', 'System Admin'] },
    },
    {
        path: '/hr',
        name: 'hr',
        component: HrView,
        meta: { requiresAuth: true, title: 'HR Management' },
        children: [
            {
                path: '',
                name: 'employee-list',
                component: () => import('@/views/EmployeeListView.vue'),
                meta: { requiresAuth: true, title: 'Employees', roles: ['Admin', 'Manager', 'System Admin'] },
            },
            {
                path: 'attendance-report',
                name: 'attendance-report',
                component: () => import('@/views/AttendanceReportView.vue'),
                meta: { requiresAuth: true, title: 'Attendance Report', roles: ['Admin', 'Manager', 'System Admin'] },
            },
            {
                path: 'employees/:id',
                name: 'employee-detail',
                component: () => import('@/views/EmployeeDetailView.vue'),
                meta: { requiresAuth: true, title: 'Employee Details', roles: ['Admin', 'Manager', 'System Admin'] },
            },
        ],
    },
    {
        path: '/salaries/list',
        name: 'salary-slips',
        component: () => import('@/views/SalarySlipsView.vue'),
        meta: { requiresAuth: true, title: 'Salary Slips', roles: ['Admin', 'Manager', 'System Admin'] },
    },
    {
        path: '/salaries',
        name: 'salaries',
        component: () => import('@/views/SalaryView.vue'),
        meta: { requiresAuth: true, title: 'Salary Management', roles: ['Admin', 'Manager', 'System Admin'] },
    },
    {
        path: '/salaries/:id/edit',
        name: 'edit-salary',
        component: () => import('@/views/SalaryView.vue'),
        meta: { requiresAuth: true, title: 'Edit Salary', roles: ['Admin', 'Manager', 'System Admin'] },
    },
    {
        path: '/salaries/reports',
        name: 'salary-reports',
        component: () => import('@/views/SalaryReportsView.vue'),
        meta: { requiresAuth: true, title: 'Salary Reports', roles: ['Admin', 'Manager', 'System Admin'] },
    },
    {
        path: '/expenses/monthly-report',
        name: 'expenses-monthly-report',
        component: () => import('@/views/ExpensesMonthlyReportView.vue'),
        meta: { requiresAuth: true, title: 'Monthly Expense Report', roles: ['Admin', 'Manager'] },
    },
    {
        path: '/expenses/create',
        name: 'expense-create',
        component: () => import('@/views/ExpenseFormView.vue'),
        meta: { requiresAuth: true, title: 'Add Expense', roles: ['Admin', 'Manager'] },
    },
    {
        path: '/expenses/:id/edit',
        name: 'expense-edit',
        component: () => import('@/views/ExpenseFormView.vue'),
        meta: { requiresAuth: true, title: 'Edit Expense', roles: ['Admin', 'Manager'] },
    },
    {
        path: '/expenses',
        name: 'expenses',
        component: () => import('@/views/ExpensesView.vue'),
        meta: { requiresAuth: true, title: 'Expense Management', roles: ['Admin', 'Manager'] },
    },
    {
        path: '/commission-management',
        redirect: '/commission/allocate',
    },
    {
        path: '/commission/allocate',
        name: 'commission-allocate',
        component: () => import('@/views/CommissionManagementView.vue'),
        meta: { requiresAuth: true, title: 'Commission Workspace', roles: ['Admin', 'Manager', 'System Admin'] },
    },
    {
        path: '/commission/report',
        name: 'commission-report',
        component: () => import('@/views/CommissionReportView.vue'),
        meta: { requiresAuth: true, title: 'Commission Reports', roles: ['Admin', 'Manager', 'System Admin'] },
    },
    {
        path: '/products',
        name: 'products',
        component: () => import('@/views/ProductsView.vue'),
        meta: { requiresAuth: true, title: 'Products Management' },
    },
    {
        path: '/employees',
        name: 'employees',
        component: () => import('@/views/EmployeesView.vue'),
        meta: { requiresAuth: true, title: 'Employee Management', roles: ['Admin', 'Manager'] },
    },
    {
        path: '/employees/:id/edit',
        name: 'employee-edit',
        component: () => import('@/views/EmployeeEditView.vue'),
        meta: { requiresAuth: true, title: 'Edit Employee', roles: ['Admin', 'Manager', 'System Admin'] },
    },
    {
        path: '/employees/goals',
        name: 'employee-goals',
        component: () => import('@/views/EmployeeGoalsView.vue'),
        meta: { requiresAuth: true, title: 'Set targets', roles: ['Admin', 'Manager', 'System Admin'] },
    },
    {
        path: '/templates/email/new',
        name: 'email-template-new',
        component: () => import('@/views/EmailTemplateEditPage.vue'),
        meta: { requiresAuth: true, title: 'New Email Template', roles: ['Admin', 'System Admin'] },
    },
    {
        path: '/templates/email/:id(\\d+)/edit',
        name: 'email-template-edit',
        component: () => import('@/views/EmailTemplateEditPage.vue'),
        meta: { requiresAuth: true, title: 'Edit Email Template', roles: ['Admin', 'System Admin'] },
    },
    {
        path: '/templates',
        name: 'templates',
        component: () => import('@/views/TemplatesView.vue'),
        meta: { requiresAuth: true, title: 'Email Templates', roles: ['Admin', 'System Admin'] },
    },
    {
        path: '/import-export',
        name: 'import-export',
        component: () => import('@/views/ImportExportView.vue'),
        meta: { requiresAuth: true, title: 'Import & export', roles: ['Admin', 'Manager', 'System Admin'] },
    },
    {
        path: '/access-manager',
        name: 'access-manager',
        component: () => import('@/views/AccessManagerView.vue'),
        meta: { requiresAuth: true, title: 'Access Manager', roles: ['Admin', 'System Admin'] },
    },
    {
        path: '/settings',
        name: 'settings',
        component: () => import('@/views/SettingsView.vue'),
        meta: { requiresAuth: true, title: 'Settings', roles: ['Admin', 'System Admin'] },
    },
    {
        path: '/today-activity',
        name: 'today-activity',
        component: () => import('@/views/TodaysActivityView.vue'),
        meta: { requiresAuth: true, title: "Today's Activity" },
    },
    {
        path: '/profile',
        name: 'profile',
        component: () => import('@/views/ProfileView.vue'),
        meta: { requiresAuth: true, title: 'My profile' },
    },
    {
        path: '/change-password',
        name: 'change-password',
        component: () => import('@/views/ChangePasswordView.vue'),
        meta: { requiresAuth: true, title: 'Change password' },
    },
    {
        path: '/todays-report',
        name: 'todays-report',
        component: () => import('@/views/TodaysReportView.vue'),
        meta: { requiresAuth: true, title: "Today's Report", roles: ['Admin', 'Manager', 'System Admin'] },
    },
    {
        path: '/bulk-whatsapp',
        redirect: '/whatsapp-management',
    },
    {
        path: '/whatsapp-management',
        name: 'whatsapp-management',
        component: () => import('@/views/WhatsAppManagementView.vue'),
        meta: { requiresAuth: true, title: 'WhatsApp Management' },
    },
    {
        path: '/whatsapp-inbox',
        name: 'whatsapp-inbox',
        component: () => import('@/views/WhatsAppInboxView.vue'),
        meta: { requiresAuth: true, title: 'WhatsApp Inbox', section: 'marketing_whatsapp' },
    },
    {
        path: '/reports/geo',
        name: 'reports-geo',
        component: () => import('@/views/GeoMapView.vue'),
        meta: { requiresAuth: true, title: 'Geo map', roles: ['Admin', 'Manager', 'System Admin'], section: 'report' },
    },
    {
        path: '/whatsapp-templates',
        name: 'whatsapp-templates',
        component: () => import('@/views/WhatsAppTemplatesView.vue'),
        meta: { requiresAuth: true, title: 'WhatsApp Templates', roles: ['Admin', 'System Admin'] },
    },
    {
        path: '/email-management',
        name: 'email-management',
        component: () => import('@/views/EmailManagementView.vue'),
        meta: { requiresAuth: true, title: 'Email Management' },
    },
    {
        path: '/sms-management',
        name: 'sms-management',
        component: () => import('@/views/SmsManagementView.vue'),
        meta: { requiresAuth: true, title: 'SMS Management' },
    },
    {
        path: '/marketing/agent',
        name: 'marketing-agent',
        component: () => import('@/views/MarketingAgentView.vue'),
        meta: { requiresAuth: true, title: 'Marketing agent', section: 'marketing_agent' },
    },
    {
        path: '/cold-calling',
        name: 'cold-calling',
        component: () => import('@/views/ColdCallingView.vue'),
        meta: {
            requiresAuth: true,
            title: 'Cold calling',
            roles: ['Admin', 'Manager', 'System Admin'],
        },
    },
    {
        path: '/appointments',
        name: 'appointments',
        component: () => import('@/views/AppointmentsView.vue'),
        // Not "My" - a manager sees the team's diary here, and the page says
        // whose underneath.
        meta: { requiresAuth: true, title: 'Appointments' },
    },
    {
        path: '/followups',
        name: 'followups',
        component: () => import('@/views/FollowUpsView.vue'),
        meta: { requiresAuth: true, title: 'Follow-ups' },
    },
    {
        path: '/appointments/:id',
        name: 'appointment-detail',
        component: () => import('@/views/AppointmentDetailView.vue'),
        meta: { requiresAuth: true, title: 'Appointment Details' },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/views/NotFoundView.vue'),
        meta: { requiresAuth: true, title: 'Page not found' },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to, from, next) => {
    const auth = useAuthStore();

    if (!auth.initialized) {
        await auth.bootstrap();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return next({ name: 'login' });
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return next({ path: '/' });
    }

    if (!auth.isAuthenticated || !auth.user) {
        return next();
    }

    const userRole = auth.user.role?.name;
    const isFieldRole = userRole === 'Sales' || userRole === 'CallAgent';
    const homeRoute = isFieldRole ? 'sales-dashboard' : 'dashboard';

    // Field roles get their own dashboard.
    if (to.name === 'dashboard' && isFieldRole) {
        return next({ name: 'sales-dashboard' });
    }

    /** Bounce to the user's home screen and say why, rather than redirecting silently. */
    const deny = (reason) => next({ name: homeRoute, query: { denied: reason } });

    // Role gate.
    if (to.meta.roles) {
        // Any logged-in user may open their own employee page (bank details & documents only in UI).
        const ownEmployeePage =
            to.name === 'employee-edit' && Number(to.params.id) === auth.user.id;
        if (!ownEmployeePage && !to.meta.roles.includes(userRole)) {
            return deny(to.meta.title || 'that page');
        }
    }

    // Sidebar-permission gate, driven by meta.section instead of per-route blocks.
    if (to.meta.section && !auth.navSectionAllowed(to.meta.section)) {
        return deny(to.meta.title || 'that page');
    }

    // Prospects and Customers share one route but are separate permissions.
    if (to.name === 'customers') {
        const key = to.query.type === 'customer' ? 'customers' : 'prospects';
        if (!auth.navSectionAllowed(key)) {
            return deny(key === 'customers' ? 'Customers' : 'Prospects');
        }
    }

    next();
});

/**
 * Keep the browser tab title in step with the route.
 *
 * Every page previously showed the same static title from the Blade shell, so
 * a user with several tabs open could not tell them apart. Uses the same
 * resolver as the page heading and the breadcrumbs.
 */
router.afterEach((to) => {
    const appName = document.querySelector('meta[name="app-name"]')?.content || 'Switch & Save CRM';
    const title = resolveRouteTitle(to);

    document.title = title && title !== appName ? `${title} — ${appName}` : appName;
});

/**
 * Recover when a deploy pulls the file out from under an open tab.
 *
 * Every screen after the first is a lazily imported chunk with a content hash
 * in its name. Deploying replaces those files, so a browser that loaded the app
 * before the deploy is holding a map of filenames that no longer exist: the
 * next navigation asks for LeadsListView-BLvT_amt.js, gets a 404, and the route
 * simply never opens. Nothing on screen explains it, and the app looks broken
 * until somebody thinks to hard-refresh.
 *
 * A failed chunk fetch is not a bug to report, it is a stale tab. Reload it on
 * the route the person was trying to reach, so they land where they meant to.
 * The sessionStorage guard stops that becoming a refresh loop if the file is
 * genuinely missing rather than merely renamed.
 */
router.onError((error, to) => {
    const message = String(error?.message ?? '');

    const isStaleChunk = /dynamically imported module|Importing a module script failed|error loading dynamically imported/i
        .test(message);

    if (! isStaleChunk) {
        return;
    }

    const key = 'reloaded-for-stale-assets';
    const target = to?.fullPath ?? window.location.pathname;

    let previous = null;

    try {
        previous = JSON.parse(sessionStorage.getItem(key) ?? 'null');
    } catch {
        previous = null;
    }

    // Same route, moments ago: reloading again would not help.
    if (previous?.path === target && Date.now() - previous.at < 20000) {
        return;
    }

    try {
        sessionStorage.setItem(key, JSON.stringify({ path: target, at: Date.now() }));
    } catch {
        // A private window with storage blocked still gets one reload, just no
        // loop protection - which is the right way round.
    }

    window.location.assign(target);
});

export default router;


