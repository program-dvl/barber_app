<script setup>
defineProps({
    title: String,
    description: String,
    padding: {
        type: Boolean,
        default: true,
    },
    elevated: Boolean,
    compact: Boolean,
});
</script>

<template>
    <section :class="['cd-surface overflow-hidden border border-[var(--border-subtle)] bg-[var(--surface-raised)]', elevated ? 'shadow-[var(--shadow-raised)]' : '', compact ? 'cd-surface-compact' : '']">
        <header v-if="title || description || $slots.actions" class="cd-surface-header flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border-subtle)]">
            <div>
                <h2 v-if="title" class="font-semibold text-[var(--text-strong)]">{{ title }}</h2>
                <p v-if="description" class="mt-1 text-sm text-[var(--text-muted)]">{{ description }}</p>
            </div>
            <slot name="actions" />
        </header>
        <div :class="padding ? 'cd-surface-body' : 'cd-surface-flush'">
            <slot />
        </div>
    </section>
</template>
