<script setup>
import AppNotice from './AppNotice.vue';
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
        <AppNotice
            v-for="message in messages"
            :key="message.id"
            class="pointer-events-auto relative shadow-[var(--shadow-overlay)]"
            style="padding-right:56px"
            :tone="['error', 'warning', 'success'].includes(message.tone) ? message.tone : 'info'"
            :title="message.title"
            :description="message.description"
        >
            <template #trailing><button type="button" class="absolute right-1 top-1 min-h-11 min-w-11 rounded-lg text-[var(--text-muted)] hover:bg-[var(--surface-subtle)]" :aria-label="`Dismiss ${message.title}`" @click="$emit('dismiss', message.id)">×</button></template>
        </AppNotice>
    </div>
</template>
