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
    <section :class="['overflow-hidden rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-raised)]', elevated ? 'shadow-[var(--shadow-raised)]' : '']">
        <header v-if="title || description || $slots.actions" :class="['flex items-start justify-between gap-4 border-b border-[var(--border-subtle)]', compact ? 'px-4 py-3' : 'px-5 py-4 sm:px-6']">
            <div>
                <h2 v-if="title" class="font-semibold text-[var(--text-strong)]">{{ title }}</h2>
                <p v-if="description" class="mt-1 text-sm text-[var(--text-muted)]">{{ description }}</p>
            </div>
            <slot name="actions" />
        </header>
        <div :class="padding ? (compact ? 'p-4' : 'p-5 sm:p-6') : ''">
            <slot />
        </div>
    </section>
</template>
