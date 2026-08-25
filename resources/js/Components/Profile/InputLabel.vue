<script setup>
defineProps({
    value: String,
});
</script>

<template>
    <label class="block text-sm font-semibold text-[var(--text-strong)]">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
