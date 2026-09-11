<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { marketingFamilyVisuals } from '@/Support/marketingVisuals';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ article: Object, related: Array });
const date = (value) => new Intl.DateTimeFormat('en-IN', { dateStyle: 'long' }).format(new Date(value));
</script>

<template>
    <HomeLayout>
        <Head :title="article.seo_title" />
        <article>
            <header class="cd-public-section cd-family-hero" data-tone="amber"><PublicContainer><Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Resources', href: route('marketing.resources') }, { label: 'Blog', href: route('blog.index') }, { label: article.title }]" /><div class="cd-family-hero-grid mt-10"><div class="cd-family-hero-copy"><p class="cd-eyebrow">{{ article.topic }}</p><h1 class="mt-5 max-w-4xl font-display text-[clamp(2.75rem,7vw,5rem)] font-semibold leading-[1] tracking-[-0.055em] text-balance">{{ article.title }}</h1><p class="mt-7 max-w-3xl text-lg leading-8">{{ article.excerpt }}</p><p class="mt-7 text-sm font-semibold text-white/60">By {{ article.author }} · Published <time :datetime="article.published_at">{{ date(article.published_at) }}</time><span v-if="article.materially_updated_at"> · Materially updated <time :datetime="article.materially_updated_at">{{ date(article.materially_updated_at) }}</time></span></p></div><figure class="cd-family-hero-media"><img class="cd-family-hero-image" :src="marketingFamilyVisuals.resources.src" :alt="marketingFamilyVisuals.resources.alt" width="1536" height="1024" fetchpriority="high" /><figcaption class="cd-family-hero-caption">Reviewed guidance, grounded in day-to-day work.</figcaption></figure></div></PublicContainer></header>
            <section class="cd-public-section"><PublicContainer><div class="cd-editorial-prose prose prose-lg mx-auto max-w-3xl break-words prose-headings:font-display prose-headings:text-[var(--text-strong)] prose-p:leading-8 prose-a:text-[var(--brand-primary)]" v-html="article.html" /></PublicContainer></section>
        </article>
        <section v-if="related.length" class="cd-public-section bg-[var(--surface-subtle)]"><PublicContainer><h2 class="font-display text-3xl font-semibold text-[var(--text-strong)]">Related reviewed reading</h2><div class="cd-visual-card-grid mt-8"><article v-for="item in related" :key="item.id" class="cd-visual-card"><div class="cd-visual-card-media"><img class="cd-visual-card-image" :src="marketingFamilyVisuals.resources.src" alt="A salon owner reviewing practical business notes." width="1536" height="1024" loading="lazy" /></div><div class="cd-visual-card-body min-h-0"><h3 class="text-xl font-extrabold text-[var(--text-strong)]">{{ item.title }}</h3><p class="mt-3 text-[var(--text-muted)]">{{ item.excerpt }}</p><Link :href="route('blog.article', item.slug)" class="cd-arrow-link mt-5">Read this article</Link></div></article></div></PublicContainer></section>
    </HomeLayout>
</template>
