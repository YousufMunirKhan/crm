<template>
    <component :is="component" class="sidebar-nav-icon" aria-hidden="true" />
</template>

<script setup>
import { computed } from 'vue';
import {
    BanknotesIcon,
    BoltIcon,
    BriefcaseIcon,
    CalendarDaysIcon,
    ChartBarIcon,
    ChartPieIcon,
    ChatBubbleLeftEllipsisIcon,
    ChatBubbleLeftRightIcon,
    ClipboardDocumentCheckIcon,
    ClipboardDocumentIcon,
    Cog6ToothIcon,
    CubeIcon,
    CurrencyDollarIcon,
    CurrencyPoundIcon,
    DevicePhoneMobileIcon,
    DocumentTextIcon,
    EnvelopeIcon,
    FunnelIcon,
    HomeIcon,
    IdentificationIcon,
    ListBulletIcon,
    MapPinIcon,
    MegaphoneIcon,
    PhoneIcon,
    RectangleGroupIcon,
    ShieldCheckIcon,
    ShoppingCartIcon,
    Squares2X2Icon,
    TicketIcon,
    UserPlusIcon,
    UsersIcon,
    ViewfinderCircleIcon,
} from '@heroicons/vue/24/outline';

/**
 * The `name` values come from the nav payload built in AppLayout.vue and are
 * gated by app/Support/NavSections.php. The prop API is fixed - only the
 * rendering changed from a hand-drawn v-if chain to a Heroicons lookup.
 */
const ICONS = {
    dashboard: HomeIcon,
    wallet: BanknotesIcon,
    calendar: CalendarDaysIcon,
    followup: ClipboardDocumentCheckIcon,
    'user-plus': UserPlusIcon,
    users: UsersIcon,
    'id-card': IdentificationIcon,
    list: ListBulletIcon,
    funnel: FunnelIcon,
    cube: CubeIcon,
    ticket: TicketIcon,
    device: DevicePhoneMobileIcon,
    invoice: DocumentTextIcon,
    activity: BoltIcon,
    'chart-bar': ChartBarIcon,
    target: ViewfinderCircleIcon,
    shopping: ShoppingCartIcon,
    'chart-pie': ChartPieIcon,
    megaphone: MegaphoneIcon,
    mail: EnvelopeIcon,
    phone: PhoneIcon,
    sms: ChatBubbleLeftEllipsisIcon,
    message: ChatBubbleLeftRightIcon,
    template: RectangleGroupIcon,
    briefcase: BriefcaseIcon,
    currency: CurrencyDollarIcon,
    pound: CurrencyPoundIcon,
    document: DocumentTextIcon,
    clipboard: ClipboardDocumentIcon,
    'map-pin': MapPinIcon,
    shield: ShieldCheckIcon,
    cog: Cog6ToothIcon,
};

const props = defineProps({
    name: {
        type: String,
        default: 'default',
    },
});

const component = computed(() => ICONS[props.name] ?? Squares2X2Icon);
</script>

<style scoped>
/*
 * Default size only. A scoped class beats a Tailwind utility on specificity,
 * so `class="w-4 h-4"` at a call site was being silently ignored; :where()
 * drops this rule to zero specificity and lets callers override it.
 */
:where(.sidebar-nav-icon) {
    width: 1.25rem;
    height: 1.25rem;
    flex-shrink: 0;
    opacity: 0.95;
}
</style>
