<template>
    <div class="max-w-5xl mx-auto p-4 md:p-6 space-y-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Access Manager</h1>
            <p class="text-sm text-slate-600 mt-1 max-w-3xl">
                Choose which sidebar sections each <strong>role</strong> can see. Leave “Limit menu” off to use the normal menu for that role.
                If a user has <strong>custom menu</strong> set on their employee profile, that overrides the role here.
                <span class="block mt-2 text-slate-700"><strong>Admin</strong> and <strong>System Admin</strong> roles always have full access; they cannot be limited here.</span>
            </p>
        </div>

        <div v-if="loading" class="text-center py-16 text-slate-500">Loading roles…</div>
        <div v-else class="space-y-4">
            <div
                v-for="role in managedRoles"
                :key="role.id"
                class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden"
            >
                <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2 bg-slate-50">
                    <div>
                        <h2 class="font-semibold text-slate-900">{{ role.name }}</h2>
                        <p v-if="role.description" class="text-xs text-slate-500 mt-0.5">{{ role.description }}</p>
                    </div>
                    <button
                        v-if="!isFullAccessRole(role)"
                        type="button"
                        :disabled="savingId === role.id"
                        class="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                        @click="saveRole(role)"
                    >
                        {{ savingId === role.id ? 'Saving…' : 'Save' }}
                    </button>
                </div>
                <div v-if="uiState[role.id] && isFullAccessRole(role)" class="p-4">
                    <p class="text-sm text-slate-700 bg-emerald-50 border border-emerald-100 rounded-lg px-3 py-2">
                        This role always has <strong>full menu access</strong>. Restrictions do not apply.
                    </p>
                </div>
                <div v-else-if="uiState[role.id]" class="p-4 space-y-4">
                    <label class="flex items-center gap-2 text-sm text-slate-800 cursor-pointer">
                        <input
                            v-model="uiState[role.id].restrict"
                            type="checkbox"
                            class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        />
                        Limit sidebar for this role (whitelist checked items only)
                    </label>
                    <div
                        v-if="uiState[role.id].restrict"
                        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-64 overflow-y-auto border border-slate-200 rounded-lg p-3 bg-slate-50"
                    >
                        <label
                            v-for="opt in sectionOptions"
                            v-show="opt.key !== 'dashboard'"
                            :key="`${role.id}-${opt.key}`"
                            class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer"
                        >
                            <input
                                v-model="uiState[role.id].checks[opt.key]"
                                type="checkbox"
                                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            {{ opt.label }}
                        </label>
                    </div>
                    <p v-else class="text-xs text-slate-500">No role-level limit — menu follows the app’s normal rules for this role.</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import { NAV_SECTION_OPTIONS } from '@/constants/navSections';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();
const loading = ref(true);
const roles = ref([]);
const savingId = ref(null);
/** @type {import('vue').Ref<Record<number, { restrict: boolean, checks: Record<string, boolean> }>>} */
const uiState = ref({});

const sectionOptions = NAV_SECTION_OPTIONS;

const managedRoles = computed(() =>
    roles.value.filter((r) => r.name !== 'Customer').sort((a, b) => a.name.localeCompare(b.name)),
);

function defaultChecks() {
    const c = {};
    for (const { key } of NAV_SECTION_OPTIONS) {
        c[key] = true;
    }
    return c;
}

function isFullAccessRole(role) {
    return role.name === 'Admin' || role.name === 'System Admin';
}

function buildUiFromRole(role) {
    if (isFullAccessRole(role)) {
        return { restrict: false, checks: defaultChecks() };
    }
    const np = role.nav_permissions;
    const restrict = np && typeof np === 'object' && Object.keys(np).length > 0;
    const checks = defaultChecks();
    if (restrict) {
        for (const key of Object.keys(checks)) {
            checks[key] = !!np[key];
        }
    }
    return { restrict, checks };
}

function syncUiState() {
    const next = { ...uiState.value };
    for (const role of roles.value) {
        if (role.name === 'Customer') continue;
        next[role.id] = buildUiFromRole(role);
    }
    uiState.value = next;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/roles');
        roles.value = Array.isArray(data) ? data : [];
        syncUiState();
    } catch (e) {
        console.error(e);
        toast.error('Failed to load roles.');
        roles.value = [];
    } finally {
        loading.value = false;
    }
}

async function saveRole(role) {
    if (isFullAccessRole(role)) return;

    const state = uiState.value[role.id];
    if (!state) return;

    savingId.value = role.id;
    try {
        const payload =
            state.restrict
                ? {
                      nav_permissions: Object.fromEntries(
                          NAV_SECTION_OPTIONS.map(({ key }) => [key, !!state.checks[key]]),
                      ),
                  }
                : { nav_permissions: null };

        const { data } = await axios.patch(`/api/roles/${role.id}`, payload);
        const idx = roles.value.findIndex((r) => r.id === role.id);
        if (idx !== -1) {
            roles.value[idx] = data;
        }
        uiState.value[role.id] = buildUiFromRole(data);
        toast.success(`Saved menu access for ${role.name}.`);
    } catch (e) {
        console.error(e);
        const msg = e.response?.data?.message || 'Failed to save.';
        toast.error(msg);
    } finally {
        savingId.value = null;
    }
}

load();
</script>
