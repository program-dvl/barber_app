<script setup>
defineProps({
    id: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    hint: String,
    error: String,
    required: Boolean,
});
</script>

<template>
    <div>
        <label :for="id" class="block text-sm font-semibold text-[var(--text-strong)]">
            {{ label }}
            <span v-if="required" aria-hidden="true" class="text-[var(--status-danger)]">*</span>
            <span v-if="required" class="ds-sr-only"> required</span>
        </label>
        <p v-if="hint" :id="`${id}-hint`" class="mt-1 text-sm text-[var(--text-muted)]">{{ hint }}</p>
        <div class="mt-2">
            <slot :describedby="[hint ? `${id}-hint` : null, error ? `${id}-error` : null].filter(Boolean).join(' ') || undefined" />
        </div>
        <p v-if="error" :id="`${id}-error`" role="alert" class="mt-1.5 text-sm font-medium text-[var(--status-danger)]">{{ error }}</p>
    </div>
</template>
