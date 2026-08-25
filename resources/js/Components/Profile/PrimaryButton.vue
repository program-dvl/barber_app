<script setup>
import AppButton from '@/Components/Product/AppButton.vue';

defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <AppButton :type="type" variant="primary">
        <slot />
    </AppButton>
</template>
