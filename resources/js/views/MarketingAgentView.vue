<template>
    <ListingPageShell
        title="Marketing agent"
        subtitle="A weekly plan of who to contact and why. Nothing is sent until you approve it."
        :badge="plan ? `${counts.approved} approved` : null"
    >
        <template #actions>
            <BaseButton variant="outline" :loading="generating" @click="generate">
                <template #icon><SparklesIcon class="icon-sm" aria-hidden="true" /></template>
                {{ plan ? 'Rebuild this week' : 'Build this week' }}
            </BaseButton>
        </template>

        <div v-if="loading" class="card p-6"><div class="skeleton skeleton-text w-1/3" /></div>

        <EmptyState
            v-else-if="!plan"
            heading="No plan yet"
            description="Build one to see who the agent thinks is worth contacting this week, and why."
        />

        <div v-else class="space-y-4">
            <!-- What the rules did, so a short list is explained rather than mysterious. -->
            <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
                <div class="stat-card">
                    <p class="stat-label">Considered</p>
                    <p class="stat-value">{{ plan.rail_summary?.candidates ?? 0 }}</p>
                    <p class="stat-caption">Contacts eligible this week</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Suggested</p>
                    <p class="stat-value">{{ plan.rail_summary?.proposed ?? 0 }}</p>
                    <p class="stat-caption">Picked by the agent</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Ready to send</p>
                    <p class="stat-value text-success-700">{{ counts.approved }}</p>
                    <p class="stat-caption">Cap is {{ limits.weekly_cap }} a week</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Blocked</p>
                    <p class="stat-value" :class="counts.blocked ? 'text-danger-700' : ''">{{ counts.blocked }}</p>
                    <p class="stat-caption">Rules refused these</p>
                </div>
            </div>

            <div v-if="blockedReasons.length" class="callout callout-warning">
                <div class="min-w-0">
                    <p class="font-medium">Why some were left out</p>
                    <ul class="mt-1 text-sm space-y-0.5">
                        <li v-for="r in blockedReasons" :key="r.reason">{{ r.count }} × {{ r.reason }}</li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="flex flex-wrap items-center justify-between gap-3 p-4 border-b border-slate-200">
                    <div class="flex flex-wrap items-center gap-2">
                        <BaseButton size="sm" variant="outline" @click="bulk('approved')">Approve all</BaseButton>
                        <BaseButton size="sm" variant="outline" @click="bulk('skipped')">Skip all</BaseButton>
                        <span class="text-sm text-slate-500">Week of {{ formatDate(plan.week_starting) }}</span>
                    </div>
                    <BaseButton
                        variant="primary"
                        :disabled="!counts.approved || sending || !editable"
                        :loading="sending"
                        @click="confirmSendOpen = true"
                    >
                        Send {{ counts.approved }} message{{ counts.approved === 1 ? '' : 's' }}
                    </BaseButton>
                </div>

                <div class="table-wrap">
                    <table class="table min-w-[1000px]">
                        <caption class="sr-only">This week's proposed messages</caption>
                        <thead class="table-thead">
                            <tr>
                                <th scope="col" class="table-th">Customer / Company</th>
                                <th scope="col" class="table-th">Reason</th>
                                <th scope="col" class="table-th">Message</th>
                                <th scope="col" class="table-th">Channel</th>
                                <th scope="col" class="table-th text-right">Decision</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in sortedItems" :key="item.id" class="table-row" :class="rowTone(item)">
                                <td class="table-td w-[20rem]">
                                    <CustomerName :customer="item.customer" />
                                    <span class="mt-1 inline-flex items-center gap-1.5 text-xs text-slate-500">
                                        <BaseBadge :tone="item.customer?.type === 'customer' ? 'success' : 'primary'">
                                            {{ item.customer?.type === 'customer' ? 'Customer' : 'Prospect' }}
                                        </BaseBadge>
                                        <span v-if="item.customer?.city">{{ item.customer.city }}</span>
                                    </span>
                                </td>
                                <td class="table-td max-w-[24rem]">
                                    <span class="text-slate-700">{{ item.reason || '—' }}</span>
                                    <span v-if="item.blocked_reason" class="mt-1 block text-xs font-medium text-danger-700">
                                        {{ item.blocked_reason }}
                                    </span>
                                </td>
                                <td class="table-td">
                                    <span class="font-medium text-slate-900">{{ purposeLabel(item.purpose) }}</span>
                                    <span v-if="isEdited(item)" class="mt-0.5 block text-xs text-primary-700">Edited for this person</span>
                                    <button type="button" class="link text-xs" @click="openEditor(item)">
                                        {{ isEdited(item) ? 'Change again' : 'Edit for this person' }}
                                    </button>
                                </td>
                                <td class="table-td"><BaseBadge tone="neutral">{{ item.channel }}</BaseBadge></td>
                                <td class="table-td text-right whitespace-nowrap">
                                    <template v-if="item.status === 'blocked'">
                                        <span class="text-xs text-slate-500">Cannot send</span>
                                    </template>
                                    <template v-else-if="['sent', 'failed'].includes(item.status)">
                                        <BaseBadge :tone="item.status === 'sent' ? 'success' : 'danger'">{{ item.status }}</BaseBadge>
                                    </template>
                                    <template v-else>
                                        <BaseButton
                                            size="sm"
                                            :variant="item.status === 'approved' ? 'soft-success' : 'outline'"
                                            @click="setStatus(item, item.status === 'approved' ? 'pending' : 'approved')"
                                        >{{ item.status === 'approved' ? 'Approved' : 'Approve' }}</BaseButton>
                                        <BaseButton
                                            size="sm"
                                            variant="ghost"
                                            class="ml-1"
                                            @click="setStatus(item, 'skipped')"
                                        >Skip</BaseButton>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Per-person edit. Deliberately worded so it cannot be mistaken for editing the template. -->
        <BaseModal
            v-model="editorOpen"
            title="Edit this one message"
            :description="editing ? `Only ${editing.customer?.name || 'this contact'} will see these changes. Everyone else on ${purposeLabel(editing.purpose)} keeps the original wording.` : ''"
            size="lg"
            :close-on-backdrop="false"
        >
            <div v-if="editing" class="space-y-4">
                <div>
                    <label class="form-label" for="agent-subject">Subject</label>
                    <input id="agent-subject" v-model="editorForm.subject" type="text" class="form-input" />
                    <p class="form-hint">Leave empty to use the template's subject.</p>
                </div>
                <div>
                    <label class="form-label" for="agent-body">Message</label>
                    <textarea id="agent-body" v-model="editorForm.body" rows="12" class="form-textarea font-mono text-xs" />
                    <p class="form-hint">
                        Leave empty to use the template as it is. Merge tags like <code>{{ mergeTagExample }}</code> still work.
                    </p>
                </div>
            </div>

            <template #actions>
                <BaseButton variant="outline" block-mobile @click="editorOpen = false">Cancel</BaseButton>
                <BaseButton variant="primary" block-mobile :loading="savingEdit" @click="saveEdit">Save for this person</BaseButton>
            </template>
        </BaseModal>

        <ConfirmDialog
            v-model="confirmSendOpen"
            title="Send these messages?"
            :message="`${counts.approved} message(s) will be queued and go out over the next few minutes. This cannot be undone.`"
            confirm-label="Send them"
            :loading="sending"
            @confirm="send"
        />
    </ListingPageShell>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { SparklesIcon } from '@heroicons/vue/24/outline';
import { useToastStore } from '@/stores/toast';
import ListingPageShell from '@/components/ListingPageShell.vue';
import CustomerName from '@/components/CustomerName.vue';
import { BaseBadge, BaseButton, BaseModal, ConfirmDialog, EmptyState } from '@/components/base';

const toast = useToastStore();

const plan = ref(null);
const limits = ref({ weekly_cap: 50, min_days_between_messages: 30 });
const loading = ref(true);
const generating = ref(false);
const sending = ref(false);
const confirmSendOpen = ref(false);

const editorOpen = ref(false);
const editing = ref(null);
const editorForm = ref({ subject: '', body: '' });
const savingEdit = ref(false);

const PURPOSE_LABELS = {
    'welcome-onboarding': 'Welcome',
    'licence-renewal': 'Licence renewal',
    birthday: 'Birthday',
    'check-in': 'Check-in',
    'epos-upsell': 'ePOS upsell',
    'website-upsell': 'Website upsell',
    'bundle-upsell': 'Bundle offer',
    'funding-offer': 'Business funding',
    'quote-followup': 'Quote follow-up',
    winback: 'Win-back',
    'prospect-nurture': 'Nurture',
};

/** Written out rather than inlined: nested mustaches break the template parser. */
const mergeTagExample = '{' + '{first_name}' + '}';

const purposeLabel = (p) => PURPOSE_LABELS[p] || p;
const isEdited = (item) => Boolean(item.subject_override || item.body_override);
const editable = computed(() => ['draft', 'approved'].includes(plan.value?.status));

const items = computed(() => plan.value?.items || []);

/** Blocked rows sink to the bottom; the actionable ones are what you scroll to. */
const sortedItems = computed(() => [...items.value].sort((a, b) => {
    const rank = (i) => (i.status === 'blocked' ? 2 : i.status === 'skipped' ? 1 : 0);
    return rank(a) - rank(b) || a.priority - b.priority;
}));

const counts = computed(() => ({
    approved: items.value.filter((i) => i.status === 'approved').length,
    blocked: items.value.filter((i) => i.status === 'blocked').length,
    pending: items.value.filter((i) => i.status === 'pending').length,
}));

const blockedReasons = computed(() =>
    Object.entries(plan.value?.rail_summary?.blocked_reasons || {})
        .map(([reason, count]) => ({ reason, count }))
        .sort((a, b) => b.count - a.count),
);

function rowTone(item) {
    if (item.status === 'blocked') return 'opacity-60';
    if (item.status === 'skipped') return 'opacity-50';
    return '';
}

const formatDate = (d) => (d ? new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—');

async function load() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/marketing/agent/plans');
        limits.value = data.limits || limits.value;
        const latest = (data.data || [])[0];
        plan.value = latest ? (await axios.get(`/api/marketing/agent/plans/${latest.id}`)).data.plan : null;
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Could not load the plan.');
    } finally {
        loading.value = false;
    }
}

async function generate() {
    generating.value = true;
    try {
        const { data } = await axios.post('/api/marketing/agent/plans');
        plan.value = (await axios.get(`/api/marketing/agent/plans/${data.plan.id}`)).data.plan;
        toast.success('Plan built. Nothing has been sent.');
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Could not build the plan.');
    } finally {
        generating.value = false;
    }
}

async function setStatus(item, status) {
    try {
        const { data } = await axios.patch(`/api/marketing/agent/plans/${plan.value.id}/items/${item.id}`, { status });
        Object.assign(item, data.item);
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Could not update that row.');
    }
}

async function bulk(status) {
    try {
        await axios.post(`/api/marketing/agent/plans/${plan.value.id}/bulk`, { status });
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Could not update the rows.');
    }
}

function openEditor(item) {
    editing.value = item;
    editorForm.value = { subject: item.subject_override || '', body: item.body_override || '' };
    editorOpen.value = true;
}

async function saveEdit() {
    savingEdit.value = true;
    try {
        const { data } = await axios.patch(
            `/api/marketing/agent/plans/${plan.value.id}/items/${editing.value.id}`,
            { subject_override: editorForm.value.subject, body_override: editorForm.value.body },
        );
        Object.assign(editing.value, data.item);
        editorOpen.value = false;
        toast.success('Saved for this person only.');
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Could not save the edit.');
    } finally {
        savingEdit.value = false;
    }
}

async function send() {
    sending.value = true;
    try {
        const { data } = await axios.post(`/api/marketing/agent/plans/${plan.value.id}/send`);
        toast.success(data.message);
        confirmSendOpen.value = false;
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Could not send.');
    } finally {
        sending.value = false;
    }
}

onMounted(load);
</script>
