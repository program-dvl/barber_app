<script setup>
defineProps({
    embedded: Boolean,
    wide: Boolean,
});
</script>

<template>
    <div :class="['flex flex-col items-center', embedded ? '' : 'min-h-screen pt-6 sm:justify-center sm:pt-0']">
        <div v-if="$slots.logo">
            <slot name="logo" />
        </div>

        <div :class="[
            'mt-6 w-full overflow-hidden rounded-[var(--radius-xl)] border border-[var(--border-subtle)] bg-[var(--surface-raised)] px-6 py-7 shadow-[var(--shadow-raised)] sm:px-9 sm:py-8',
            wide ? 'sm:max-w-2xl' : 'sm:max-w-md',
        ]">
            <slot />
        </div>
    </div>
</template>
