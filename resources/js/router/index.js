import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
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
        meta: { guest: true },
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
        meta: { requiresAuth: true, title: 'Lead Pipeline' },
    },
    {
        path: '/leads',
        name: 'leads-list',
        component: () => import('@/views/LeadsListView.vue'),
        meta: { requiresAuth: true, title: 'Leads' },
    },
    {
        path: '/tickets',
        name: 'tickets',
        component: TicketsView,
        meta: { requiresAuth: true, title: 'Tickets' },
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
        meta: { requiresAuth: true, title: 'POS Support', roles: ['Admin', 'Manager', 'System Admin'] },
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
        meta: { requiresAuth: true, title: 'My Report' },
    },
    {
        path: '/report/target-achievement',
        name: 'report-target-achievement',
        component: () => import('@/views/report/ReportTargetAchievementView.vue'),
        meta: { requiresAuth: true, title: 'Target vs Achievement', roles: ['Admin', 'Manager', 'System Admin'] },
    },
    {
        path: '/report/products-by-employee',
        name: 'report-products-by-employee',
        component: () => import('@/views/report/ReportProductsByEmployeeView.vue'),
        meta: { requiresAuth: true, title: 'Products Sold by Employee', roles: ['Admin', 'Manager', 'System Admin'] },
    },
    {
        path: '/reports',
        name: 'reports',
        component: ReportsView,
        meta: { requiresAuth: true, title: 'Reports & Analytics', roles: ['Admin', 'Manager', 'System Admin'] },
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
        path: '/expenses',
        name: 'expenses',
        component: () => import('@/views/ExpensesView.vue'),
        meta: { requiresAuth: true, title: 'Expense Management', roles: ['Admin', 'Manager'] },
    },
    {
        path: '/expenses/monthly-report',
        name: 'expenses-monthly-report',
        component: () => import('@/views/ExpensesMonthlyReportView.vue'),
        meta: { requiresAuth: true, title: 'Monthly Expense Report', roles: ['Admin', 'Manager'] },
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
        meta: { requiresAuth: true, title: 'Employee Goals', roles: ['Admin', 'Manager', 'System Admin'] },
    },
    {
        path: '/templates',
        name: 'templates',
        component: () => import('@/views/TemplatesView.vue'),
        meta: { requiresAuth: true, title: 'Email Templates', roles: ['Admin', 'System Admin'] },
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
        path: '/todays-report',
        name: 'todays-report',
        component: () => import('@/views/TodaysReportView.vue'),
        meta: { requiresAuth: true, title: "Today's Report", roles: ['Admin', 'Manager', 'System Admin'] },
    },
    {
        path: '/bulk-whatsapp',
        name: 'bulk-whatsapp',
        component: () => import('@/views/BulkWhatsAppView.vue'),
        meta: { requiresAuth: true, title: 'Bulk WhatsApp Messaging' },
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
        path: '/appointments',
        name: 'appointments',
        component: () => import('@/views/AppointmentsView.vue'),
        meta: { requiresAuth: true, title: 'My Appointments' },
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

    // Role-based routing: redirect sales agents to sales dashboard
    if (to.name === 'dashboard' && auth.isAuthenticated && auth.user) {
        const userRole = auth.user.role?.name;
        if (userRole === 'Sales' || userRole === 'CallAgent') {
            return next({ name: 'sales-dashboard' });
        }
    }

    // Redirect sales agents trying to access admin dashboard
    if (to.name === 'dashboard' && auth.isAuthenticated && auth.user) {
        const userRole = auth.user.role?.name;
        if (userRole === 'Sales' || userRole === 'CallAgent') {
            return next({ name: 'sales-dashboard' });
        }
    }

    // Role-based access control for routes
    if (to.meta.roles && auth.isAuthenticated && auth.user) {
        const userRole = auth.user.role?.name;
        if (!to.meta.roles.includes(userRole)) {
            // Redirect to dashboard if user doesn't have access
            return next({ name: 'dashboard' });
        }
    }

    next();
});

export default router;


