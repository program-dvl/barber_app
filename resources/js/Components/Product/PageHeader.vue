<script setup>
defineProps({
    eyebrow: String,
    title: {
        type: String,
        required: true,
    },
    description: String,
});
</script>

<template>
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p v-if="eyebrow" class="mb-1 text-xs font-semibold uppercase tracking-[0.12em] text-[var(--action-primary)]">
                {{ eyebrow }}
            </p>
            <h1 class="text-2xl font-bold tracking-tight text-[var(--text-strong)] sm:text-3xl">
                {{ title }}
            </h1>
            <p v-if="description" class="mt-2 max-w-3xl text-sm leading-6 text-[var(--text-muted)] sm:text-base">
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
