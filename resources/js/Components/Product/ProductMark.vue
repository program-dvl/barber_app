<script setup>
import goodHoursMark from '../../../images/brand/good-hours-mark.png';

defineProps({
    compact: Boolean,
    inverse: Boolean,
    large: Boolean,
});
</script>

<template>
    <span class="inline-flex items-center gap-2.5" :class="inverse ? 'text-white' : 'text-[var(--text-strong)]'" :aria-label="compact ? 'Good Hours' : undefined">
        <img :src="goodHoursMark" alt="" width="456" height="512" class="w-auto shrink-0 object-contain" :class="large ? 'h-14' : 'h-11'" aria-hidden="true">
        <span v-if="!compact" class="leading-tight">
            <span class="gh-display block leading-none" :class="large ? 'text-2xl' : 'text-xl'">Good Hours</span>
            <span class="mt-1 block text-[0.6875rem] font-medium text-[var(--text-muted)]" :class="{ '!text-white/70': inverse }">Make every hour count.</span>
        </span>
    </span>
</template>
