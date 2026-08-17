<script setup>
defineProps({
    eyebrow: { type: String, default: '' },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    align: { type: String, default: 'left' },
    level: { type: Number, default: 2 },
});
</script>

<template>
    <div :class="['max-w-3xl', align === 'center' && 'mx-auto text-center']">
        <p v-if="eyebrow" class="gh-eyebrow">{{ eyebrow }}</p>
        <component :is="`h${level}`" class="gh-section-title">{{ title }}</component>
        <p v-if="description" class="mt-5 text-base leading-8 text-[var(--text-muted)] sm:text-lg">{{ description }}</p>
    </div>
</template>
