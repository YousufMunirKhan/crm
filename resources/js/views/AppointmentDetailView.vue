<template>
    <div class="max-w-3xl mx-auto p-3 sm:p-6 space-y-4 sm:space-y-6">
        <div class="flex items-center gap-2 sm:gap-3 min-w-0">
            <BaseButton
                size="icon"
                variant="ghost"
                to="/appointments"
                label="Back to appointments"
                class="shrink-0"
            >
                <ArrowLeftIcon class="icon" aria-hidden="true" />
            </BaseButton>
            <h1 class="text-lg sm:text-xl font-bold text-slate-900 truncate">Appointment Details</h1>
        </div>

        <div
            v-if="loading"
            class="space-y-3 py-4"
            role="status"
            aria-live="polite"
            aria-busy="true"
        >
            <span class="sr-only">Loading appointment…</span>
            <div v-for="n in 5" :key="`appointment-skeleton-${n}`" class="skeleton-text" :class="n % 3 === 0 ? 'w-1/2' : 'w-full'" />
        </div>
        <template v-else-if="appointment">
            <BaseCard>
                <div class="space-y-4">
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
                        <router-link :to="`/leads/${appointment.lead_id}`" class="link">
                            {{ appointment.lead_id }}
                        </router-link>
                    </div>

                    <div class="rounded-card border border-primary-100 bg-primary-50/70 p-4 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 class="text-sm font-semibold text-primary-900">Sales brief</h2>
                                <div class="text-xs text-primary-700">Who this is for, what to sell, and where the lead stands.</div>
                            </div>
                            <BaseBadge tone="primary">
                                {{ formatStage(appointment.lead?.stage) || 'No stage' }}
                            </BaseBadge>
                        </div>

                        <div>
                            <div class="text-xs font-medium text-primary-800 mb-1">What to sell</div>
                            <div v-if="productsToSell.length" class="flex flex-wrap gap-1.5">
                                <BaseBadge
                                    v-for="product in productsToSell"
                                    :key="product"
                                    tone="primary"
                                >
                                    {{ product }}
                                </BaseBadge>
                            </div>
                            <p v-else class="callout callout-warning">
                                No product is attached to this lead yet. Open the lead and add the product before attending.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                            <div>
                                <div class="text-xs text-primary-800">Source</div>
                                <div class="font-medium text-slate-900">{{ appointment.lead?.source || 'Not set' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-primary-800">Lead owner</div>
                                <div class="font-medium text-slate-900">{{ appointment.lead?.assignee?.name || 'Unassigned' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-primary-800">Pipeline value</div>
                                <div class="font-medium text-slate-900">GBP {{ money(appointment.lead?.pipeline_value || 0) }}</div>
                            </div>
                        </div>
                    </div>

                    <div v-if="appointment.description">
                        <div class="text-sm text-slate-500">Notes</div>
                        <p class="text-slate-700 whitespace-pre-line">{{ appointment.description }}</p>
                    </div>
                </div>
            </BaseCard>

            <BaseCard title="Update appointment">
                <form id="appointment-update-form" class="space-y-4 sm:space-y-5" @submit.prevent="save">
                    <div class="form-grid-2">
                        <div>
                            <label class="form-label" for="appointmentdetailview-appointment-date">Appointment date</label>
                            <input id="appointmentdetailview-appointment-date"
                                v-model="form.appointment_date"
                                type="date"
                                class="form-input"
                            />
                        </div>
                        <div>
                            <label class="form-label" for="appointmentdetailview-appointment-time">Appointment time</label>
                            <input id="appointmentdetailview-appointment-time"
                                v-model="form.appointment_time"
                                type="time"
                                class="form-input"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="form-label" for="appointmentdetailview-status">Status</label>
                        <select id="appointmentdetailview-status"
                            v-model="form.appointment_status"
                            class="form-select"
                        >
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="no_show">No show</option>
                            <option value="rescheduled">Rescheduled</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="appointmentdetailview-what-happened-outcome-notes">What happened (outcome notes)</label>
                        <textarea id="appointmentdetailview-what-happened-outcome-notes"
                            v-model="form.outcome_notes"
                            rows="3"
                            class="form-textarea resize-none"
                            placeholder="Summarise what happened at the appointment..."
                        />
                    </div>

                    <div class="border-t border-slate-200 pt-4 space-y-4">
                        <fieldset class="form-fieldset">
                            <legend class="form-legend">Lead outcome (optional)</legend>
                            <div class="flex flex-wrap gap-4">
                                <label class="form-choice">
                                    <input type="radio" v-model="form.lead_stage" value="won" class="form-radio" />
                                    <span>Won</span>
                                </label>
                                <label class="form-choice">
                                    <input type="radio" v-model="form.lead_stage" value="lost" class="form-radio" />
                                    <span>Lost</span>
                                </label>
                                <label class="form-choice">
                                    <input type="radio" v-model="form.lead_stage" value="" class="form-radio" />
                                    <span>No change</span>
                                </label>
                            </div>
                        </fieldset>

                        <div v-if="form.lead_stage === 'won'" class="mt-4 space-y-4">
                            <div class="text-sm font-medium text-slate-700">Products achieved</div>
                            <p class="text-xs text-slate-500">Select products that were sold during this appointment.</p>
                            <p v-if="!pendingItems.length && !wonItemsList.length" class="callout callout-info">
                                No products on this lead. Add products on the <router-link :to="`/leads/${appointment.lead_id}`" class="link">Lead page</router-link> first.
                            </p>
                            <div v-else class="space-y-3">
                                <div
                                    v-for="item in pendingItems"
                                    :key="item.id"
                                    class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 sm:p-4 rounded-card border border-slate-200 bg-white"
                                >
                                    <label class="form-choice shrink-0">
                                        <input
                                            type="checkbox"
                                            :checked="isItemSelected(item.id)"
                                            @change="toggleWonItem(item)"
                                            class="form-checkbox"
                                        />
                                        <span class="font-medium text-slate-900">{{ item.product?.name || 'Product' }}</span>
                                    </label>
                                    <div v-if="isItemSelected(item.id)" class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 flex-1">
                                        <div class="flex-1 min-w-0 w-full sm:w-auto">
                                            <label class="form-label" :for="`appointmentdetailview-quantity-${item.id}`">Quantity</label>
                                            <input :id="`appointmentdetailview-quantity-${item.id}`"
                                                type="number"
                                                min="1"
                                                :value="getWonItemQty(item.id)"
                                                @input="(e) => setWonItemQty(item.id, e.target.value)"
                                                class="form-input sm:min-w-[5rem] sm:max-w-[6rem] text-base sm:text-sm touch-manipulation"
                                            />
                                        </div>
                                        <div class="flex-1 min-w-0 w-full sm:w-auto">
                                            <label class="form-label" :for="`appointmentdetailview-unit-price-gbp-${item.id}`">Unit price (GBP)</label>
                                            <input :id="`appointmentdetailview-unit-price-gbp-${item.id}`"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                :value="getWonItemPrice(item.id)"
                                                @input="(e) => setWonItemPrice(item.id, e.target.value)"
                                                class="form-input sm:min-w-[6rem] sm:max-w-[7rem] text-base sm:text-sm touch-manipulation"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-for="item in wonItemsList"
                                    :key="'won-' + item.id"
                                    class="flex flex-wrap items-center gap-2 p-3 rounded-card bg-success-50 border border-success-200"
                                >
                                    <BaseBadge tone="success">Won</BaseBadge>
                                    <span class="font-medium text-slate-900">{{ item.product?.name || 'Product' }}</span>
                                    <span class="text-sm text-slate-600">
                                        {{ item.quantity }} x GBP {{ parseFloat(item.unit_price || 0).toFixed(2) }}
                                    </span>
                                    <span class="text-xs text-slate-500 ml-auto">Already achieved</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="form.lead_stage === 'lost'">
                            <label class="form-label" for="appointmentdetailview-lost-reason">Lost reason</label>
                            <input id="appointmentdetailview-lost-reason"
                                v-model="form.lost_reason"
                                type="text"
                                class="form-input"
                                placeholder="Reason for loss..."
                            />
                        </div>
                    </div>

                    <p v-if="error" class="callout callout-danger" role="alert">{{ error }}</p>
                </form>

                <template #footer>
                    <BaseButton
                        variant="primary"
                        size="lg"
                        type="submit"
                        form="appointment-update-form"
                        block-mobile
                        :loading="saving"
                    >
                        {{ saving ? 'Saving...' : 'Save changes' }}
                    </BaseButton>
                </template>
            </BaseCard>
        </template>
        <EmptyState
            v-else
            heading="Appointment not found."
            :description="error || ''"
        >
            <template #icon>
                <CalendarDaysIcon class="icon" aria-hidden="true" />
            </template>
        </EmptyState>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { ArrowLeftIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline';
import { BaseBadge, BaseButton, BaseCard, EmptyState } from '@/components/base';
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
