<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto bg-black/50">
        <div
            class="bg-white rounded-xl shadow-xl w-full max-w-md my-8"
            @click.stop
        >
            <!-- Header with logo -->
            <div class="px-4 sm:px-6 pt-6 pb-4 text-center border-b border-slate-200">
                <img
                    v-if="logoUrl"
                    :src="logoUrl"
                    :alt="companyName"
                    class="h-12 mx-auto object-contain mb-3"
                />
                <div v-else class="h-12 flex items-center justify-center text-slate-400 text-2xl font-bold mb-3">
                    {{ companyName || 'Invoice' }}
                </div>
                <h2 class="text-lg font-semibold text-slate-900">Send invoice by email</h2>
            </div>

            <div class="px-4 sm:px-6 py-4 space-y-4">
                <p class="text-sm text-slate-600 leading-relaxed">
                    This is your invoice. It is valid from <strong>{{ validFromLabel }}</strong>.
                    The invoice PDF will be sent to the email address below. You can change the email if needed.
                </p>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Invoice will be sent to</label>
                    <input
                        v-model="email"
                        type="email"
                        required
                        placeholder="customer@example.com"
                        class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Message (optional)</label>
                    <textarea
                        v-model="message"
                        rows="3"
                        placeholder="Add a short message to include in the email..."
                        class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500 resize-none"
                    />
                </div>

                <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
            </div>

            <div class="px-4 sm:px-6 py-4 border-t border-slate-200 flex flex-col-reverse sm:flex-row gap-2 justify-end">
                <button
                    type="button"
                    @click="$emit('close')"
                    class="px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    :disabled="sending || !email"
                    @click="send"
                    class="px-4 py-2.5 bg-slate-900 text-white rounded-lg text-sm font-medium hover:bg-slate-800 disabled:opacity-50"
                >
                    {{ sending ? 'Sending...' : 'Send invoice' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

const props = defineProps({
    invoice: { type: Object, default: null },
    logoUrl: { type: String, default: '' },
    companyName: { type: String, default: '' },
});

const emit = defineEmits(['close', 'sent']);

const toast = useToastStore();
const email = ref('');
const message = ref('');
const sending = ref(false);
const error = ref(null);

const validFromLabel = computed(() => {
    const d = props.invoice?.invoice_date;
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
});

watch(() => props.invoice, (inv) => {
    if (inv?.customer?.email) {
        email.value = inv.customer.email;
    } else {
        email.value = '';
    }
    message.value = '';
    error.value = null;
}, { immediate: true });

async function send() {
    if (!props.invoice || !email.value) return;
    error.value = null;
    sending.value = true;
    try {
        await axios.post(`/api/invoices/${props.invoice.id}/send-email`, {
            email: email.value.trim(),
            message: message.value.trim() || undefined,
        });
        toast.success(`Invoice will be sent to ${email.value}`);
        emit('sent');
        emit('close');
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to send invoice email';
    } finally {
        sending.value = false;
    }
}
</script>
