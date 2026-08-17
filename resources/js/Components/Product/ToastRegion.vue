<script setup>
defineProps({
    messages: {
        type: Array,
        default: () => [],
    },
});

defineEmits(['dismiss']);
</script>

<template>
    <div aria-live="polite" aria-atomic="false" class="pointer-events-none fixed inset-x-4 top-4 z-50 ml-auto flex max-w-sm flex-col gap-2 sm:inset-x-auto sm:right-4">
        <div
            v-for="message in messages"
            :key="message.id"
            class="pointer-events-auto flex items-start gap-3 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-4 shadow-[var(--shadow-overlay)]"
            :role="message.tone === 'error' ? 'alert' : 'status'"
        >
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-[var(--text-strong)]">{{ message.title }}</p>
                <p v-if="message.description" class="mt-1 text-sm text-[var(--text-muted)]">{{ message.description }}</p>
            </div>
            <button type="button" class="min-h-11 min-w-11 rounded-lg text-[var(--text-muted)] hover:bg-[var(--surface-subtle)]" :aria-label="`Dismiss ${message.title}`" @click="$emit('dismiss', message.id)">×</button>
        </div>
    </div>
</template>
