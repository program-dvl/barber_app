<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import clipperDeskMark from '../../../images/brand/clipperdesk-mark.svg';
import clipperDeskMarkInverse from '../../../images/brand/clipperdesk-mark-inverse.svg';

defineProps({
    compact: Boolean,
    inverse: Boolean,
    large: Boolean,
    showTagline: { type: Boolean, default: true },
});

const page = usePage();
const brand = computed(() => page.props.brand ?? {
    product_name: 'ClipperDesk',
    tagline: 'Run the day. Grow the business.',
});
</script>

<template>
    <span class="inline-flex items-center gap-2.5" :class="inverse ? 'text-white' : 'text-[var(--text-strong)]'" :aria-label="compact ? brand.product_name : undefined">
        <img :src="inverse ? clipperDeskMarkInverse : clipperDeskMark" alt="" width="64" height="64" class="w-auto shrink-0 object-contain" :class="large ? 'h-12' : 'h-10'" aria-hidden="true">
        <span v-if="!compact" class="leading-tight">
            <span class="cd-display block font-bold leading-none tracking-[-0.04em]" :class="large ? 'text-2xl' : 'text-xl'">{{ brand.product_name }}</span>
            <span v-if="showTagline" class="mt-1 block text-[0.6875rem] font-medium text-[var(--text-muted)]" :class="{ '!text-white/70': inverse }">{{ brand.tagline }}</span>
        </span>
    </span>
</template>
