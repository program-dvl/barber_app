<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import MarketingCard from '@/Components/Marketing/MarketingCard.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ articles: Object });
const date = (value) => new Intl.DateTimeFormat('en-IN', { dateStyle: 'long' }).format(new Date(value));
</script>

<template>
    <HomeLayout>
        <Head title="Good Hours salon operations blog" />
        <section class="gh-public-section border-b border-[var(--border-subtle)]"><PublicContainer><Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Resources', href: route('marketing.resources') }, { label: 'Blog' }]" /><p class="gh-eyebrow mt-10">Editorial</p><h1 class="mt-5 max-w-4xl font-display text-[clamp(3rem,7vw,5.25rem)] font-semibold leading-[0.98] tracking-[-0.05em] text-[var(--text-strong)] text-balance">Reviewed notes for a clearer salon day.</h1><p class="mt-7 max-w-3xl text-lg leading-8 text-[var(--text-muted)]">Articles appear only after authorship, review, metadata, publication date and safe rendering checks are complete.</p></PublicContainer></section>
        <section class="gh-public-section"><PublicContainer><div v-if="articles.data.length" class="grid gap-5 md:grid-cols-2 lg:grid-cols-3"><MarketingCard v-for="article in articles.data" :key="article.id" class="flex flex-col"><p class="gh-eyebrow">{{ article.topic }}</p><h2 class="mt-4 text-xl font-extrabold text-[var(--text-strong)]">{{ article.title }}</h2><p class="mt-4 grow leading-7 text-[var(--text-muted)]">{{ article.excerpt }}</p><p class="mt-5 text-sm text-[var(--text-muted)]">By {{ article.author }} · <time :datetime="article.published_at">{{ date(article.published_at) }}</time></p><Link :href="route('blog.article', article.slug)" class="mt-5 inline-flex min-h-11 items-center font-extrabold text-[var(--brand-pine)] underline-offset-4 hover:underline">Read the article</Link></MarketingCard></div><div v-else class="mx-auto max-w-2xl rounded-[var(--radius-xl)] border border-[var(--border-subtle)] bg-[var(--surface-subtle)] p-8 text-center"><h2 class="font-display text-3xl font-semibold text-[var(--text-strong)]">No reviewed articles are published yet.</h2><p class="mt-4 leading-7 text-[var(--text-muted)]">The two maintained operating guides remain available while the editorial owner reviews future articles.</p><Link :href="route('marketing.resources')" class="gh-button gh-button-secondary mt-6">Browse resources</Link></div></PublicContainer></section>
    </HomeLayout>
</template>
