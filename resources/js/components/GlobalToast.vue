<template>
    <Toast
        :visible="toast.visible"
        :type="toast.type"
        :title="toast.title"
        :message="toast.message"
        @close="toast.hide"
    />
</template>

<script setup>
import Toast from './Toast.vue';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();
</script>

