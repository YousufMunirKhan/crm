<template>
    <ListingPageShell
        :title="editingSalary ? 'Edit Salary' : 'Add Salary'"
        subtitle="Build a monthly salary slip for one employee — earnings first, then deductions, with the net total recalculated as you type."
        :badge="editingSalary ? 'Editing' : 'New'"
    >
        <template #actions>
            <BaseButton variant="outline" to="/salaries/list" block-mobile>
                <template #icon><ArrowLeftIcon class="icon-sm" aria-hidden="true" /></template>
                Back to Salary Slips
            </BaseButton>
            <BaseButton v-if="editingSalary" variant="outline" block-mobile @click="downloadSalarySlip">
                <template #icon><ArrowDownTrayIcon class="icon-sm" aria-hidden="true" /></template>
                Download Slip
            </BaseButton>
            <BaseButton v-if="editingSalary" variant="outline" block-mobile @click="sendSalarySlipEmail">
                <template #icon><EnvelopeIcon class="icon-sm" aria-hidden="true" /></template>
                Send Email
            </BaseButton>
        </template>

        <div class="px-4 sm:px-6 py-5 sm:py-6">
            <form id="salary-form" class="max-w-3xl space-y-5 lg:space-y-6" @submit.prevent="saveSalary">
                <div class="form-grid-2">
                    <div>
                        <label class="form-label" for="salaryview-employee">Employee <span class="form-required">*</span></label>
                        <select id="salaryview-employee"
                            v-model="salaryForm.user_id"
                            required
                            :disabled="editingSalary"
                            class="form-select w-full"
                        >
                            <option value="">Select employee...</option>
                            <option v-for="user in employees" :key="user.id" :value="user.id">
                                {{ user.name }} ({{ user.role?.name || 'N/A' }})
                            </option>
                        </select>
                        <div v-if="!employees.length" class="mt-2" aria-busy="true">
                            <span class="sr-only">Loading employees…</span>
                            <div class="skeleton-text w-40" aria-hidden="true"></div>
                        </div>
                    </div>
                    <div>
                        <label class="form-label" for="salaryview-month">Month <span class="form-required">*</span></label>
                        <input id="salaryview-month"
                            v-model="salaryForm.month"
                            type="month"
                            required
                            :disabled="editingSalary"
                            class="form-input w-full"
                        />
                    </div>
                </div>

                <div>
                    <label class="form-label" for="salaryview-currency">Currency <span class="form-required">*</span></label>
                    <select id="salaryview-currency"
                        v-model="salaryForm.currency"
                        required
                        class="form-select w-full"
                    >
                        <option value="GBP">GBP (£)</option>
                        <option value="PKR">PKR (₨)</option>
                    </select>
                </div>

                <div class="form-grid-2">
                    <div>
                        <label class="form-label" for="salaryview-base-salary">Base Salary <span class="form-required">*</span></label>
                        <input id="salaryview-base-salary"
                            v-model.number="salaryForm.base_salary"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            class="form-input w-full"
                        />
                    </div>
                    <div>
                        <label class="form-label" for="salaryview-house-allowance">House Allowance</label>
                        <input id="salaryview-house-allowance"
                            v-model.number="salaryForm.house_allowance"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="form-input w-full"
                        />
                    </div>
                    <div>
                        <label class="form-label" for="salaryview-transport-allowance">Transport Allowance</label>
                        <input id="salaryview-transport-allowance"
                            v-model.number="salaryForm.allowances"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="Enter amount or 0"
                            class="form-input w-full"
                        />
                        <p class="form-hint">This amount will be added to the base salary</p>
                    </div>
                    <div>
                        <label class="form-label" for="salaryview-medical-allowance">Medical Allowance</label>
                        <input id="salaryview-medical-allowance"
                            v-model.number="salaryForm.medical_allowance"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="form-input w-full"
                        />
                    </div>
                    <div>
                        <label class="form-label" for="salaryview-other-allowance">Other Allowance</label>
                        <input id="salaryview-other-allowance"
                            v-model.number="salaryForm.other_allowance"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="form-input w-full"
                        />
                    </div>
                </div>

                <fieldset class="form-fieldset border-t border-slate-200 pt-4">
                    <legend class="form-legend">Deductions</legend>
                    <div class="form-grid-2">
                        <div>
                            <label class="form-label" for="salaryview-tax">Tax</label>
                            <input id="salaryview-tax"
                                v-model.number="salaryForm.tax"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                class="form-input w-full"
                            />
                        </div>
                        <div>
                            <label class="form-label" for="salaryview-loan-deduction">Loan Deduction</label>
                            <input id="salaryview-loan-deduction"
                                v-model.number="salaryForm.loan_deduction"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                class="form-input w-full"
                            />
                        </div>
                        <div>
                            <label class="form-label" for="salaryview-other-deduction">Other Deduction</label>
                            <input id="salaryview-other-deduction"
                                v-model.number="salaryForm.other_deduction"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                class="form-input w-full"
                            />
                        </div>
                    </div>
                </fieldset>

                <div>
                    <label class="form-label" for="salaryview-attendance-days">Attendance Days</label>
                    <input id="salaryview-attendance-days"
                        v-model.number="salaryForm.attendance_days"
                        type="number"
                        min="0"
                        max="31"
                        class="form-input w-full"
                    />
                </div>

                <!-- Bonuses Section -->
                <div>
                    <div class="flex justify-between items-center gap-3 mb-2">
                        <span class="form-label mb-0">Bonuses</span>
                        <BaseButton variant="ghost" size="sm" @click="addBonus">
                            <template #icon><PlusIcon class="icon-sm" aria-hidden="true" /></template>
                            Add Bonus
                        </BaseButton>
                    </div>
                    <p v-if="salaryForm.bonuses.length === 0" class="form-hint">
                        No bonuses added
                    </p>
                    <div v-else class="space-y-2">
                        <div v-for="(bonus, index) in salaryForm.bonuses" :key="index" class="flex gap-2">
                            <div class="flex-1 min-w-0">
                                <label class="sr-only" :for="`salaryview-bonus-name-${index}`">Bonus {{ index + 1 }} name</label>
                                <input
                                    :id="`salaryview-bonus-name-${index}`"
                                    v-model="bonus.name"
                                    type="text"
                                    placeholder="Bonus name"
                                    class="form-input w-full"
                                />
                            </div>
                            <div class="w-32 shrink-0">
                                <label class="sr-only" :for="`salaryview-bonus-amount-${index}`">Bonus {{ index + 1 }} amount</label>
                                <input
                                    :id="`salaryview-bonus-amount-${index}`"
                                    v-model.number="bonus.amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Amount"
                                    class="form-input w-full"
                                />
                            </div>
                            <BaseButton
                                variant="ghost"
                                size="icon"
                                :label="`Remove bonus ${index + 1}`"
                                class="text-danger-700 shrink-0"
                                @click="removeBonus(index)"
                            >
                                <TrashIcon class="icon" aria-hidden="true" />
                            </BaseButton>
                        </div>
                    </div>
                </div>

                <!-- Deductions Detail Section -->
                <div>
                    <div class="flex justify-between items-center gap-3 mb-2">
                        <span class="form-label mb-0">Deductions Detail</span>
                        <BaseButton variant="ghost" size="sm" @click="addDeduction">
                            <template #icon><PlusIcon class="icon-sm" aria-hidden="true" /></template>
                            Add Deduction
                        </BaseButton>
                    </div>
                    <p v-if="salaryForm.deductions_detail.length === 0" class="form-hint">
                        No detailed deductions added
                    </p>
                    <div v-else class="space-y-2">
                        <div v-for="(deduction, index) in salaryForm.deductions_detail" :key="index" class="flex gap-2">
                            <div class="flex-1 min-w-0">
                                <label class="sr-only" :for="`salaryview-deduction-name-${index}`">Deduction {{ index + 1 }} name</label>
                                <input
                                    :id="`salaryview-deduction-name-${index}`"
                                    v-model="deduction.name"
                                    type="text"
                                    placeholder="Deduction name"
                                    class="form-input w-full"
                                />
                            </div>
                            <div class="w-32 shrink-0">
                                <label class="sr-only" :for="`salaryview-deduction-amount-${index}`">Deduction {{ index + 1 }} amount</label>
                                <input
                                    :id="`salaryview-deduction-amount-${index}`"
                                    v-model.number="deduction.amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Amount"
                                    class="form-input w-full"
                                />
                            </div>
                            <BaseButton
                                variant="ghost"
                                size="icon"
                                :label="`Remove deduction ${index + 1}`"
                                class="text-danger-700 shrink-0"
                                @click="removeDeduction(index)"
                            >
                                <TrashIcon class="icon" aria-hidden="true" />
                            </BaseButton>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="form-label" for="salaryview-notes">Notes</label>
                    <textarea id="salaryview-notes"
                        v-model="salaryForm.notes"
                        rows="3"
                        class="form-textarea w-full"
                        placeholder="Additional notes or comments..."
                    ></textarea>
                </div>

                <!-- Net Salary Display -->
                <div class="rounded-card bg-slate-50 p-4 border border-slate-200">
                    <div class="flex flex-wrap justify-between items-center gap-2">
                        <div class="text-sm text-slate-600">Net Salary:</div>
                        <div class="text-metric text-slate-900">
                            {{ salaryForm.currency === 'PKR' ? '₨' : '£' }}{{ formatNumber(calculateNetSalary()) }}
                            <span class="text-sm text-slate-500 ml-2">({{ salaryForm.currency }})</span>
                        </div>
                    </div>
                </div>

                <div v-if="salaryError" class="callout callout-danger" role="alert">
                    {{ salaryError }}
                </div>

                <div class="form-actions pt-4 border-t border-slate-200">
                    <BaseButton variant="outline" to="/hr" block-mobile>Cancel</BaseButton>
                    <BaseButton
                        variant="primary"
                        type="submit"
                        block-mobile
                        :loading="savingSalary"
                    >
                        {{ savingSalary ? 'Saving...' : (editingSalary ? 'Update Salary' : 'Create Salary') }}
                    </BaseButton>
                </div>
            </form>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseButton } from '@/components/base';
import {
    ArrowLeftIcon,
    ArrowDownTrayIcon,
    EnvelopeIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

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
