<script setup>
import {
    CheckCircleIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
} from '@heroicons/vue/24/outline';
const props = defineProps({
    tone: {
        type: String,
        default: 'empty',
        validator: value => ['empty', 'loading', 'error', 'success', 'info'].includes(value),
    },
    title: {
        type: String,
        required: true,
    },
    description: String,
    compact: Boolean,
});

const toneClasses = {
    empty: 'bg-[var(--surface-subtle)] text-[var(--text-default)]',
    loading: 'bg-[var(--status-info-soft)] text-[var(--status-info)]',
    error: 'bg-[var(--status-danger-soft)] text-[var(--status-danger)]',
    success: 'bg-[var(--status-success-soft)] text-[var(--status-success)]',
    info: 'bg-[var(--status-info-soft)] text-[var(--status-info)]',
};
</script>

<template>
    <div
        :class="['cd-state-panel text-center', compact ? 'p-4' : 'px-4 py-6']"
        :role="tone === 'error' ? 'alert' : ['success', 'loading'].includes(tone) ? 'status' : undefined"
        :aria-busy="tone === 'loading' || undefined"
    >
        <span :class="[toneClasses[props.tone], 'mx-auto mb-2 grid size-8 place-items-center rounded-full']" aria-hidden="true">
            <CheckCircleIcon v-if="tone === 'success'" class="size-5" />
            <ExclamationTriangleIcon v-else-if="tone === 'error'" class="size-5" />
            <span v-else-if="tone === 'loading'" class="size-5 animate-spin rounded-full border-2 border-current border-r-transparent motion-reduce:animate-none" />
            <InformationCircleIcon v-else class="size-5" />
        </span>
        <h3 class="font-semibold text-[var(--text-strong)]">{{ title }}</h3>
        <p v-if="description" class="mx-auto mt-1 max-w-xl text-sm leading-6 text-[var(--text-muted)]">{{ description }}</p>
        <div v-if="$slots.actions" class="mt-4 flex flex-wrap justify-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
