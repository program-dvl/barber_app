<script setup>
defineProps({
    caption: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="overflow-x-auto" tabindex="0" :aria-label="`${caption}. Scroll horizontally when needed.`">
        <table class="min-w-full border-separate border-spacing-0 text-left text-sm">
            <caption class="ds-sr-only">{{ caption }}</caption>
            <slot />
        </table>
    </div>
</template>
