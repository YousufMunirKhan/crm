<template>
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
            <div class="p-4 border-b border-slate-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-slate-900">Schedule Follow-up</h2>
                <button type="button" @click="$emit('close')" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <form @submit.prevent="handleSubmit" class="p-4 space-y-4">
                <p v-if="lead" class="text-sm text-slate-600">{{ lead.customer?.name }} — Lead #{{ lead.id }}</p>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Date & time *</label>
                    <input
                        v-model="form.next_follow_up_at"
                        type="datetime-local"
                        required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Note (optional)</label>
                    <textarea
                        v-model="form.comment"
                        rows="2"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="e.g. Call back about quote"
                    />
                </div>
                <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
                <div class="flex gap-2 justify-end pt-2">
                    <button type="button" @click="$emit('close')" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" :disabled="loading" class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50">Schedule</button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

const props = defineProps({ lead: { type: Object, default: null } });
const emit = defineEmits(['saved', 'close']);
const toast = useToastStore();

const form = ref({ next_follow_up_at: '', comment: '' });
const loading = ref(false);
const error = ref('');

function setDefaultTime() {
    const d = new Date();
    d.setHours(d.getHours() + 1, 0, 0, 0);
    form.value.next_follow_up_at = d.toISOString().slice(0, 16);
}

watch(() => props.lead, () => {
    error.value = '';
    setDefaultTime();
    form.value.comment = '';
}, { immediate: true });

async function handleSubmit() {
    if (!props.lead?.id) return;
    loading.value = true;
    error.value = '';
    try {
        await axios.post(`/api/leads/${props.lead.id}/followup`, {
            next_follow_up_at: form.value.next_follow_up_at,
            comment: form.value.comment || undefined,
        });
        toast.success('Follow-up scheduled.');
        emit('saved');
        emit('close');
    } catch (err) {
        const msg = err.response?.data?.message || err.response?.data?.errors?.next_follow_up_at?.[0] || 'Failed to schedule follow-up.';
        error.value = msg;
        toast.error(msg);
    } finally {
        loading.value = false;
    }
}
</script>
