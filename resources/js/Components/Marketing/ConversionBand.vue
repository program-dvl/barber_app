<script setup>
import PublicCta from '@/Components/Marketing/PublicCta.vue';

defineProps({ title: { type: String, required: true }, description: { type: String, required: true }, context: { type: String, required: true } });
</script>

<template>
    <section class="gh-public-section">
        <div class="gh-public-container">
            <div class="overflow-hidden rounded-[1.5rem] bg-[var(--brand-pine)] px-6 py-10 text-white shadow-[var(--shadow-raised)] sm:px-10 lg:flex lg:items-center lg:justify-between lg:gap-12">
                <div class="max-w-2xl">
                    <h2 class="font-display text-3xl leading-tight sm:text-4xl">{{ title }}</h2>
                    <p class="mt-4 leading-7 text-white/78">{{ description }}</p>
                </div>
                <div class="mt-7 shrink-0 lg:mt-0"><PublicCta :context="context" /></div>
            </div>
        </div>
    </section>
</template>
