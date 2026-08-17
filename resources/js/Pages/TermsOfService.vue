<script setup>
import { Head } from '@inertiajs/vue3';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';

defineProps({
    terms: String,
});
</script>

<template>
    <Head title="Terms of Service" />

    <HomeLayout><main class="gh-public-section"><PublicContainer><div class="mx-auto max-w-3xl"><div class="mb-8 rounded-lg border-l-4 border-[var(--brand-rust)] bg-[var(--surface-subtle)] p-5 font-bold text-[var(--text-strong)]">Legal review draft — not approved for public launch or paid use.</div><article class="prose max-w-none prose-headings:font-display prose-headings:text-[var(--text-strong)] prose-p:leading-8" v-html="terms" /></div></PublicContainer></main></HomeLayout>
</template>
