<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="modal-backdrop flex items-start justify-center p-4 pt-[12vh]"
            @click.self="close"
        >
            <div
                ref="panel"
                role="dialog"
                aria-modal="true"
                aria-label="Search and commands"
                class="w-full max-w-xl rounded-card bg-white shadow-xl shadow-slate-900/20 overflow-hidden"
            >
                <div class="flex items-center gap-3 border-b border-slate-100 px-4">
                    <MagnifyingGlassIcon class="icon text-slate-500" aria-hidden="true" />
                    <input
                        ref="input"
                        v-model="query"
                        type="text"
                        class="flex-1 py-3.5 text-sm text-slate-900 placeholder:text-slate-500 focus-visible:outline-none"
                        placeholder="Name, company, phone, email, invoice or ticket…"
                        aria-label="Search"
                        @keydown.down.prevent="move(1)"
                        @keydown.up.prevent="move(-1)"
                        @keydown.enter.prevent="run(results[cursor])"
                    />
                    <kbd class="kbd hidden sm:block">Esc</kbd>
                </div>

                <ul v-if="results.length" class="max-h-80 overflow-y-auto py-2" role="listbox">
                    <li
                        v-for="(item, i) in results"
                        :key="item.key"
                        role="option"
                        :aria-selected="i === cursor"
                        :class="[
                            'flex items-center justify-between gap-3 px-4 py-2.5 cursor-pointer',
                            i === cursor ? 'bg-primary-50' : 'hover:bg-slate-50',
                        ]"
                        @mouseenter="cursor = i"
                        @click="run(item)"
                    >
                        <span class="min-w-0">
                            <span class="block text-sm text-slate-800 truncate">{{ item.label }}</span>
                            <span v-if="item.detail" class="block text-xs text-slate-500 truncate">{{ item.detail }}</span>
                        </span>
                        <span class="shrink-0 text-eyebrow uppercase text-slate-500">{{ item.group }}</span>
                    </li>
                </ul>

                <p v-else class="px-4 py-8 text-center text-sm text-slate-500">
                    {{ query ? 'Nothing matched.' : 'Start typing to search.' }}
                </p>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { useAuthStore } from '@/stores/auth';
import { useFocusTrap } from '@/composables/useFocusTrap';

const router = useRouter();
const auth = useAuthStore();

const open = ref(false);
const query = ref('');
const cursor = ref(0);
const panel = ref(null);
const input = ref(null);
const remote = ref([]);

/** Every navigable destination, filtered by the user's permissions. */
const pages = computed(() => {
    const allow = (section) => !section || auth.navSectionAllowed(section);

    return [
        { label: 'Dashboard', to: '/', section: 'dashboard' },
        { label: 'Prospects', to: '/customers?type=prospect', section: 'prospects' },
        { label: 'Customers', to: '/customers?type=customer', section: 'customers' },
        { label: 'All Leads', to: '/leads', section: 'all_leads' },
        { label: 'Lead Pipeline', to: '/leads/pipeline', section: 'lead_pipeline' },
        { label: 'Follow-ups', to: '/followups', section: 'followups' },
        { label: 'Appointments', to: '/appointments', section: 'appointments' },
        { label: 'Tickets', to: '/tickets', section: 'tickets' },
        { label: 'POS Support', to: '/pos-support', section: 'pos_support' },
        { label: 'Invoices', to: '/invoices', section: 'invoices' },
        { label: 'Products', to: '/products', section: 'products' },
        { label: 'Email Management', to: '/email-management', section: 'marketing_email' },
        { label: 'SMS Management', to: '/sms-management', section: 'marketing_sms' },
        { label: 'WhatsApp Management', to: '/whatsapp-management', section: 'marketing_whatsapp' },
        { label: 'WhatsApp Inbox', to: '/whatsapp-inbox', section: 'marketing_whatsapp' },
        { label: 'Cold calling', to: '/cold-calling', section: 'marketing_cold_calling' },
        { label: 'Business Reports', to: '/reports', section: 'report' },
        { label: 'Geo map', to: '/reports/geo', section: 'report' },
        { label: 'My Report', to: '/report/my-report', section: 'report' },
        { label: 'Employees', to: '/employees', section: 'employees' },
        { label: 'Expenses', to: '/expenses', section: 'expenses' },
        { label: 'Settings', to: '/settings', section: 'settings' },
        { label: 'My profile', to: '/profile' },
        { label: 'Back office (admin panel)', to: '/admin', external: true },
    ]
        .filter((p) => allow(p.section))
        .map((p) => ({ ...p, key: 'page:' + p.to, group: 'Go to' }));
});

const results = computed(() => {
    const q = query.value.trim().toLowerCase();

    if (!q) return pages.value.slice(0, 8);

    const matchedPages = pages.value.filter((p) => p.label.toLowerCase().includes(q));

    return [...matchedPages, ...remote.value].slice(0, 24);
});

watch(results, () => { cursor.value = 0; });

// Records are searched server-side; pages are filtered locally.
let debounce = null;
watch(query, (value) => {
    clearTimeout(debounce);
    const q = value.trim();

    if (q.length < 2) {
        remote.value = [];
        return;
    }

    debounce = setTimeout(() => searchRecords(q), 250);
});

/** Rows of the list, or the list itself when the endpoint is not paginated. */
function rowsOf(response) {
    const data = response?.data;
    return data?.data ?? data ?? [];
}

/**
 * A customer is a person at a company, and the palette used to show
 * `business_name || name` - so for anyone with a company on file the contact's
 * own name never appeared, and searching for that person looked like it had
 * failed even though the row on screen was theirs. Both are shown now.
 */
function customerLabel(customer) {
    const contact = customer.name?.trim();
    const company = customer.business_name?.trim();

    if (contact && company) return `${contact} - ${company}`;

    return company || contact || 'Unnamed customer';
}

/**
 * Only customers were searchable from here, so a lead, an invoice or a ticket
 * could only be reached by opening its list first. Each endpoint is asked
 * independently and a slow or failing one just contributes nothing.
 */
async function searchRecords(q) {
    const params = { search: q, per_page: 5 };

    const [customers, leads, invoices, tickets] = await Promise.all([
        axios.get('/api/customers', { params }).catch(() => null),
        axios.get('/api/leads', { params }).catch(() => null),
        axios.get('/api/invoices', { params }).catch(() => null),
        axios.get('/api/tickets', { params }).catch(() => null),
    ]);

    // The query moved on while these were in flight.
    if (query.value.trim() !== q) return;

    const found = [];

    rowsOf(customers).slice(0, 5).forEach((c) => found.push({
        key: 'customer:' + c.id,
        label: customerLabel(c),
        detail: [c.phone, c.email].filter(Boolean).join(' · '),
        to: `/customers/${c.id}`,
        group: c.type === 'prospect' ? 'Prospect' : 'Customer',
    }));

    rowsOf(leads).slice(0, 5).forEach((l) => found.push({
        key: 'lead:' + l.id,
        label: `#${l.id} ${customerLabel(l.customer || {})}`,
        detail: [l.stage?.replace('_', ' '), l.product?.name].filter(Boolean).join(' · '),
        to: `/leads/${l.id}`,
        group: 'Lead',
    }));

    rowsOf(invoices).slice(0, 5).forEach((i) => found.push({
        key: 'invoice:' + i.id,
        label: i.invoice_number,
        detail: [customerLabel(i.customer || {}), i.status].filter(Boolean).join(' · '),
        to: `/invoices/${i.id}/edit`,
        group: 'Invoice',
    }));

    rowsOf(tickets).slice(0, 5).forEach((t) => found.push({
        key: 'ticket:' + t.id,
        label: t.subject || t.ticket_number,
        detail: [customerLabel(t.customer || {}), t.status?.replace('_', ' ')].filter(Boolean).join(' · '),
        to: `/tickets/${t.id}`,
        group: 'Ticket',
    }));

    remote.value = found;
}

function move(delta) {
    if (!results.value.length) return;
    cursor.value = (cursor.value + delta + results.value.length) % results.value.length;
}

function run(item) {
    if (!item) return;
    close();
    if (item.external) {
        window.location.href = item.to;
        return;
    }
    router.push(item.to);
}

function show() {
    open.value = true;
    query.value = '';
    remote.value = [];
    nextTick(() => input.value?.focus());
}

function close() {
    open.value = false;
}

function onKeydown(event) {
    // Ctrl/Cmd+K opens; Escape closes.
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        open.value ? close() : show();
        return;
    }
    if (event.key === 'Escape' && open.value) {
        close();
    }
}

useFocusTrap(
    panel,
    computed(() => open.value),
    close,
);

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));

defineExpose({ show });
</script>
