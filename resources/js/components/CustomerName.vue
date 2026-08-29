<template>
    <span class="block min-w-0">
        <span class="block truncate" :class="nameClass" :title="fullTitle">{{ contact }}</span>
        <span
            v-if="company"
            class="block text-xs text-slate-600 break-words line-clamp-2"
            :title="company"
        >{{ company }}</span>
    </span>
</template>

<script setup>
import { computed } from 'vue';
import { companyName, contactName } from '@/utils/customerLabel';

/**
 * A customer is a person at a company, and lists showed only one of the two.
 * The leads table printed "Greig Taylor" with no sign of the business, so a
 * manager who knows the account by its trading name could not recognise their
 * own pipeline. The naming rules live in @/utils/customerLabel so the stacked
 * form here and the inline form used in dense digests cannot drift apart.
 */
const props = defineProps({
    /** A customer record, or a report row carrying customer_name / customer_business_name. */
    customer: { type: Object, default: () => ({}) },
    /** Shown when the record has no name at all. */
    fallback: { type: String, default: '—' },
    nameClass: { type: String, default: 'font-medium text-slate-900' },
});

const contact = computed(() => contactName(props.customer, props.fallback));
const company = computed(() => companyName(props.customer));
const fullTitle = computed(() =>
    company.value ? `${contact.value} · ${company.value}` : contact.value,
);
</script>
