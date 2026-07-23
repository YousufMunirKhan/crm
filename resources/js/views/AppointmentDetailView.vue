<template>
    <div class="max-w-3xl mx-auto p-3 sm:p-6 space-y-4 sm:space-y-6">
        <div class="flex items-center gap-2 sm:gap-3 min-w-0">
            <router-link
                to="/appointments"
                class="p-2 text-slate-600 hover:bg-slate-100 rounded-lg shrink-0 touch-manipulation"
                aria-label="Back to appointments"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </router-link>
            <h1 class="text-lg sm:text-xl font-bold text-slate-900 truncate">Appointment Details</h1>
        </div>

        <div v-if="loading" class="text-center py-12 text-slate-500">Loading...</div>
        <template v-else-if="appointment">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 sm:p-5 space-y-4">
                <div>
                    <div class="text-sm text-slate-500">{{ appointmentCustomerTypeLabel }}</div>
                    <div class="font-semibold text-slate-900">
                        {{ appointment.business_name || appointment.customer?.business_name || appointment.customer?.name || '-' }}
                    </div>
                    <div v-if="appointment.business_name || appointment.customer?.business_name" class="text-sm text-slate-600">
                        Contact: {{ appointment.customer?.name || 'N/A' }}
                    </div>
                    <div class="text-sm text-slate-600">
                        {{ appointment.customer?.phone || '' }}
                        {{ appointment.customer?.email || '' }}
                    </div>
                    <div v-if="appointment.customer?.address" class="text-sm text-slate-600">
                        {{ appointment.customer?.address }}
                    </div>
                    <div v-if="appointment.customer?.city || appointment.customer?.postcode" class="text-sm text-slate-600">
                        {{ appointment.customer?.city || '' }}
                        {{ appointment.customer?.postcode || '' }}
                    </div>
                    <div class="mt-2 text-xs text-slate-500 space-y-0.5">
                        <div>Created by: <span class="font-medium text-slate-700">{{ appointment.user?.name || 'Unknown' }}</span></div>
                        <div v-if="appointment.assignee">
                            Assigned to: <span class="font-medium text-slate-700">{{ appointment.assignee.name }}</span>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="text-sm text-slate-500">Lead #</div>
                    <router-link :to="`/leads/${appointment.lead_id}`" class="text-blue-600 hover:underline">
                        {{ appointment.lead_id }}
                    </router-link>
                </div>

                <div class="rounded-lg border border-teal-100 bg-teal-50/70 p-4 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold text-teal-900">Sales brief</div>
                            <div class="text-xs text-teal-700">Who this is for, what to sell, and where the lead stands.</div>
                        </div>
                        <span class="px-2 py-1 rounded bg-white text-xs font-medium text-teal-800 border border-teal-100">
                            {{ formatStage(appointment.lead?.stage) || 'No stage' }}
                        </span>
                    </div>

                    <div>
                        <div class="text-xs font-medium text-teal-800 mb-1">What to sell</div>
                        <div v-if="productsToSell.length" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="product in productsToSell"
                                :key="product"
                                class="px-2 py-1 rounded bg-white text-teal-800 border border-teal-100 text-xs font-medium"
                            >
                                {{ product }}
                            </span>
                        </div>
                        <div v-else class="text-sm text-amber-700">
                            No product is attached to this lead yet. Open the lead and add the product before attending.
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                        <div>
                            <div class="text-xs text-teal-800">Source</div>
                            <div class="font-medium text-slate-900">{{ appointment.lead?.source || 'Not set' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-teal-800">Lead owner</div>
                            <div class="font-medium text-slate-900">{{ appointment.lead?.assignee?.name || 'Unassigned' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-teal-800">Pipeline value</div>
                            <div class="font-medium text-slate-900">GBP {{ money(appointment.lead?.pipeline_value || 0) }}</div>
                        </div>
                    </div>
                </div>

                <div v-if="appointment.description">
                    <div class="text-sm text-slate-500">Notes</div>
                    <p class="text-slate-700 whitespace-pre-line">{{ appointment.description }}</p>
                </div>
            </div>

            <form @submit.prevent="save" class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 sm:p-5 space-y-4 sm:space-y-5">
                <h2 class="font-semibold text-slate-900">Update appointment</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Appointment date</label>
                        <input
                            v-model="form.appointment_date"
                            type="date"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Appointment time</label>
                        <input
                            v-model="form.appointment_time"
                            type="time"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                    <select
                        v-model="form.appointment_status"
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no_show">No show</option>
                        <option value="rescheduled">Rescheduled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">What happened (outcome notes)</label>
                    <textarea
                        v-model="form.outcome_notes"
                        rows="3"
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                        placeholder="Summarise what happened at the appointment..."
                    />
                </div>

                <div class="border-t border-slate-200 pt-4 space-y-4">
                    <div class="text-sm font-medium text-slate-700">Lead outcome (optional)</div>
                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2">
                            <input type="radio" v-model="form.lead_stage" value="won" class="text-green-600" />
                            <span>Won</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" v-model="form.lead_stage" value="lost" class="text-red-600" />
                            <span>Lost</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" v-model="form.lead_stage" value="" class="text-slate-600" />
                            <span>No change</span>
                        </label>
                    </div>

                    <div v-if="form.lead_stage === 'won'" class="mt-4 space-y-4">
                        <div class="text-sm font-medium text-slate-700">Products achieved</div>
                        <p class="text-xs text-slate-500">Select products that were sold during this appointment.</p>
                        <div v-if="!pendingItems.length && !wonItemsList.length" class="text-sm text-slate-500 py-4 bg-slate-50 rounded-lg px-4">
                            No products on this lead. Add products on the <router-link :to="`/leads/${appointment.lead_id}`" class="text-blue-600 hover:underline">Lead page</router-link> first.
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="item in pendingItems"
                                :key="item.id"
                                class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 sm:p-4 rounded-lg border border-slate-200 bg-white"
                            >
                                <label class="flex items-center gap-2 shrink-0 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        :checked="isItemSelected(item.id)"
                                        @change="toggleWonItem(item)"
                                        class="rounded border-slate-300 text-green-600"
                                    />
                                    <span class="font-medium text-slate-900">{{ item.product?.name || 'Product' }}</span>
                                </label>
                                <div v-if="isItemSelected(item.id)" class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 flex-1">
                                    <div class="flex-1 min-w-0 w-full sm:w-auto">
                                        <label class="block text-xs text-slate-500 mb-0.5">Quantity</label>
                                        <input
                                            type="number"
                                            min="1"
                                            :value="getWonItemQty(item.id)"
                                            @input="(e) => setWonItemQty(item.id, e.target.value)"
                                            class="w-full sm:min-w-[5rem] sm:max-w-[6rem] px-3 py-2.5 text-base sm:text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 touch-manipulation"
                                        />
                                    </div>
                                    <div class="flex-1 min-w-0 w-full sm:w-auto">
                                        <label class="block text-xs text-slate-500 mb-0.5">Unit price (GBP)</label>
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            :value="getWonItemPrice(item.id)"
                                            @input="(e) => setWonItemPrice(item.id, e.target.value)"
                                            class="w-full sm:min-w-[6rem] sm:max-w-[7rem] px-3 py-2.5 text-base sm:text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 touch-manipulation"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div
                                v-for="item in wonItemsList"
                                :key="'won-' + item.id"
                                class="flex items-center gap-2 p-3 rounded-lg bg-green-50 border border-green-100"
                            >
                                <span class="font-medium text-green-700">Won</span>
                                <span class="font-medium text-slate-900">{{ item.product?.name || 'Product' }}</span>
                                <span class="text-sm text-slate-600">
                                    {{ item.quantity }} x GBP {{ parseFloat(item.unit_price || 0).toFixed(2) }}
                                </span>
                                <span class="text-xs text-slate-500 ml-auto">Already achieved</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="form.lead_stage === 'lost'">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Lost reason</label>
                        <input
                            v-model="form.lost_reason"
                            type="text"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Reason for loss..."
                        />
                    </div>
                </div>

                <div v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded-lg">{{ error }}</div>
                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        :disabled="saving"
                        class="w-full sm:w-auto px-5 py-3 sm:py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 font-medium text-base sm:text-sm touch-manipulation min-h-[44px] sm:min-h-0"
                    >
                        {{ saving ? 'Saving...' : 'Save changes' }}
                    </button>
                </div>
            </form>
        </template>
        <div v-else class="text-center py-12 text-slate-500">Appointment not found.</div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const toast = useToastStore();
const loading = ref(true);
const saving = ref(false);
const error = ref(null);
const appointment = ref(null);

const form = ref({
    appointment_date: '',
    appointment_time: '10:00',
    appointment_status: 'pending',
    outcome_notes: '',
    lead_stage: '',
    lost_reason: '',
});
const wonItems = ref({});

const appointmentCustomerTypeLabel = computed(() => {
    const type = appointment.value?.customer?.type;
    return type === 'customer' ? 'Customer' : 'Prospect';
});

const pendingItems = computed(() => {
    const lead = appointment.value?.lead;
    if (!lead?.items) return [];
    return lead.items.filter((i) => i.status === 'pending');
});

const wonItemsList = computed(() => {
    const lead = appointment.value?.lead;
    if (!lead?.items) return [];
    return lead.items.filter((i) => i.status === 'won');
});

const productsToSell = computed(() => {
    if (Array.isArray(appointment.value?.products_to_sell) && appointment.value.products_to_sell.length) {
        return appointment.value.products_to_sell;
    }

    const items = appointment.value?.lead?.items || [];
    return [...new Set(items.map((item) => item.product?.name).filter(Boolean))];
});

function formatStage(stage) {
    return String(stage || '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

function money(value) {
    return Number(value || 0).toLocaleString('en-GB', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function isItemSelected(id) {
    return id in wonItems.value;
}

function toggleWonItem(item) {
    const next = { ...wonItems.value };
    if (next[item.id]) {
        delete next[item.id];
    } else {
        next[item.id] = {
            quantity: item.quantity || 1,
            unit_price: item.unit_price || 0,
        };
    }
    wonItems.value = next;
}

function getWonItemQty(id) {
    return wonItems.value[id]?.quantity ?? 1;
}

function setWonItemQty(id, val) {
    const qty = Math.max(1, parseInt(val, 10) || 1);
    wonItems.value = {
        ...wonItems.value,
        [id]: { ...wonItems.value[id], quantity: qty, unit_price: wonItems.value[id]?.unit_price ?? 0 },
    };
}

function getWonItemPrice(id) {
    return wonItems.value[id]?.unit_price ?? 0;
}

function setWonItemPrice(id, val) {
    const price = Math.max(0, parseFloat(val) || 0);
    wonItems.value = {
        ...wonItems.value,
        [id]: { ...wonItems.value[id], quantity: wonItems.value[id]?.quantity ?? 1, unit_price: price },
    };
}

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await axios.get(`/api/appointments/${route.params.id}`);
        appointment.value = res.data;
        form.value.appointment_date = res.data.appointment_date || '';
        form.value.appointment_time = res.data.appointment_time || '10:00';
        form.value.appointment_status = res.data.appointment_status || 'pending';
        form.value.outcome_notes = res.data.outcome_notes || '';
        wonItems.value = {};
    } catch (e) {
        if (e.response?.status === 403) {
            error.value = 'You do not have access to this appointment.';
        } else {
            error.value = 'Failed to load appointment.';
        }
        appointment.value = null;
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    error.value = null;
    try {
        const payload = {
            appointment_date: form.value.appointment_date || undefined,
            appointment_time: form.value.appointment_time || undefined,
            appointment_status: form.value.appointment_status,
            outcome_notes: form.value.outcome_notes || undefined,
            lead_stage: form.value.lead_stage || undefined,
            lost_reason: form.value.lead_stage === 'lost' ? form.value.lost_reason : undefined,
        };
        if (form.value.lead_stage === 'won' && Object.keys(wonItems.value).length > 0) {
            payload.won_items = Object.entries(wonItems.value).map(([lead_item_id, data]) => ({
                lead_item_id: parseInt(lead_item_id, 10),
                quantity: data.quantity || 1,
                unit_price: data.unit_price ?? 0,
            }));
        }
        await axios.put(`/api/appointments/${route.params.id}`, payload);
        toast.success('Appointment updated.');
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || 'Failed to save.';
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
