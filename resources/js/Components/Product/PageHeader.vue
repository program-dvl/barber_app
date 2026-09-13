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
    <header class="cd-page-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p v-if="eyebrow" class="cd-page-eyebrow mb-1 text-xs font-medium text-[var(--text-muted)]">
                {{ eyebrow }}
            </p>
            <h1 class="text-2xl font-bold tracking-tight text-[var(--text-strong)]">
                {{ title }}
            </h1>
            <p v-if="description" class="mt-1.5 max-w-3xl text-sm leading-relaxed text-[var(--text-muted)]">
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
