<template>
    <Menu as="div" class="relative shrink-0">
        <MenuButton
            class="relative grid h-10 w-10 place-items-center rounded-control border border-slate-200 bg-white
                   text-slate-500 transition-colors touch-manipulation hover:bg-slate-50 hover:text-slate-800
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
            :aria-label="unread > 0 ? `Notifications, ${unread} unread` : 'Notifications'"
            @click="load"
        >
            <BellIcon class="icon-sm" aria-hidden="true" />
            <span
                v-if="unread > 0"
                class="absolute -right-1 -top-1 grid min-w-[18px] place-items-center rounded-full
                       bg-danger-600 px-1 text-[10px] font-bold leading-[18px] text-white"
                aria-hidden="true"
            >{{ unread > 9 ? '9+' : unread }}</span>
        </MenuButton>

        <MenuItems
            class="popover-panel absolute right-0 mt-2 w-[calc(100vw-2rem)] max-w-sm focus-visible:outline-none"
        >
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-3 py-2">
                <p class="text-xs font-semibold text-slate-800">What needs you</p>
                <button
                    v-if="unread > 0"
                    type="button"
                    class="text-[11px] font-medium text-primary-700 hover:underline"
                    @click.stop="markAllRead"
                >
                    Mark all read
                </button>
            </div>

            <div class="max-h-[min(70vh,26rem)] overflow-y-auto overscroll-contain">
                <p v-if="loading" class="px-3 py-6 text-center text-xs text-slate-500">Loading…</p>

                <p v-else-if="items.length === 0" class="px-3 py-6 text-center text-xs text-slate-500">
                    Nothing overdue and nothing gone quiet. That is the good version of this list.
                </p>

                <MenuItem
                    v-for="item in items"
                    :key="item.id"
                    v-slot="{ active }"
                >
                    <button
                        type="button"
                        class="flex w-full items-start gap-2.5 border-b border-slate-50 px-3 py-2.5 text-left last:border-b-0"
                        :class="[active ? 'bg-slate-50' : '', item.read_at ? 'opacity-60' : '']"
                        @click="open(item)"
                    >
                        <span
                            class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full"
                            :class="item.read_at ? 'bg-transparent' : 'bg-danger-500'"
                            aria-hidden="true"
                        />
                        <span class="min-w-0">
                            <span class="block text-xs font-semibold text-slate-800">{{ item.title }}</span>
                            <span class="mt-0.5 block text-[11px] leading-snug text-slate-600">{{ item.message }}</span>
                            <span class="mt-1 block text-[10px] text-slate-400">{{ when(item.created_at) }}</span>
                        </span>
                    </button>
                </MenuItem>
            </div>
        </MenuItems>
    </Menu>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue';
import { BellIcon } from '@heroicons/vue/24/outline';
import { useRouter } from 'vue-router';
import axios from 'axios';

/**
 * The bell for a notifications table that has never had a reader.
 *
 * The model, the migration and four API endpoints have all been sitting here
 * finished, with no caller anywhere in the frontend - so a lead going cold, a
 * follow-up going past its date and a lead sitting unassigned have all produced
 * exactly nothing. Of 177 open leads, 169 have had no contact in a month.
 *
 * Every item here is already visible on some screen. The point is that a screen
 * has to be visited and this does not.
 */
const router = useRouter();

const items = ref([]);
const unread = ref(0);
const loading = ref(false);

let timer = null;

async function refreshCount() {
    try {
        const { data } = await axios.get('/api/notifications/unread-count');
        unread.value = data?.count ?? 0;
    } catch {
        // A dead count is not worth a toast; the bell simply stays quiet.
    }
}

async function load() {
    loading.value = true;

    try {
        const { data } = await axios.get('/api/notifications');
        items.value = Array.isArray(data) ? data : [];
    } catch {
        items.value = [];
    } finally {
        loading.value = false;
    }
}

async function markAllRead() {
    unread.value = 0;
    items.value = items.value.map((i) => ({ ...i, read_at: i.read_at ?? new Date().toISOString() }));

    try {
        await axios.put('/api/notifications/read-all');
    } catch {
        await refreshCount();
    }
}

/**
 * Opening one marks it read and goes where the work is. A notification that
 * does not take you to the thing it is about is just a reminder to go looking.
 */
async function open(item) {
    if (! item.read_at) {
        unread.value = Math.max(0, unread.value - 1);
        item.read_at = new Date().toISOString();
        axios.put(`/api/notifications/${item.id}/read`).catch(() => refreshCount());
    }

    const to = destination(typeof item.data === 'string' ? safeParse(item.data) : item.data);

    if (to) {
        router.push(to);
    }
}

/**
 * Where a notification takes you.
 *
 * The SLA check and the marketing automations have been writing `ticket_id` and
 * `customer_id` since long before anything read them - 38 unread rows are
 * sitting there now - so those are understood as well as the `route` newer
 * ones carry. A notification that does not take you to the thing it is about
 * is only a reminder to go looking.
 */
function destination(data) {
    if (! data) return null;
    if (data.route) return data.route;
    if (data.ticket_id) return `/tickets/${data.ticket_id}`;
    if (data.lead_id) return `/leads/${data.lead_id}`;
    if (data.customer_id) return `/customers/${data.customer_id}`;

    return null;
}

function safeParse(value) {
    try {
        return JSON.parse(value);
    } catch {
        return null;
    }
}

function when(value) {
    if (! value) return '';

    const then = new Date(value.replace(' ', 'T'));
    const hours = Math.floor((Date.now() - then.getTime()) / 3600000);

    if (hours < 1) return 'Just now';
    if (hours < 24) return `${hours}h ago`;

    const days = Math.floor(hours / 24);

    return days === 1 ? 'Yesterday' : `${days} days ago`;
}

onMounted(() => {
    refreshCount();
    // These are raised once a morning, so anything more frequent is a request
    // per user per minute for a number that cannot have changed.
    timer = setInterval(refreshCount, 5 * 60 * 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>
