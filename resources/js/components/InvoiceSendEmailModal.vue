<template>
    <!--
      `title` (not #header) so BaseModal keeps its aria-labelledby wiring; the
      branding strip rides at the top of the body instead.
    -->
    <BaseModal
        :model-value="true"
        title="Send invoice by email"
        size="sm"
        :close-on-backdrop="false"
        @close="$emit('close')"
    >
        <div class="space-y-4">
            <div class="text-center">
                <img
                    v-if="logoUrl"
                    :src="logoUrl"
                    :alt="companyName"
                    class="mx-auto h-12 object-contain"
                />
                <div v-else class="flex h-12 items-center justify-center text-2xl font-bold text-slate-500">
                    {{ companyName || 'Invoice' }}
                </div>
            </div>
            <p class="text-sm leading-relaxed text-slate-600">
                This is your invoice. It is valid from <strong>{{ validFromLabel }}</strong>.
                The invoice PDF will be sent to the email address below. You can change the email if needed.
            </p>

            <div>
                <label class="form-label" for="invoicesendemailmodal-invoice-will-be-sent-to">Invoice will be sent to</label>
                <input
                    id="invoicesendemailmodal-invoice-will-be-sent-to"
                    v-model="email"
                    type="email"
                    required
                    placeholder="customer@example.com"
                    class="form-input"
                />
            </div>

            <div>
                <label class="form-label" for="invoicesendemailmodal-message-optional">Message (optional)</label>
                <textarea
                    id="invoicesendemailmodal-message-optional"
                    v-model="message"
                    rows="3"
                    placeholder="Add a short message to include in the email..."
                    class="form-textarea resize-none"
                />
            </div>

            <p v-if="error" class="callout callout-danger" role="alert">{{ error }}</p>
        </div>

        <template #actions>
            <BaseButton variant="outline" block-mobile @click="$emit('close')">
                Cancel
            </BaseButton>
            <BaseButton
                variant="primary"
                block-mobile
                :disabled="sending || !email"
                :loading="sending"
                @click="send"
            >
                <template #icon>
                    <PaperAirplaneIcon class="icon" aria-hidden="true" />
                </template>
                {{ sending ? 'Sending...' : 'Send invoice' }}
            </BaseButton>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { PaperAirplaneIcon } from '@heroicons/vue/24/outline';
import { BaseButton, BaseModal } from '@/components/base';
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
