<template>
    <ListingPageShell
        title="WhatsApp inbox"
        subtitle="Incoming WhatsApp conversations. Replies are only possible inside the 24-hour window."
        :badge="badge"
    >
        <div class="grid grid-cols-1 lg:grid-cols-[320px_1fr] min-h-[60vh]">
            <!-- Conversations -->
            <aside class="border-b lg:border-b-0 lg:border-r border-slate-100 min-w-0">
                <div class="p-3 border-b border-slate-100">
                    <label for="wa-search" class="sr-only">Search conversations</label>
                    <input
                        id="wa-search"
                        v-model="search"
                        type="text"
                        class="listing-input"
                        placeholder="Search name or number…"
                    />
                </div>

                <p v-if="loadingList" class="px-4 py-8 text-center text-sm text-slate-500">Loading…</p>

                <EmptyState
                    v-else-if="!filtered.length"
                    heading="No conversations"
                    description="Inbound WhatsApp messages appear here once a customer replies."
                />

                <ul v-else class="max-h-[60vh] overflow-y-auto divide-y divide-slate-100">
                    <li v-for="c in filtered" :key="c.id">
                        <button
                            type="button"
                            :class="[
                                'w-full text-left px-4 py-3 hover:bg-slate-50 transition-colors',
                                active?.id === c.id ? 'bg-primary-50' : '',
                            ]"
                            @click="open(c)"
                        >
                            <span class="flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-slate-800 truncate">
                                    {{ c.customer?.name || c.phone_number || 'Unknown' }}
                                </span>
                                <span
                                    v-if="withinWindow(c)"
                                    class="shrink-0 text-[10px] uppercase tracking-wide text-success-800 bg-success-50 rounded-full px-1.5 py-0.5"
                                >
                                    open
                                </span>
                            </span>
                            <span class="block text-xs text-slate-500 truncate">{{ c.phone_number }}</span>
                            <span class="block text-[11px] text-slate-500 mt-0.5">{{ when(c.last_message_at) }}</span>
                        </button>
                    </li>
                </ul>
            </aside>

            <!-- Messages -->
            <section class="min-w-0 flex flex-col">
                <EmptyState
                    v-if="!active"
                    heading="Select a conversation"
                    description="Messages appear here."
                />

                <template v-else>
                    <header class="px-5 py-3 border-b border-slate-100 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800 truncate">
                                {{ active.customer?.name || active.phone_number }}
                            </p>
                            <p class="text-xs text-slate-500">{{ active.phone_number }}</p>
                        </div>
                        <router-link
                            v-if="active.customer?.id"
                            :to="`/customers/${active.customer.id}`"
                            class="listing-link-edit shrink-0"
                        >
                            Open customer
                        </router-link>
                    </header>

                    <div ref="thread" class="flex-1 overflow-y-auto px-5 py-4 space-y-3 max-h-[52vh] bg-slate-50/40">
                        <p v-if="loadingMessages" class="text-center text-sm text-slate-500 py-6">Loading messages…</p>

                        <div
                            v-for="m in messages"
                            :key="m.id"
                            :class="['flex', m.direction === 'outbound' ? 'justify-end' : 'justify-start']"
                        >
                            <div
                                :class="[
                                    'max-w-[75%] rounded-card px-3.5 py-2 text-sm shadow-sm',
                                    m.direction === 'outbound'
                                        ? 'bg-primary-600 text-white'
                                        : 'bg-white text-slate-800 border border-slate-200',
                                ]"
                            >
                                <p class="whitespace-pre-wrap break-words">{{ body(m) }}</p>
                                <p
                                    :class="[
                                        'mt-1 text-[10px]',
                                        m.direction === 'outbound' ? 'text-white/70' : 'text-slate-500',
                                    ]"
                                >
                                    {{ when(m.created_at) }}
                                    <span v-if="m.direction === 'outbound' && m.status"> · {{ m.status }}</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <footer class="px-5 py-3 border-t border-slate-100">
                        <!--
                            Meta only permits free-form replies within 24 hours
                            of the customer's last message; outside it, a
                            pre-approved template is required.
                        -->
                        <div v-if="withinWindow(active)" class="flex items-end gap-2">
                            <div class="flex-1 min-w-0">
                                <label for="wa-reply" class="sr-only">Reply</label>
                                <textarea
                                    id="wa-reply"
                                    v-model="reply"
                                    rows="2"
                                    class="form-textarea"
                                    placeholder="Type a reply…"
                                    @keydown.enter.exact.prevent="send"
                                />
                            </div>
                            <button type="button" class="btn btn-lg btn-primary btn-block-mobile sm:w-auto" :disabled="sending || !reply.trim()" @click="send">
                                {{ sending ? 'Sending…' : 'Send' }}
                            </button>
                        </div>

                        <p v-else class="text-xs text-slate-500">
                            The 24-hour window has closed. Send an approved template from
                            <router-link to="/whatsapp-management" class="listing-link-edit">WhatsApp Management</router-link>.
                        </p>
                    </footer>
                </template>
            </section>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import axios from 'axios';
import ListingPageShell from '@/components/ListingPageShell.vue';
import EmptyState from '@/components/base/EmptyState.vue';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();

const conversations = ref([]);
const messages = ref([]);
const active = ref(null);
const search = ref('');
const reply = ref('');
const loadingList = ref(false);
const loadingMessages = ref(false);
const sending = ref(false);
const thread = ref(null);

const badge = computed(() => (conversations.value.length ? `${conversations.value.length} threads` : null));

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return conversations.value;

    return conversations.value.filter(
        (c) =>
            (c.customer?.name || '').toLowerCase().includes(q) ||
            (c.phone_number || '').toLowerCase().includes(q),
    );
});

function when(value) {
    if (!value) return '';
    const d = new Date(value);
    return d.toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

function body(message) {
    return message.body || message.content || message.text || '[media]';
}

/** Meta's 24-hour customer service window. */
function withinWindow(conversation) {
    const last = conversation?.window_expires_at || conversation?.last_inbound_at || conversation?.last_message_at;
    if (!last) return false;

    const expiry = conversation?.window_expires_at
        ? new Date(conversation.window_expires_at)
        : new Date(new Date(last).getTime() + 24 * 60 * 60 * 1000);

    return expiry > new Date();
}

async function loadConversations() {
    loadingList.value = true;
    try {
        const { data } = await axios.get('/api/whatsapp/conversations');
        conversations.value = data.data ?? data ?? [];
    } catch (e) {
        toast.error('Could not load conversations.');
    } finally {
        loadingList.value = false;
    }
}

async function open(conversation) {
    active.value = conversation;
    messages.value = [];
    loadingMessages.value = true;

    try {
        const { data } = await axios.get(`/api/whatsapp/conversations/${conversation.id}/messages`);
        messages.value = data.data ?? data ?? [];
        await nextTick();
        if (thread.value) thread.value.scrollTop = thread.value.scrollHeight;
    } catch {
        toast.error('Could not load messages.');
    } finally {
        loadingMessages.value = false;
    }
}

async function send() {
    const text = reply.value.trim();
    if (!text || !active.value) return;

    sending.value = true;
    try {
        await axios.post('/api/whatsapp/messages/send-text', {
            customer_id: active.value.customer?.id,
            phone: active.value.phone_number,
            message: text,
        });
        reply.value = '';
        await open(active.value);
        toast.success('Reply sent');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Could not send the reply.');
    } finally {
        sending.value = false;
    }
}

onMounted(loadConversations);
</script>
