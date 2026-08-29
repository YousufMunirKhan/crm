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
                        <li v-for="r in blockedReasons" :key="r.reason">{{ r.count }} — {{ r.reason }}</li>
                    </ul>
                </div>
            </div>

            <div class="card overflow-hidden">
                <!-- One tab per channel: they send through different providers,
                     cost different amounts, and are not all switched on. -->
                <nav class="tab-list border-b border-slate-200 bg-slate-50/90" aria-label="Channel">
                    <button
                        v-for="tab in channelTabs"
                        :key="tab.channel"
                        type="button"
                        class="tab min-h-[42px]"
                        :class="activeChannel === tab.channel ? 'tab-active' : ''"
                        @click="activeChannel = tab.channel"
                    >
                        {{ tab.label }}
                        <span class="badge badge-neutral ml-1.5">{{ tab.total }}</span>
                    </button>
                </nav>

                <div class="flex flex-wrap items-center justify-between gap-3 p-4 border-b border-slate-200">
                    <span class="text-sm text-slate-500">Week of {{ formatDate(plan.week_starting) }}</span>
                    <BaseButton
                        variant="primary"
                        :disabled="!counts.approved || sending || !editable"
                        :loading="sending"
                        @click="confirmSendOpen = true"
                    >
                        Send {{ counts.approved }} message{{ counts.approved === 1 ? '' : 's' }}
                    </BaseButton>
                </div>

                <div v-if="!activeChannelEnabled" class="callout callout-warning m-4">
                    <div class="min-w-0">
                        <p class="font-medium">{{ activeChannelLabel }} is not switched on yet</p>
                        <p class="text-sm mt-0.5">
                            These are here so you can see who would qualify. They cannot be approved until the
                            {{ activeChannelLabel }} provider is connected.
                        </p>
                    </div>
                </div>

                <EmptyState
                    v-else-if="!groups.length"
                    heading="Nothing on this channel"
                    description="The agent did not pick anyone for this channel this week."
                />

                <!-- Grouped by reason, because that is how it actually sends: one
                     campaign per purpose. A flat list of sixty rows is sixty
                     decisions; this is one decision per group, and you only open
                     a group when something looks wrong. -->
                <div v-for="group in groups" :key="group.purpose" class="border-b border-slate-200 last:border-b-0">
                    <div class="flex flex-wrap items-start justify-between gap-3 p-4 bg-slate-50/60">
                        <button
                            type="button"
                            class="flex items-start gap-2 min-w-0 text-left"
                            @click="toggle(group.purpose)"
                        >
                            <ChevronRightIcon
                                class="icon-sm mt-0.5 shrink-0 text-slate-500 transition-transform"
                                :class="expanded[group.purpose] ? 'rotate-90' : ''"
                                aria-hidden="true"
                            />
                            <span class="min-w-0">
                                <span class="block font-semibold text-slate-900">
                                    {{ group.items.length }} × {{ purposeLabel(group.purpose) }}
                                </span>
                                <span class="block text-xs text-slate-500 mt-0.5">{{ group.subject || 'No subject set' }}</span>
                            </span>
                        </button>

                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <BaseButton size="sm" variant="ghost" @click="openPreview(group.items[0])">
                                <template #icon><EyeIcon class="icon-sm" aria-hidden="true" /></template>
                                Preview
                            </BaseButton>
                            <BaseButton size="sm" variant="ghost" @click="openTemplate(group)">
                                <template #icon><PencilSquareIcon class="icon-sm" aria-hidden="true" /></template>
                                Edit template
                            </BaseButton>
                            <BaseButton
                                size="sm"
                                :variant="group.allApproved ? 'soft-success' : 'outline'"
                                :disabled="!editable"
                                @click="approveGroup(group, !group.allApproved)"
                            >
                                {{ group.allApproved ? `All ${group.items.length} approved` : `Approve all ${group.items.length}` }}
                            </BaseButton>
                        </div>
                    </div>

                    <div v-if="expanded[group.purpose]" class="table-wrap">
                        <table class="table min-w-[820px]">
                            <caption class="sr-only">{{ purposeLabel(group.purpose) }} recipients</caption>
                            <thead class="table-thead">
                                <tr>
                                    <th scope="col" class="table-th">Customer / Company</th>
                                    <th scope="col" class="table-th">Why this person</th>
                                    <th scope="col" class="table-th text-right">Decision</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in group.items" :key="item.id" class="table-row" :class="rowTone(item)">
                                    <td class="table-td w-[18rem]">
                                        <CustomerName :customer="item.customer" />
                                        <span class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                                            <BaseBadge :tone="item.customer?.type === 'customer' ? 'success' : 'primary'">
                                                {{ item.customer?.type === 'customer' ? 'Customer' : 'Prospect' }}
                                            </BaseBadge>
                                            <span v-if="item.customer?.city">{{ item.customer.city }}</span>
                                        </span>
                                    </td>
                                    <td class="table-td max-w-[26rem]">
                                        <span class="text-slate-700">{{ item.reason || '—' }}</span>
                                        <span v-if="item.blocked_reason" class="mt-1 block text-xs font-medium text-danger-700">
                                            {{ item.blocked_reason }}
                                        </span>
                                        <span class="mt-1 flex flex-wrap gap-3">
                                            <button type="button" class="link text-xs" @click="openPreview(item)">Preview</button>
                                            <button type="button" class="link text-xs" @click="openEditor(item)">
                                                {{ isEdited(item) ? 'Edited for this person — change' : 'Edit for this person only' }}
                                            </button>
                                        </span>
                                    </td>
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
                                                :disabled="!editable"
                                                @click="setStatus(item, item.status === 'approved' ? 'pending' : 'approved')"
                                            >{{ item.status === 'approved' ? 'Approved' : 'Approve' }}</BaseButton>
                                            <BaseButton
                                                size="sm"
                                                variant="ghost"
                                                class="ml-1"
                                                :disabled="!editable"
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
        </div>

        <!-- Per-person override. Worded so it cannot be confused with the template. -->
        <BaseModal
            v-model="editorOpen"
            title="Edit this one message"
            :description="editing
                ? `Only ${editing.customer?.name || 'this contact'} sees this. Everyone else on ${purposeLabel(editing.purpose)} keeps the original.`
                : ''"
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

        <!-- Template edit. Says the count out loud, because this is the one that
             changes what everybody receives. -->
        <BaseModal
            v-model="templateOpen"
            title="Edit the template"
            :description="templateGroup
                ? `This changes the message for all ${templateGroup.items.length} ${purposeLabel(templateGroup.purpose)} recipients, and for future weeks too.`
                : ''"
            size="lg"
            :close-on-backdrop="false"
        >
            <div v-if="templateGroup" class="space-y-4">
                <div class="callout callout-warning">
                    <span>
                        Changing this affects <strong>{{ templateGroup.items.length }}</strong> people in this plan.
                        To change one person only, close this and use “Edit for this person only”.
                    </span>
                </div>
                <div>
                    <label class="form-label" for="tpl-subject">Subject</label>
                    <input id="tpl-subject" v-model="templateForm.subject" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label" for="tpl-body">Message</label>
                    <textarea id="tpl-body" v-model="templateForm.content" rows="14" class="form-textarea font-mono text-xs" />
                </div>
            </div>

            <template #actions>
                <BaseButton variant="outline" block-mobile @click="templateOpen = false">Cancel</BaseButton>
                <BaseButton variant="primary" block-mobile :loading="savingTemplate" @click="saveTemplate">
                    Save for everyone
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Rendered by the same code the real send uses, with this person's own
             data merged in - a preview built any other way is a drawing of the
             email rather than the email. -->
        <BaseModal v-model="previewOpen" title="Preview" size="xl">
            <div v-if="previewLoading" class="skeleton skeleton-text w-1/2" />
            <div v-else-if="preview" class="space-y-3">
                <div class="rounded-card border border-slate-200 bg-slate-50 p-3 text-sm space-y-1">
                    <p><span class="text-slate-500">To:</span> <span class="font-medium text-slate-900">{{ preview.to_name }}</span> &lt;{{ preview.to }}&gt;</p>
                    <p><span class="text-slate-500">Subject:</span> <span class="font-medium text-slate-900">{{ preview.subject }}</span></p>
                    <p v-if="preview.edited" class="text-primary-700">This copy was edited for this person only.</p>
                </div>

                <div v-if="preview.unresolved_tags?.length" class="callout callout-warning">
                    <span>
                        This contact has no value for {{ preview.unresolved_tags.join(', ') }} — the email would go out with a
                        gap where that should be. Fix the record or edit this one message.
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <BaseButton size="sm" :variant="previewWidth === 'desktop' ? 'soft' : 'outline'" @click="previewWidth = 'desktop'">Desktop</BaseButton>
                    <BaseButton size="sm" :variant="previewWidth === 'mobile' ? 'soft' : 'outline'" @click="previewWidth = 'mobile'">Mobile</BaseButton>
                </div>

                <div class="rounded-card border border-slate-200 bg-slate-100 p-3 overflow-x-auto">
                    <iframe
                        :srcdoc="preview.html"
                        class="block mx-auto bg-white border-0 rounded"
                        :style="{ width: previewWidth === 'mobile' ? '375px' : '100%', height: '640px' }"
                        title="Email preview"
                        sandbox=""
                    />
                </div>
            </div>

            <template #actions>
                <BaseButton variant="outline" block-mobile @click="previewOpen = false">Close</BaseButton>
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
import { ChevronRightIcon, EyeIcon, PencilSquareIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import { useToastStore } from '@/stores/toast';
import ListingPageShell from '@/components/ListingPageShell.vue';
import CustomerName from '@/components/CustomerName.vue';
import { BaseBadge, BaseButton, BaseModal, ConfirmDialog, EmptyState } from '@/components/base';

const toast = useToastStore();

const plan = ref(null);
const limits = ref({ weekly_cap: 50, min_days_between_messages: 30, enabled_channels: ['email'] });
const loading = ref(true);
const generating = ref(false);
const sending = ref(false);
const confirmSendOpen = ref(false);
const activeChannel = ref('email');
const expanded = ref({});

const editorOpen = ref(false);
const editing = ref(null);
const editorForm = ref({ subject: '', body: '' });
const savingEdit = ref(false);

const previewOpen = ref(false);
const preview = ref(null);
const previewLoading = ref(false);
const previewWidth = ref('desktop');

const templateOpen = ref(false);
const templateGroup = ref(null);
const templateForm = ref({ subject: '', content: '' });
const savingTemplate = ref(false);

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

const CHANNEL_LABELS = { email: 'Email', sms: 'SMS', whatsapp: 'WhatsApp' };

/** Nested mustaches break the template parser, so the example is built here. */
const mergeTagExample = '{' + '{first_name}' + '}';

const purposeLabel = (p) => PURPOSE_LABELS[p] || p;
const isEdited = (item) => Boolean(item.subject_override || item.body_override);
const editable = computed(() => ['draft', 'approved'].includes(plan.value?.status));
const items = computed(() => plan.value?.items || []);

const channelTabs = computed(() =>
    ['email', 'sms', 'whatsapp'].map((channel) => ({
        channel,
        label: CHANNEL_LABELS[channel],
        total: items.value.filter((i) => i.channel === channel).length,
    })).filter((t) => t.total > 0 || t.channel === 'email'),
);

const activeChannelEnabled = computed(() => (limits.value.enabled_channels || ['email']).includes(activeChannel.value));
const activeChannelLabel = computed(() => CHANNEL_LABELS[activeChannel.value]);

/** Grouped by reason, blocked rows last within each group. */
const groups = computed(() => {
    const map = new Map();

    for (const item of items.value) {
        if (item.channel !== activeChannel.value) continue;
        if (!map.has(item.purpose)) map.set(item.purpose, []);
        map.get(item.purpose).push(item);
    }

    return [...map.entries()]
        .map(([purpose, rows]) => {
            const sorted = [...rows].sort((a, b) => {
                const rank = (i) => (i.status === 'blocked' ? 2 : i.status === 'skipped' ? 1 : 0);
                return rank(a) - rank(b) || a.priority - b.priority;
            });
            const decidable = sorted.filter((i) => !['blocked', 'sent', 'failed'].includes(i.status));

            return {
                purpose,
                items: sorted,
                subject: sorted[0]?.email_template?.subject || '',
                templateId: sorted[0]?.email_template_id || null,
                allApproved: decidable.length > 0 && decidable.every((i) => i.status === 'approved'),
            };
        })
        .sort((a, b) => b.items.length - a.items.length);
});

const counts = computed(() => ({
    approved: items.value.filter((i) => i.status === 'approved').length,
    blocked: items.value.filter((i) => i.status === 'blocked').length,
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

function toggle(purpose) {
    expanded.value = { ...expanded.value, [purpose]: !expanded.value[purpose] };
}

const formatDate = (d) =>
    d ? new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

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

async function approveGroup(group, approve) {
    const ids = group.items.filter((i) => !['blocked', 'sent', 'failed'].includes(i.status)).map((i) => i.id);

    if (!ids.length) return;

    try {
        await axios.post(`/api/marketing/agent/plans/${plan.value.id}/bulk`, {
            status: approve ? 'approved' : 'pending',
            item_ids: ids,
        });
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Could not update the group.');
    }
}

async function openPreview(item) {
    if (!item) return;

    preview.value = null;
    previewLoading.value = true;
    previewOpen.value = true;

    try {
        const { data } = await axios.get(`/api/marketing/agent/plans/${plan.value.id}/items/${item.id}/preview`);
        preview.value = data;
    } catch (e) {
        previewOpen.value = false;
        toast.error(e?.response?.data?.message || 'Could not build the preview.');
    } finally {
        previewLoading.value = false;
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

async function openTemplate(group) {
    if (!group.templateId) {
        toast.error('This group has no template attached.');
        return;
    }
    try {
        const { data } = await axios.get(`/api/email-templates/${group.templateId}`);
        const t = data.data || data;
        templateGroup.value = group;
        templateForm.value = { subject: t.subject || '', content: t.content || '' };
        templateOpen.value = true;
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Could not open the template.');
    }
}

async function saveTemplate() {
    savingTemplate.value = true;
    try {
        await axios.put(`/api/email-templates/${templateGroup.value.templateId}`, {
            subject: templateForm.value.subject,
            content: templateForm.value.content,
        });
        templateOpen.value = false;
        toast.success('Template saved for everyone on this reason.');
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Could not save the template.');
    } finally {
        savingTemplate.value = false;
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
