<template>
    <div class="w-full min-w-0 max-w-4xl mx-auto p-3 sm:p-4 lg:p-6 space-y-4 lg:space-y-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
            <div>
                <h1 class="text-xl lg:text-2xl font-bold text-slate-900">{{ editingSalary ? 'Edit Salary' : 'Add Salary' }}</h1>
                <p class="text-xs lg:text-sm text-slate-600 mt-1">Generate salary slip for employees</p>
            </div>
            <router-link
                to="/salaries/list"
                class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 text-sm lg:text-base text-center touch-manipulation w-full sm:w-auto"
            >
                ← Back to Salary Slips
            </router-link>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-4 lg:p-6">
            <form @submit.prevent="saveSalary" class="space-y-4 lg:space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Employee *</label>
                        <select
                            v-model="salaryForm.user_id"
                            required
                            :disabled="editingSalary"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="">Select employee...</option>
                            <option v-for="user in employees" :key="user.id" :value="user.id">
                                {{ user.name }} ({{ user.role?.name || 'N/A' }})
                            </option>
                        </select>
                        <p v-if="!employees.length" class="text-xs text-red-600 mt-1">Loading employees...</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Month *</label>
                        <input
                            v-model="salaryForm.month"
                            type="month"
                            required
                            :disabled="editingSalary"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Currency *</label>
                    <select
                        v-model="salaryForm.currency"
                        required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    >
                        <option value="GBP">GBP (£)</option>
                        <option value="PKR">PKR (₨)</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Base Salary *</label>
                        <input
                            v-model.number="salaryForm.base_salary"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">House Allowance</label>
                        <input
                            v-model.number="salaryForm.house_allowance"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Transport Allowance</label>
                        <input
                            v-model.number="salaryForm.allowances"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="Enter amount or 0"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                        <p class="text-xs text-slate-500 mt-1">This amount will be added to the base salary</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Medical Allowance</label>
                        <input
                            v-model.number="salaryForm.medical_allowance"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Other Allowance</label>
                        <input
                            v-model.number="salaryForm.other_allowance"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-4">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">Deductions</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Tax</label>
                            <input
                                v-model.number="salaryForm.tax"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Loan Deduction</label>
                            <input
                                v-model.number="salaryForm.loan_deduction"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Other Deduction</label>
                            <input
                                v-model.number="salaryForm.other_deduction"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Attendance Days</label>
                    <input
                        v-model.number="salaryForm.attendance_days"
                        type="number"
                        min="0"
                        max="31"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />
                </div>

                <!-- Bonuses Section -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-slate-700">Bonuses</label>
                        <button
                            type="button"
                            @click="addBonus"
                            class="text-sm text-blue-600 hover:text-blue-700"
                        >
                            + Add Bonus
                        </button>
                    </div>
                    <div v-if="salaryForm.bonuses.length === 0" class="text-sm text-slate-500 italic">
                        No bonuses added
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="(bonus, index) in salaryForm.bonuses" :key="index" class="flex gap-2">
                            <input
                                v-model="bonus.name"
                                type="text"
                                placeholder="Bonus name"
                                class="flex-1 px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                            <input
                                v-model.number="bonus.amount"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="Amount"
                                class="w-32 px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                            <button
                                type="button"
                                @click="removeBonus(index)"
                                class="px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Deductions Detail Section -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-slate-700">Deductions Detail</label>
                        <button
                            type="button"
                            @click="addDeduction"
                            class="text-sm text-blue-600 hover:text-blue-700"
                        >
                            + Add Deduction
                        </button>
                    </div>
                    <div v-if="salaryForm.deductions_detail.length === 0" class="text-sm text-slate-500 italic">
                        No detailed deductions added
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="(deduction, index) in salaryForm.deductions_detail" :key="index" class="flex gap-2">
                            <input
                                v-model="deduction.name"
                                type="text"
                                placeholder="Deduction name"
                                class="flex-1 px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                            <input
                                v-model.number="deduction.amount"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="Amount"
                                class="w-32 px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                            <button
                                type="button"
                                @click="removeDeduction(index)"
                                class="px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                    <textarea
                        v-model="salaryForm.notes"
                        rows="3"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        placeholder="Additional notes or comments..."
                    ></textarea>
                </div>

                <!-- Net Salary Display -->
                <div class="bg-slate-50 rounded-lg p-4 border border-slate-200">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-slate-600">Net Salary:</div>
                        <div class="text-2xl font-bold text-slate-900">
                            {{ salaryForm.currency === 'PKR' ? '₨' : '£' }}{{ formatNumber(calculateNetSalary()) }}
                            <span class="text-sm text-slate-500 ml-2">({{ salaryForm.currency }})</span>
                        </div>
                    </div>
                </div>

                <div v-if="salaryError" class="text-sm text-red-600 bg-red-50 p-3 rounded">
                    {{ salaryError }}
                </div>

                <div class="flex justify-between items-center pt-4 border-t border-slate-200">
                    <div v-if="editingSalary" class="flex gap-2">
                        <button
                            type="button"
                            @click="downloadSalarySlip"
                            class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700"
                        >
                            📥 Download Slip
                        </button>
                        <button
                            type="button"
                            @click="sendSalarySlipEmail"
                            class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                        >
                            📧 Send Email
                        </button>
                    </div>
                    <div class="flex gap-3 ml-auto">
                        <router-link
                            to="/hr"
                            class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                        >
                            Cancel
                        </router-link>
                        <button
                            type="submit"
                            :disabled="savingSalary"
                            class="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                        >
                            {{ savingSalary ? 'Saving...' : (editingSalary ? 'Update Salary' : 'Create Salary') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();
const route = useRoute();
const router = useRouter();

const employees = ref([]);
const editingSalary = ref(null);
const savingSalary = ref(false);
const salaryError = ref(null);

const salaryForm = ref({
    user_id: '',
    month: new Date().toISOString().slice(0, 7),
    currency: 'GBP',
    base_salary: 0,
    allowances: 0,
    deductions: 0,
    bonuses: [],
    deductions_detail: [],
    attendance_days: null,
    notes: '',
});

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB').format(num || 0);
};

const loadEmployees = async () => {
    try {
        const { data } = await axios.get('/api/users');
        // Get all users (including admin) - handle both array and paginated response
        employees.value = data.data || data || [];
        console.log('Loaded employees:', employees.value.length);
    } catch (error) {
        console.error('Failed to load employees:', error);
        toast.error('Failed to load employees. Please try again.');
    }
};

const loadSalary = async (id) => {
    try {
        const { data } = await axios.get(`/api/hr/salaries/${id}`);
        editingSalary.value = data;
        salaryForm.value = {
            user_id: data.user_id,
            month: data.month,
            base_salary: parseFloat(data.base_salary),
            allowances: parseFloat(data.allowances || 0),
            house_allowance: parseFloat(data.house_allowance || 0),
            medical_allowance: parseFloat(data.medical_allowance || 0),
            other_allowance: parseFloat(data.other_allowance || 0),
            tax: parseFloat(data.tax || 0),
            loan_deduction: parseFloat(data.loan_deduction || 0),
            other_deduction: parseFloat(data.other_deduction || 0),
            deductions: parseFloat(data.deductions || 0),
            currency: data.currency || 'GBP',
            bonuses: data.bonuses && Array.isArray(data.bonuses) ? [...data.bonuses] : [],
            deductions_detail: data.deductions_detail && Array.isArray(data.deductions_detail) ? [...data.deductions_detail] : [],
            attendance_days: data.attendance_days,
            notes: data.notes || '',
        };
    } catch (error) {
        console.error('Failed to load salary:', error);
        toast.error('Failed to load salary details.');
        router.push('/salaries/list');
    }
};

const addBonus = () => {
    salaryForm.value.bonuses.push({ name: '', amount: 0 });
};

const removeBonus = (index) => {
    salaryForm.value.bonuses.splice(index, 1);
};

const addDeduction = () => {
    salaryForm.value.deductions_detail.push({ name: '', amount: 0 });
};

const removeDeduction = (index) => {
    salaryForm.value.deductions_detail.splice(index, 1);
};

const calculateNetSalary = () => {
    const base = parseFloat(salaryForm.value.base_salary) || 0;
    const transportAllowance = parseFloat(salaryForm.value.allowances) || 0;
    const houseAllowance = parseFloat(salaryForm.value.house_allowance) || 0;
    const medicalAllowance = parseFloat(salaryForm.value.medical_allowance) || 0;
    const otherAllowance = parseFloat(salaryForm.value.other_allowance) || 0;
    const bonuses = salaryForm.value.bonuses.reduce((sum, b) => sum + (parseFloat(b.amount) || 0), 0);
    const tax = parseFloat(salaryForm.value.tax) || 0;
    const loanDeduction = parseFloat(salaryForm.value.loan_deduction) || 0;
    const otherDeduction = parseFloat(salaryForm.value.other_deduction) || 0;
    const deductionsDetail = salaryForm.value.deductions_detail.reduce((sum, d) => sum + (parseFloat(d.amount) || 0), 0);
    
    // Total Earnings - Total Deductions
    const totalEarnings = base + transportAllowance + houseAllowance + medicalAllowance + otherAllowance + bonuses;
    const totalDeductions = tax + loanDeduction + otherDeduction + deductionsDetail;
    return totalEarnings - totalDeductions;
};

const saveSalary = async () => {
    savingSalary.value = true;
    salaryError.value = null;

    try {
        const payload = {
            ...salaryForm.value,
            allowances: salaryForm.value.allowances || 0, // Transport Allowance
            house_allowance: salaryForm.value.house_allowance || 0,
            medical_allowance: salaryForm.value.medical_allowance || 0,
            other_allowance: salaryForm.value.other_allowance || 0,
            tax: salaryForm.value.tax || 0,
            loan_deduction: salaryForm.value.loan_deduction || 0,
            other_deduction: salaryForm.value.other_deduction || 0,
            deductions: 0, // Not used anymore, but keep for backward compatibility
            bonuses: salaryForm.value.bonuses.filter(b => b.name && b.amount > 0),
            deductions_detail: salaryForm.value.deductions_detail.filter(d => d.name && d.amount > 0),
        };

        if (editingSalary.value) {
            await axios.put(`/api/hr/salaries/${editingSalary.value.id}`, payload);
        } else {
            await axios.post('/api/hr/salaries', payload);
        }

        toast.success('Salary saved successfully!');
        router.push('/salaries/list');
    } catch (error) {
        if (error.response?.data?.errors) {
            salaryError.value = Object.values(error.response.data.errors).flat().join(', ');
        } else if (error.response?.data?.message) {
            salaryError.value = error.response.data.message;
        } else {
            salaryError.value = 'Failed to save salary. Please try again.';
        }
        console.error('Failed to save salary:', error);
    } finally {
        savingSalary.value = false;
    }
};

const downloadSalarySlip = async () => {
    if (!editingSalary.value) return;
    
    try {
        const response = await axios.get(`/api/hr/salaries/${editingSalary.value.id}/slip`, {
            responseType: 'blob',
        });
        
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `salary_slip_${editingSalary.value.id}.pdf`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
        
        toast.success('Salary slip downloaded!');
    } catch (error) {
        console.error('Failed to download salary slip:', error);
        toast.error('Failed to download salary slip. Please try again.');
    }
};

const sendSalarySlipEmail = async () => {
    if (!editingSalary.value) return;
    
    try {
        await axios.post(`/api/hr/salaries/${editingSalary.value.id}/send-email`);
        toast.success('Salary slip sent via email!');
    } catch (error) {
        console.error('Failed to send email:', error);
        toast.error(error.response?.data?.error || 'Failed to send email. Please try again.');
    }
};

onMounted(async () => {
    await loadEmployees();
    
    // Check if editing existing salary
    if (route.params.id) {
        await loadSalary(route.params.id);
    } else if (route.query.employee_id) {
        // Pre-select employee if coming from employee detail page
        salaryForm.value.user_id = parseInt(route.query.employee_id);
    }
});
</script>

