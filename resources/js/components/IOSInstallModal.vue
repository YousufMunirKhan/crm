<template>
    <BaseModal
        :model-value="visible"
        title="Install CRM App"
        description="Add to your home screen for the best experience"
        size="sm"
        @close="$emit('close')"
    >
        <!-- App icon -->
        <div class="mb-6 flex justify-center">
            <div class="w-20 h-20 bg-slate-900 rounded-2xl flex items-center justify-center shadow-xl overflow-hidden">
                <img
                    v-if="branding.faviconUrl"
                    :src="faviconDisplayUrl"
                    alt=""
                    class="w-full h-full object-cover"
                >
                <span v-else class="text-primary-300 font-bold text-2xl">S&amp;S</span>
            </div>
        </div>

        <!-- Instructions -->
        <div class="space-y-4">
            <!-- Step 1 -->
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <span class="text-primary-700 font-bold">1</span>
                </div>
                <div class="flex-1">
                    <p class="font-medium text-slate-900">Tap the Share button</p>
                    <div class="flex items-center gap-2 mt-2">
                        <div class="p-2 bg-slate-100 rounded-lg">
                            <ShareIcon class="icon text-primary-700" aria-hidden="true" />
                        </div>
                        <span class="text-sm text-slate-500">at the bottom of Safari</span>
                    </div>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <span class="text-primary-700 font-bold">2</span>
                </div>
                <div class="flex-1">
                    <p class="font-medium text-slate-900">Scroll down and tap</p>
                    <div class="flex items-center gap-2 mt-2">
                        <div class="p-2 bg-slate-100 rounded-lg flex items-center gap-2">
                            <PlusCircleIcon class="icon text-slate-700" aria-hidden="true" />
                            <span class="text-sm font-medium text-slate-700">Add to Home Screen</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 bg-success-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <CheckIcon class="icon text-success-700" aria-hidden="true" />
                </div>
                <div class="flex-1">
                    <p class="font-medium text-slate-900">Tap "Add" to confirm</p>
                    <p class="text-sm text-slate-500 mt-1">The app icon will appear on your home screen</p>
                </div>
            </div>
        </div>

        <template #actions>
            <BaseButton variant="primary" block-mobile @click="$emit('close')">Got it!</BaseButton>
        </template>
    </BaseModal>
</template>

<script setup>
import { computed } from 'vue';
import { CheckIcon, PlusCircleIcon, ShareIcon } from '@heroicons/vue/24/outline';
import { BaseButton, BaseModal } from '@/components/base';
import { useBrandingStore } from '@/stores/branding';
import { absolutePublicUrl } from '@/utils/branding';

defineProps({
    visible: {
        type: Boolean,
        default: false
    }
});

defineEmits(['close']);

const branding = useBrandingStore();
const faviconDisplayUrl = computed(() => absolutePublicUrl(branding.faviconUrl));
</script>
