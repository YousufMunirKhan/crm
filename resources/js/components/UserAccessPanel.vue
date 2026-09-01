<template>
    <div class="space-y-4 border border-slate-200 rounded-card p-4 bg-slate-50/80">
        <div>
            <h2 class="form-section-title text-base">Access</h2>
            <p class="text-xs text-slate-600 mt-1">
                Their role decides most of this. Use the exceptions below to hand one person
                one section — usually with an end date, so it undoes itself.
            </p>
        </div>

        <p v-if="loading" class="text-xs text-slate-500">Loading…</p>

        <p v-else-if="roleHasFullAccess" class="callout callout-info">
            <strong>{{ roleName }}</strong> has every section and cannot be limited here.
            To restrict this person, change their role first.
        </p>

        <template v-else-if="sections.length">
            <!-- Live exceptions, and how to add one -->
            <div class="rounded-card border border-slate-200 bg-white p-3 space-y-3">
                <h3 class="text-sm font-semibold text-slate-800">Exceptions to their role</h3>

                <ul v-if="liveGrants.length" class="space-y-2">
                    <li
                        v-for="grant in liveGrants"
                        :key="grant.id"
                        class="flex flex-wrap items-start justify-between gap-2 rounded-control border p-2.5"
                        :class="grant.effect === 'revoke'
                            ? 'border-danger-200 bg-danger-50/60'
                            : 'border-success-200 bg-success-50/60'"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900">
                                {{ grant.effect === 'revoke' ? 'Blocked from' : 'Extra access to' }}
                                {{ grant.section_label }}
                            </p>
                            <p class="text-xs text-slate-600 mt-0.5">
                                {{ grant.expires_at ? `Until ${formatDate(grant.expires_at)}` : 'No end date' }}
                                <template v-if="grant.granted_by"> · set by {{ grant.granted_by }}</template>
                            </p>
                            <p v-if="grant.reason" class="text-xs text-slate-500 mt-0.5">{{ grant.reason }}</p>
                        </div>
                        <BaseButton
                            variant="outline"
                            size="sm"
                            :loading="removingId === grant.id"
                            @click="remove(grant)"
                        >
                            Remove
                        </BaseButton>
                    </li>
                </ul>

                <p v-else class="text-xs text-slate-500">
                    None. This person gets exactly what {{ roleName || 'their role' }} gets.
                </p>

                <!-- Add one -->
                <div class="border-t border-slate-100 pt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label class="form-label" :for="`${idPrefix}-section`">Section</label>
                        <select :id="`${idPrefix}-section`" v-model="draft.section" class="form-select">
                            <option value="">Choose a section…</option>
                            <option v-for="s in assignableSections" :key="s.key" :value="s.key">
                                {{ s.label }}{{ s.allowed ? ' — they already have this' : '' }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" :for="`${idPrefix}-effect`">Give or take away</label>
                        <select :id="`${idPrefix}-effect`" v-model="draft.effect" class="form-select">
                            <option value="grant">Give them this section</option>
                            <option value="revoke">Take it away from them</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" :for="`${idPrefix}-expires`">
                            Until <span class="form-optional">Optional</span>
                        </label>
                        <input
                            :id="`${idPrefix}-expires`"
                            v-model="draft.expires_at"
                            type="date"
                            :min="tomorrow"
                            class="form-input"
                        />
                        <p class="form-hint">Leave blank to keep it open-ended.</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="form-label" :for="`${idPrefix}-reason`">
                            Why <span class="form-optional">Optional</span>
                        </label>
                        <input
                            :id="`${idPrefix}-reason`"
                            v-model="draft.reason"
                            type="text"
                            maxlength="255"
                            class="form-input"
                            placeholder="e.g. Covering invoices while Aziza is on leave"
                        />
                        <p class="form-hint">Worth a line — this is the answer when someone asks in three months.</p>
                    </div>

                    <div class="sm:col-span-2">
                        <BaseButton
                            variant="soft"
                            :disabled="!draft.section"
                            :loading="saving"
                            @click="add"
                        >
                            Add exception
                        </BaseButton>
                    </div>
                </div>
            </div>

            <!-- What they can actually reach, and why -->
            <details class="rounded-card border border-slate-200 bg-white">
                <summary class="cursor-pointer px-3 py-2.5 text-sm font-medium text-slate-800">
                    What they can reach right now ({{ allowedCount }} of {{ sections.length }})
                </summary>
                <ul class="border-t border-slate-100 divide-y divide-slate-50">
                    <li
                        v-for="section in sections"
                        :key="section.key"
                        class="flex items-center justify-between gap-3 px-3 py-2"
                    >
                        <span class="text-sm" :class="section.allowed ? 'text-slate-800' : 'text-slate-400'">
                            {{ section.label }}
                        </span>
                        <span class="text-xs shrink-0" :class="sourceTone(section)">
                            {{ sourceLabel(section) }}
                        </span>
                    </li>
                </ul>
            </details>

            <p v-if="legacyWhitelist" class="callout callout-warning">
                This person also has an old per-user menu list saved against them, which replaces
                their role entirely rather than adding to it. It still applies, underneath the
                exceptions above. Clearing it makes their role the single source again.
                <BaseButton variant="outline" size="sm" class="mt-2" :loading="clearing" @click="clearLegacy">
                    Clear the old list
                </BaseButton>
            </p>
        </template>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { BaseButton } from '@/components/base';
import { useToastStore } from '@/stores/toast';

/**
 * Access for one person: their role, plus a short list of exceptions.
 *
 * What this replaces was a second copy of the whole menu. `users.nav_permissions`
 * *replaced* the role list rather than adding to it, so giving somebody one
 * extra section meant ticking all thirty boxes by hand and then maintaining
 * that private copy forever - and if the role later changed, that person stayed
 * frozen on the old snapshot. Nobody used it. What people did instead is
 * visible in the data: 6 of 15 accounts on this system are Admin.
 *
 * An exception is one section, one direction, and usually a date. That last
 * part is the point: access nobody has to renew is how everyone ends up an
 * administrator.
 */
const props = defineProps({
    userId: { type: [Number, String], required: true },
    /** Set when the user still carries a legacy per-user menu list. */
    legacyWhitelist: { type: Boolean, default: false },
    idPrefix: { type: String, default: 'user-access' },
});

const emit = defineEmits(['legacy-cleared']);

const toast = useToastStore();

const loading = ref(true);
const saving = ref(false);
const clearing = ref(false);
const removingId = ref(null);

const sections = ref([]);
const grants = ref([]);
const roleName = ref('');
const roleHasFullAccess = ref(false);

const draft = ref({ section: '', effect: 'grant', expires_at: '', reason: '' });

const liveGrants = computed(() => grants.value.filter((g) => g.active));

const allowedCount = computed(() => sections.value.filter((s) => s.allowed).length);

/** The dashboard is where the app opens, so it is not up for discussion. */
const assignableSections = computed(() => sections.value.filter((s) => s.key !== 'dashboard'));

const tomorrow = computed(() => {
    const d = new Date();
    d.setDate(d.getDate() + 1);

    return d.toISOString().slice(0, 10);
});

function sourceLabel(section) {
    return {
        always: 'Always on',
        full_access_role: 'Administrator',
        granted: 'Given to them',
        revoked: 'Taken away',
        role: section.allowed ? `From ${roleName.value || 'their role'}` : 'Not in their role',
    }[section.source] ?? '';
}

function sourceTone(section) {
    if (section.source === 'granted') return 'text-success-700 font-medium';
    if (section.source === 'revoked') return 'text-danger-700 font-medium';

    return 'text-slate-400';
}

function formatDate(iso) {
    if (!iso) return '';

    return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

async function load() {
    loading.value = true;

    try {
        const { data } = await axios.get(`/api/users/${props.userId}/access`);
        sections.value = data.sections ?? [];
        grants.value = data.grants ?? [];
        roleName.value = data.role ?? '';
        roleHasFullAccess.value = !!data.role_has_full_access;
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Could not load access.');
        sections.value = [];
    } finally {
        loading.value = false;
    }
}

async function add() {
    if (!draft.value.section || saving.value) return;

    saving.value = true;

    try {
        await axios.post(`/api/users/${props.userId}/access`, {
            section: draft.value.section,
            effect: draft.value.effect,
            // A date input gives midnight; end of that day is what "until Friday" means.
            expires_at: draft.value.expires_at ? `${draft.value.expires_at}T23:59:59` : null,
            reason: draft.value.reason || null,
        });

        draft.value = { section: '', effect: 'grant', expires_at: '', reason: '' };
        toast.success('Saved. It applies the next time they load the app.');
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Could not save that.');
    } finally {
        saving.value = false;
    }
}

async function remove(grant) {
    removingId.value = grant.id;

    try {
        await axios.delete(`/api/users/${props.userId}/access/${grant.id}`);
        toast.success('Removed.');
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Could not remove that.');
    } finally {
        removingId.value = null;
    }
}

async function clearLegacy() {
    clearing.value = true;

    try {
        await axios.put(`/api/users/${props.userId}`, { nav_permissions: null });
        toast.success('Cleared. Their role decides again.');
        emit('legacy-cleared');
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Could not clear that.');
    } finally {
        clearing.value = false;
    }
}

watch(() => props.userId, load, { immediate: true });
</script>
