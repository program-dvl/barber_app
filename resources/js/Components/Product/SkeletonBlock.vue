<script setup>
defineProps({
    lines: {
        type: Number,
        default: 3,
    },
    label: {
        type: String,
        default: 'Loading content',
    },
});
</script>

<template>
    <div role="status" :aria-label="label" aria-busy="true" class="space-y-3">
        <span class="ds-sr-only">{{ label }}</span>
        <div class="h-5 w-2/5 animate-pulse rounded bg-[var(--surface-subtle)] motion-reduce:animate-none" />
        <div
            v-for="line in lines"
            :key="line"
            class="h-3 animate-pulse rounded bg-[var(--surface-subtle)] motion-reduce:animate-none"
            :class="line === lines ? 'w-3/5' : 'w-full'"
        />
    </div>
</template>
