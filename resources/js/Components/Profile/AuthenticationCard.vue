<script setup>
defineProps({ embedded: Boolean });
</script>

<template>
    <div :class="['flex flex-col items-center', embedded ? '' : 'min-h-screen pt-6 sm:justify-center sm:pt-0']">
        <div>
            <slot name="logo" />
        </div>

        <div :class="['mt-6 w-full overflow-hidden rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-raised)] px-6 py-6 sm:max-w-md sm:px-8', embedded ? '' : 'shadow-[var(--shadow-raised)]']">
            <slot />
        </div>
    </div>
</template>
