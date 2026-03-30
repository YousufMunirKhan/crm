<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-2xl w-full mx-4 my-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-slate-900">
                    {{ customer ? 'Edit Customer' : 'Add New Customer' }}
                </h3>
                <button
                    @click="$emit('close')"
                    class="text-slate-400 hover:text-slate-600"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-4">
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

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Birthday</label>
                        <input
                            v-model="form.birthday"
                            type="date"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                        <input
                            v-model="form.category"
                            type="text"
                            placeholder="e.g. VIP, Retail"
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

                <div class="flex justify-end gap-3 pt-4">
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="px-4 py-2 text-sm border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50"
                        :disabled="loading"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800"
                        :disabled="loading"
                    >
                        {{ loading ? 'Saving...' : (customer ? 'Update' : 'Create') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    customer: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const form = reactive({
    name: '',
    phone: '',
    email: '',
    address: '',
    postcode: '',
    city: '',
    vat_number: '',
    birthday: '',
    category: '',
    notes: '',
    latitude: null,
    longitude: null,
});

const loading = ref(false);
const error = ref(null);

onMounted(() => {
    if (props.customer) {
        Object.assign(form, {
            name: props.customer.name || '',
            phone: props.customer.phone || '',
            email: props.customer.email || '',
            address: props.customer.address || '',
            postcode: props.customer.postcode || '',
            city: props.customer.city || '',
            vat_number: props.customer.vat_number || '',
            birthday: props.customer.birthday ? props.customer.birthday.slice(0, 10) : '',
            category: props.customer.category || '',
            notes: props.customer.notes || '',
            latitude: props.customer.latitude || null,
            longitude: props.customer.longitude || null,
        });
    }
});

const handleSubmit = async () => {
    loading.value = true;
    error.value = null;

    try {
        const data = { ...form };
        if (!data.latitude) delete data.latitude;
        if (!data.longitude) delete data.longitude;

        if (props.customer) {
            await axios.put(`/api/customers/${props.customer.id}`, data);
        } else {
            await axios.post('/api/customers', data);
        }

        emit('saved');
        emit('close');
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to save customer';
    } finally {
        loading.value = false;
    }
};
</script>

