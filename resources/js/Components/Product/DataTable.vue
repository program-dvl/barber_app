<script setup>
defineProps({
    caption: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="cd-table-scroll overflow-x-auto" role="region" tabindex="0" :aria-label="`${caption}. Scroll horizontally when needed.`">
        <table class="cd-data-table min-w-full border-separate border-spacing-0 text-left text-sm">
            <caption class="ds-sr-only">{{ caption }}</caption>
            <slot />
        </table>
    </div>
</template>
