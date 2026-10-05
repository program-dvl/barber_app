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
        <p v-if="eyebrow && eyebrow !== title" class="cd-eyebrow">{{ eyebrow }}</p>
        <component :is="`h${level}`" class="cd-public-section-title">{{ title }}</component>
        <p v-if="description" class="mt-3 text-base leading-7 text-[var(--text-muted)]">{{ description }}</p>
    </div>
</template>
