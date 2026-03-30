<template>
    <div class="w-full min-w-0 max-w-6xl mx-auto p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Today's Report</h1>
                <p class="text-sm text-slate-600 mt-1">View all team activities, follow-ups, leads & attendance for any day</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <label class="text-sm font-medium text-slate-700">Date:</label>
                <input
                    v-model="selectedDate"
                    type="date"
                    @change="loadReport"
                    class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <button
                    v-if="selectedDate !== todayStr"
                    @click="goToToday"
                    class="px-3 py-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg"
                >
                    Today
                </button>
                <button
                    v-if="canGenerateReport"
                    type="button"
                    @click="generateReport"
                    :disabled="generatingReport"
                    class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50 text-sm font-medium"
                >
                    {{ generatingReport ? 'Generating...' : 'Generate report' }}
                </button>
            </div>
        </div>

        <!-- GPT-generated report -->
        <div v-if="generatedReport" class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-2">{{ generatedReport.date_label }} – Summary</h2>
                <div class="prose prose-slate max-w-none text-sm whitespace-pre-wrap text-slate-700">{{ generatedReport.generated_report }}</div>
            </div>
            <details class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <summary class="px-4 py-3 cursor-pointer text-sm font-medium text-slate-700 hover:bg-slate-50">Show what was sent to GPT (prompt + data)</summary>
                <div class="px-4 py-3 border-t border-slate-200 space-y-3">
                    <div>
                        <div class="text-xs font-semibold text-slate-500 uppercase mb-1">System prompt</div>
                        <pre class="text-xs bg-slate-50 p-3 rounded-lg whitespace-pre-wrap text-slate-700">{{ generatedReport.system_prompt }}</pre>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-slate-500 uppercase mb-1">User message (data sent to GPT)</div>
                        <pre class="text-xs bg-slate-50 p-3 rounded-lg whitespace-pre-wrap text-slate-700 max-h-96 overflow-y-auto">{{ generatedReport.raw_data_sent_to_gpt }}</pre>
                    </div>
                </div>
            </details>
        </div>

        <div v-if="loading" class="text-center py-16 text-slate-500">Loading report...</div>
        <div v-else-if="report" class="space-y-6">
            <div class="text-sm text-slate-600 font-medium">{{ report.date_label }}</div>

            <div v-if="report.report.length === 0" class="bg-white rounded-xl shadow-sm p-12 text-center text-slate-500">
                No team members found for this report.
            </div>

            <div v-else class="space-y-4">
                <div
                    v-for="user in report.report"
                    :key="user.user_id"
                    class="bg-white rounded-xl shadow-sm overflow-hidden border border-slate-200"
                >
                    <!-- User header -->
                    <div class="px-4 sm:px-6 py-4 bg-slate-50 border-b border-slate-200">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-slate-900">{{ user.user_name }}</h3>
                                <span class="text-xs text-slate-500">{{ user.role }}</span>
                            </div>
                            <div class="flex flex-wrap gap-3 sm:gap-6">
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="text-slate-500">Follow-ups:</span>
                                    <span class="font-semibold text-slate-900">{{ user.follow_ups_count }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="text-slate-500">Leads:</span>
                                    <span class="font-semibold text-slate-900">{{ user.leads_created_count }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="text-slate-500">Won:</span>
                                    <span class="font-semibold text-green-600">{{ user.leads_won_count }}</span>
                                </div>
                                <div v-if="user.attendance" class="flex items-center gap-2 text-sm">
                                    <span class="text-slate-500">Attendance:</span>
                                    <span class="font-medium text-slate-700">
                                        {{ user.attendance.check_in || '–' }} – {{ user.attendance.check_out || '–' }}
                                        <span v-if="user.attendance.work_hours" class="text-slate-500">({{ user.attendance.work_hours }}h)</span>
                                    </span>
                                </div>
                                <div v-else class="text-sm text-slate-400">No attendance</div>
                            </div>
                        </div>
                    </div>

                    <!-- Activities -->
                    <div class="px-4 sm:px-6 py-4">
                        <h4 class="text-sm font-medium text-slate-700 mb-3">
                            Activities ({{ user.activities_count }})
                        </h4>
                        <div v-if="user.activities.length === 0" class="text-sm text-slate-400 italic">
                            No activities logged for this day.
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="(act, idx) in user.activities"
                                :key="act.id"
                                class="flex gap-3 p-3 bg-slate-50 rounded-lg"
                            >
                                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-medium">
                                    {{ idx + 1 }}
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-slate-900 whitespace-pre-wrap text-sm">{{ act.description }}</p>
                                    <p class="text-xs text-slate-500 mt-1">
                                        {{ formatTime(act.created_at) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();
const auth = useAuthStore();
const report = ref(null);
const loading = ref(false);
const selectedDate = ref('');
const generatingReport = ref(false);
const generatedReport = ref(null);

const allowedReportEmails = ['yousufmunir59@gmail.com', 'owaishameed301@gmail.com'];
const canGenerateReport = computed(() => {
    const email = auth.user?.email;
    return email && allowedReportEmails.includes(email);
});

const todayStr = computed(() => new Date().toISOString().slice(0, 10));

const formatTime = (d) => {
    if (!d) return '';
    return new Date(d).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
};

const loadReport = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/daily-activities/todays-report', {
            params: { date: selectedDate.value },
        });
        report.value = data;
    } catch (e) {
        toast.error('Failed to load report');
        report.value = null;
    } finally {
        loading.value = false;
    }
};

const goToToday = () => {
    selectedDate.value = todayStr.value;
    loadReport();
};

const generateReport = async () => {
    generatingReport.value = true;
    generatedReport.value = null;
    try {
        const { data } = await axios.post('/api/daily-activities/generate-report', null, {
            params: { date: selectedDate.value },
        });
        generatedReport.value = data;
    } catch (e) {
        const msg = e.response?.data?.message || e.message || 'Failed to generate report';
        toast.error(msg);
    } finally {
        generatingReport.value = false;
    }
};

onMounted(() => {
    selectedDate.value = todayStr.value;
    loadReport();
});
</script>
