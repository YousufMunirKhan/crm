<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-slate-200">
                <h2 class="text-xl font-semibold text-slate-900">
                    {{ customer ? 'Edit Customer' : 'Create New Customer' }}
                </h2>
            </div>

            <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Name *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Phone *</label>
                        <input
                            v-model="form.phone"
                            type="text"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">City</label>
                        <input
                            v-model="form.city"
                            type="text"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Postcode</label>
                        <input
                            v-model="form.postcode"
                            type="text"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">VAT Number</label>
                        <input
                            v-model="form.vat_number"
                            type="text"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                    <textarea
                        v-model="form.address"
                        rows="2"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Latitude</label>
                        <input
                            v-model="form.latitude"
                            type="number"
                            step="any"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Longitude</label>
                        <input
                            v-model="form.longitude"
                            type="number"
                            step="any"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                </div>

                <div v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded">
                    {{ error }}
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                    >
                        {{ loading ? 'Saving...' : (customer ? 'Update' : 'Create') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

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
