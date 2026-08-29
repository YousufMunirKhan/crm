<template>
    <BaseCard class="overflow-hidden" :padded="false">
        <template #header>
            <div>
                <h3 class="card-title">History</h3>
                <p class="card-subtitle">Messages, appointments, notes, and stage changes in one place</p>
            </div>
        </template>
        <template #actions>
            <label class="sr-only" for="timelinesection-filter-type">Filter activity</label>
            <select id="timelinesection-filter-type"
                v-model="filterType"
                @change="applyFilters"
                class="form-select w-auto"
            >
                <option value="all">All activity</option>
                <option value="communication">All messages</option>
                <option value="email">Email</option>
                <option value="sms">SMS</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="lead_created">Leads</option>
                <option value="call">Calls</option>
                <option value="meeting">Meetings</option>
                <option value="appointment">Appointments</option>
                <option value="note">Notes</option>
                <option value="reminder">Follow-ups</option>
                <option value="stage_change">Stage changes</option>
                <option value="won">Won</option>
                <option value="lost">Lost</option>
                <option value="ticket">Tickets</option>
            </select>
        </template>

        <div class="p-4 sm:p-6 max-h-[500px] overflow-y-auto">
            <EmptyState
                v-if="groupedTimeline.length === 0"
                heading="No activity yet."
                description="Log calls, meetings, or send messages to build the timeline."
            >
                <template #icon>
                    <ClockIcon class="icon" aria-hidden="true" />
                </template>
            </EmptyState>

            <div v-else class="space-y-6">
                <div v-for="group in groupedTimeline" :key="group.date" class="space-y-3">
                    <div class="text-eyebrow text-slate-500 uppercase">{{ group.label }}</div>
                    <div class="space-y-2">
                        <div
                            v-for="item in group.items"
                            :key="item.id"
                            class="flex gap-3 p-3 rounded-control hover:bg-slate-50 transition-colors"
                        >
                            <div
                                class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 text-white"
                                :class="getTimelineIconClass(item)"
                            >
                                <component :is="getTimelineIcon(item)" class="icon" aria-hidden="true" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-baseline gap-2">
                                    <span class="font-medium text-slate-900">{{ item.title }}</span>
                                    <span class="text-xs text-slate-500">{{ item.when }}</span>
                                </div>
                                <p v-if="item.body" class="text-sm text-slate-600 mt-0.5 whitespace-pre-wrap">{{ item.body }}</p>
                                <p v-if="item.meta" class="text-xs text-slate-500 mt-1">{{ item.meta }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </BaseCard>
</template>

<script setup>
import { ref, computed } from 'vue';
import {
    ArrowPathIcon,
    BuildingOfficeIcon,
    CalendarDaysIcon,
    ChatBubbleLeftRightIcon,
    CheckCircleIcon,
    ClipboardDocumentIcon,
    ClockIcon,
    DevicePhoneMobileIcon,
    EnvelopeIcon,
    InformationCircleIcon,
    PencilSquareIcon,
    PhoneIcon,
    TicketIcon,
    UsersIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import { BaseCard, EmptyState } from '@/components/base';

const props = defineProps({
    timeline: {
        type: Array,
        default: () => [],
    },
});

const filterType = ref('all');

const filteredTimeline = computed(() => {
    if (filterType.value === 'all') {
        return props.timeline;
    }
    if (['email', 'sms', 'whatsapp'].includes(filterType.value)) {
        return props.timeline.filter(
            (item) => item.type === 'communication' && item.channel === filterType.value
        );
    }
    return props.timeline.filter((item) => item.type === filterType.value);
});

const groupedTimeline = computed(() => {
    const groups = {};
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    const weekAgo = new Date(today);
    weekAgo.setDate(weekAgo.getDate() - 7);

    filteredTimeline.value.forEach(item => {
        // Use created_at if available, otherwise parse "when" string
        let itemDate = new Date();

        if (item.created_at) {
            itemDate = new Date(item.created_at);
        } else {
            // Fallback: Parse the "when" string
            const when = item.when.toLowerCase();
            if (when.includes('second')) {
                itemDate = new Date(now.getTime() - parseInt(when) * 1000);
            } else if (when.includes('minute')) {
                itemDate = new Date(now.getTime() - parseInt(when) * 60 * 1000);
            } else if (when.includes('hour')) {
                itemDate = new Date(now.getTime() - parseInt(when) * 60 * 60 * 1000);
            } else if (when.includes('day')) {
                itemDate = new Date(now.getTime() - parseInt(when) * 24 * 60 * 60 * 1000);
            }
        }

        let groupKey = 'older';
        let groupLabel = 'Older';

        if (itemDate >= today) {
            groupKey = 'today';
            groupLabel = 'Today';
        } else if (itemDate >= yesterday) {
            groupKey = 'yesterday';
            groupLabel = 'Yesterday';
        } else if (itemDate >= weekAgo) {
            groupKey = 'this_week';
            groupLabel = 'This Week';
        }

        if (!groups[groupKey]) {
            groups[groupKey] = {
                date: groupKey,
                label: groupLabel,
                items: [],
            };
        }

        groups[groupKey].items.push(item);
    });

    // Sort groups by date (today first)
    const order = ['today', 'yesterday', 'this_week', 'older'];
    return order.filter(key => groups[key]).map(key => groups[key]);
});

const CHANNEL_ICONS = {
    email: EnvelopeIcon,
    sms: DevicePhoneMobileIcon,
    whatsapp: ChatBubbleLeftRightIcon,
};

const TYPE_ICONS = {
    communication: ChatBubbleLeftRightIcon,
    activity: PencilSquareIcon,
    ticket: TicketIcon,
    note: PencilSquareIcon,
    call: PhoneIcon,
    meeting: UsersIcon,
    appointment: CalendarDaysIcon,
    visit: BuildingOfficeIcon,
    reminder: ClockIcon,
    lead_created: ClipboardDocumentIcon,
    stage_change: ArrowPathIcon,
    won: CheckCircleIcon,
    lost: XCircleIcon,
};

const getTimelineIcon = (item) => {
    const type = item.type;
    if (type === 'communication' && item.channel) {
        return CHANNEL_ICONS[item.channel] || ChatBubbleLeftRightIcon;
    }
    return TYPE_ICONS[type] || InformationCircleIcon;
};

const getTimelineIconClass = (item) => {
    const type = item.type;
    if (type === 'communication' && item.channel === 'email') {
        return 'bg-primary-500';
    }
    if (type === 'communication' && item.channel === 'sms') {
        return 'bg-primary-500';
    }
    if (type === 'communication' && item.channel === 'whatsapp') {
        return 'bg-success-500';
    }
    const classes = {
        communication: 'bg-success-500',
        activity: 'bg-primary-500',
        ticket: 'bg-warning-500',
        note: 'bg-primary-500',
        call: 'bg-primary-500',
        meeting: 'bg-primary-600',
        appointment: 'bg-primary-500',
        visit: 'bg-primary-500',
        reminder: 'bg-warning-500',
        lead_created: 'bg-primary-500',
        stage_change: 'bg-primary-500',
        won: 'bg-success-500',
        lost: 'bg-danger-500',
    };
    return classes[type] || 'bg-slate-500';
};

const applyFilters = () => {
    // Filter is applied via computed property
};
</script>
