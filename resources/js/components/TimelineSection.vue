<template>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-slate-200 bg-slate-50">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Activity & Communication</h3>
                    <p class="text-sm text-slate-500 mt-0.5">Record of where we are with this customer</p>
                </div>
                <select
                    v-model="filterType"
                    @change="applyFilters"
                    class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500 bg-white"
                >
                    <option value="all">All activity</option>
                    <option value="communication">Messages</option>
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
            </div>
        </div>

        <div class="p-4 sm:p-6 max-h-[500px] overflow-y-auto">
            <div v-if="groupedTimeline.length === 0" class="text-center py-12 text-slate-400">
                <p class="text-sm">No activity yet.</p>
                <p class="text-xs mt-1">Log calls, meetings, or send messages to build the timeline.</p>
            </div>

            <div v-else class="space-y-6">
                <div v-for="group in groupedTimeline" :key="group.date" class="space-y-3">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ group.label }}</div>
                    <div class="space-y-2">
                        <div
                            v-for="item in group.items"
                            :key="item.id"
                            class="flex gap-3 p-3 rounded-lg hover:bg-slate-50 transition-colors"
                        >
                            <div
                                class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"
                                :class="getTimelineIconClass(item.type)"
                            >
                                <span class="text-base">{{ getTimelineIcon(item.type) }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-baseline gap-2">
                                    <span class="font-medium text-slate-900">{{ item.title }}</span>
                                    <span class="text-xs text-slate-400">{{ item.when }}</span>
                                </div>
                                <p v-if="item.body" class="text-sm text-slate-600 mt-0.5 whitespace-pre-wrap">{{ item.body }}</p>
                                <p v-if="item.meta" class="text-xs text-slate-500 mt-1">{{ item.meta }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

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
    return props.timeline.filter(item => item.type === filterType.value);
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

const getTimelineIcon = (type) => {
    const icons = {
        communication: '💬',
        activity: '📝',
        ticket: '🎫',
        note: '📝',
        call: '📞',
        meeting: '🤝',
        appointment: '📅',
        visit: '🏢',
        reminder: '⏰',
        lead_created: '📋',
        stage_change: '🔄',
        won: '✓',
        lost: '✗',
    };
    return icons[type] || '•';
};

const getTimelineIconClass = (type) => {
    const classes = {
        communication: 'bg-emerald-500',
        activity: 'bg-blue-500',
        ticket: 'bg-orange-500',
        note: 'bg-purple-500',
        call: 'bg-indigo-500',
        meeting: 'bg-pink-500',
        appointment: 'bg-violet-500',
        visit: 'bg-teal-500',
        reminder: 'bg-amber-500',
        lead_created: 'bg-cyan-500',
        stage_change: 'bg-sky-500',
        won: 'bg-green-500',
        lost: 'bg-red-500',
    };
    return classes[type] || 'bg-slate-400';
};

const applyFilters = () => {
    // Filter is applied via computed property
};
</script>

