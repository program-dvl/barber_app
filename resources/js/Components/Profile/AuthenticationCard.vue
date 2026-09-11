<script setup>
defineProps({
    embedded: Boolean,
    wide: Boolean,
});
</script>

<template>
    <div :class="['flex flex-col', embedded ? 'items-stretch' : 'min-h-screen items-center pt-6 sm:justify-center sm:pt-0']">
        <div v-if="$slots.logo">
            <slot name="logo" />
        </div>

        <div :class="[
            'cd-auth-card mx-auto w-full overflow-hidden border bg-[var(--surface-raised)] px-6 py-7 sm:px-9 sm:py-8',
            embedded ? 'mt-0' : 'mt-6',
            wide ? 'sm:max-w-2xl' : 'sm:max-w-md',
        ]">
            <slot />
        </div>
    </div>
</template>
