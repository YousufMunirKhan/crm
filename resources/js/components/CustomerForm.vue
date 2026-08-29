<template>
    <BaseModal
        :model-value="true"
        :title="customer ? 'Edit Customer' : 'Create New Customer'"
        size="md"
        :close-on-backdrop="false"
        @close="$emit('close')"
    >
        <form id="customer-form" class="space-y-4" @submit.prevent="handleSubmit">
            <div class="form-grid-2">
                <div>
                    <label class="form-label" for="customerform-name">Name <span class="form-required" aria-hidden="true">*</span></label>
                    <input id="customerform-name"
                        v-model="form.name"
                        type="text"
                        required
                        class="form-input"
                    />
                </div>

                <div>
                    <label class="form-label" for="customerform-phone">Phone <span class="form-required" aria-hidden="true">*</span></label>
                    <input id="customerform-phone"
                        v-model="form.phone"
                        type="text"
                        required
                        class="form-input"
                    />
                </div>

                <div>
                    <label class="form-label" for="customerform-email">Email</label>
                    <input id="customerform-email"
                        v-model="form.email"
                        type="email"
                        class="form-input"
                    />
                </div>

                <div>
                    <label class="form-label" for="customerform-city">City</label>
                    <input id="customerform-city"
                        v-model="form.city"
                        type="text"
                        class="form-input"
                    />
                </div>

                <div>
                    <label class="form-label" for="customerform-postcode">Postcode</label>
                    <input id="customerform-postcode"
                        v-model="form.postcode"
                        type="text"
                        class="form-input"
                    />
                </div>

                <div>
                    <label class="form-label" for="customerform-vat-number">VAT Number</label>
                    <input id="customerform-vat-number"
                        v-model="form.vat_number"
                        type="text"
                        class="form-input"
                    />
                </div>
            </div>

            <div>
                <label class="form-label" for="customerform-address">Address</label>
                <textarea id="customerform-address"
                    v-model="form.address"
                    rows="2"
                    class="form-textarea"
                />
            </div>

            <div>
                <label class="form-label" for="customerform-notes">Notes</label>
                <textarea id="customerform-notes"
                    v-model="form.notes"
                    rows="3"
                    class="form-textarea"
                />
            </div>

            <div class="form-grid-2">
                <div>
                    <label class="form-label" for="customerform-latitude">Latitude</label>
                    <input id="customerform-latitude"
                        v-model="form.latitude"
                        type="number"
                        step="any"
                        class="form-input"
                    />
                </div>

                <div>
                    <label class="form-label" for="customerform-longitude">Longitude</label>
                    <input id="customerform-longitude"
                        v-model="form.longitude"
                        type="number"
                        step="any"
                        class="form-input"
                    />
                </div>
            </div>

            <p v-if="error" class="callout callout-danger" role="alert">
                {{ error }}
            </p>
        </form>

        <template #actions>
            <BaseButton variant="outline" block-mobile @click="$emit('close')">Cancel</BaseButton>
            <BaseButton
                variant="primary"
                type="submit"
                form="customer-form"
                block-mobile
                :loading="loading"
            >
                {{ loading ? 'Saving...' : (customer ? 'Update' : 'Create') }}
            </BaseButton>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { BaseButton, BaseModal } from '@/components/base';

const props = defineProps({
    customer: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const form = ref({
    name: '',
    phone: '',
    email: '',
    address: '',
    postcode: '',
    city: '',
    vat_number: '',
    notes: '',
    latitude: null,
    longitude: null,
});

const loading = ref(false);
const error = ref(null);

onMounted(() => {
    if (props.customer) {
        form.value = { ...props.customer };
    }
});

const handleSubmit = async () => {
    loading.value = true;
    error.value = null;

    try {
        // Clean up form data - remove null/empty values for optional fields
        const payload = { ...form.value };
        if (!payload.email) delete payload.email;
        if (!payload.address) delete payload.address;
        if (!payload.postcode) delete payload.postcode;
        if (!payload.city) delete payload.city;
        if (!payload.vat_number) delete payload.vat_number;
        if (!payload.notes) delete payload.notes;
        if (!payload.latitude) delete payload.latitude;
        if (!payload.longitude) delete payload.longitude;

        if (props.customer) {
            await axios.put(`/api/customers/${props.customer.id}`, payload);
        } else {
            await axios.post('/api/customers', payload);
        }

        // Reset form
        form.value = {
            name: '',
            phone: '',
            email: '',
            address: '',
            postcode: '',
            city: '',
            vat_number: '',
            notes: '',
            latitude: null,
            longitude: null,
        };

        emit('saved');
        emit('close');
    } catch (err) {
        if (err.response?.data?.errors) {
            // Laravel validation errors
            const errors = err.response.data.errors;
            error.value = Object.values(errors).flat().join(', ');
        } else if (err.response?.data?.message) {
            error.value = err.response.data.message;
        } else {
            error.value = 'Failed to save customer. Please check your connection and try again.';
        }
        console.error('Customer save error:', err);
    } finally {
        loading.value = false;
    }
};
</script>
