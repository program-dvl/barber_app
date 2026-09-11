<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { marketingFamilyVisuals } from '@/Support/marketingVisuals';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ articles: Object });
const date = (value) => new Intl.DateTimeFormat('en-IN', { dateStyle: 'long' }).format(new Date(value));
</script>

<template>
    <HomeLayout>
        <Head title="ClipperDesk salon operations blog" />
        <section class="cd-public-section cd-family-hero" data-tone="amber"><PublicContainer><Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Resources', href: route('marketing.resources') }, { label: 'Blog' }]" /><div class="cd-family-hero-grid mt-10"><div class="cd-family-hero-copy"><p class="cd-eyebrow">Editorial</p><h1 class="mt-5 max-w-4xl font-display text-[clamp(3rem,7vw,5.25rem)] font-semibold leading-[0.98] tracking-[-0.055em] text-balance">Reviewed notes for a clearer salon day.</h1><p class="mt-7 max-w-3xl text-lg leading-8">Articles appear only after authorship, review, metadata, publication date and safe rendering checks are complete.</p></div><figure class="cd-family-hero-media"><img class="cd-family-hero-image" :src="marketingFamilyVisuals.resources.src" :alt="marketingFamilyVisuals.resources.alt" width="1536" height="1024" fetchpriority="high" /><figcaption class="cd-family-hero-caption">Thoughtful notes for people-powered businesses.</figcaption></figure></div></PublicContainer></section>
        <section class="cd-public-section"><PublicContainer><div v-if="articles.data.length" class="cd-visual-card-grid"><article v-for="article in articles.data" :key="article.id" class="cd-visual-card"><div class="cd-visual-card-media"><img class="cd-visual-card-image" :src="marketingFamilyVisuals.resources.src" alt="A salon owner planning and reviewing practical business guidance." width="1536" height="1024" loading="lazy" /></div><div class="cd-visual-card-body"><p class="cd-eyebrow">{{ article.topic }}</p><h2 class="mt-4 text-xl font-extrabold text-[var(--text-strong)]">{{ article.title }}</h2><p class="mt-4 grow leading-7 text-[var(--text-muted)]">{{ article.excerpt }}</p><p class="mt-5 text-sm text-[var(--text-muted)]">By {{ article.author }} · <time :datetime="article.published_at">{{ date(article.published_at) }}</time></p><Link :href="route('blog.article', article.slug)" class="cd-arrow-link mt-5">Read the article</Link></div></article></div><div v-else class="cd-answer-panel mx-auto max-w-2xl text-center"><h2 class="font-display text-3xl font-semibold text-[var(--text-strong)]">No reviewed articles are published yet.</h2><p class="mt-4 leading-7 text-[var(--text-muted)]">The two maintained operating guides remain available while the editorial owner reviews future articles.</p><Link :href="route('marketing.resources')" class="cd-button cd-button-secondary mt-6">Browse resources</Link></div></PublicContainer></section>
    </HomeLayout>
</template>
