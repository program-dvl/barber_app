<script setup>
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({ status: Number });
const page = usePage();
const title = computed(() => ({ 403: 'You do not have access to this page', 404: 'Page not found', 410: 'This page is no longer available', 503: 'ClipperDesk is temporarily unavailable' }[props.status] ?? 'Something went wrong'));
</script>
<template><HomeLayout><Head :title="title" /><main class="cd-public-section"><PublicContainer><div class="mx-auto max-w-2xl text-center"><p class="cd-eyebrow">Error {{ status }}</p><h1 class="mt-5 font-display text-5xl font-semibold text-[var(--text-strong)]">{{ title }}</h1><p class="mt-6 leading-8 text-[var(--text-muted)]">The address may be incomplete, expired or unavailable. No private route details are shown here.</p><Link :href="page.props.auth?.user ? route('dashboard') : route('marketing.home')" class="cd-button cd-button-primary mt-8">{{ page.props.auth?.user ? 'Open dashboard' : 'Return home' }}</Link></div></PublicContainer></main></HomeLayout></template>
