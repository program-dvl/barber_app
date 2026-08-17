<script setup>
defineProps({ caption: { type: String, required: true } });
</script>

<template>
    <figure>
        <div class="overflow-hidden rounded-[calc(var(--radius-lg)+0.25rem)] border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-2 shadow-[var(--shadow-raised)]">
            <slot />
        </div>
        <figcaption class="mt-3 text-sm leading-6 text-[var(--text-muted)]">{{ caption }}</figcaption>
    </figure>
</template>
