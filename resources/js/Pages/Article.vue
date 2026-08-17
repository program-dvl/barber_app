<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import MarketingCard from '@/Components/Marketing/MarketingCard.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ article: Object, related: Array });
const date = (value) => new Intl.DateTimeFormat('en-IN', { dateStyle: 'long' }).format(new Date(value));
</script>

<template>
    <HomeLayout>
        <Head :title="article.seo_title" />
        <article>
            <header class="gh-public-section border-b border-[var(--border-subtle)]"><PublicContainer><Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Resources', href: route('marketing.resources') }, { label: 'Blog', href: route('blog.index') }, { label: article.title }]" /><p class="gh-eyebrow mt-10">{{ article.topic }}</p><h1 class="mt-5 max-w-4xl font-display text-[clamp(2.75rem,7vw,5rem)] font-semibold leading-[1] tracking-[-0.05em] text-[var(--text-strong)] text-balance">{{ article.title }}</h1><p class="mt-7 max-w-3xl text-lg leading-8 text-[var(--text-muted)]">{{ article.excerpt }}</p><p class="mt-7 text-sm font-semibold text-[var(--text-muted)]">By {{ article.author }} · Published <time :datetime="article.published_at">{{ date(article.published_at) }}</time><span v-if="article.materially_updated_at"> · Materially updated <time :datetime="article.materially_updated_at">{{ date(article.materially_updated_at) }}</time></span></p></PublicContainer></header>
            <section class="gh-public-section"><PublicContainer><div class="gh-editorial-prose prose prose-lg mx-auto max-w-3xl break-words prose-headings:font-display prose-headings:text-[var(--text-strong)] prose-p:leading-8 prose-a:text-[var(--brand-pine)]" v-html="article.html" /></PublicContainer></section>
        </article>
        <section v-if="related.length" class="gh-public-section bg-[var(--surface-subtle)]"><PublicContainer><h2 class="font-display text-3xl font-semibold text-[var(--text-strong)]">Related reviewed reading</h2><div class="mt-8 grid gap-5 md:grid-cols-2"><MarketingCard v-for="item in related" :key="item.id"><h3 class="text-xl font-extrabold text-[var(--text-strong)]">{{ item.title }}</h3><p class="mt-3 text-[var(--text-muted)]">{{ item.excerpt }}</p><Link :href="route('blog.article', item.slug)" class="mt-5 inline-flex min-h-11 items-center font-extrabold text-[var(--brand-pine)] underline-offset-4 hover:underline">Read this article</Link></MarketingCard></div></PublicContainer></section>
    </HomeLayout>
</template>
