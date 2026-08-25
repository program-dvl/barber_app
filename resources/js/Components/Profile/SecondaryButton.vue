<script setup>
import AppButton from '@/Components/Product/AppButton.vue';

defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <AppButton :type="type" variant="secondary">
        <slot />
    </AppButton>
</template>
