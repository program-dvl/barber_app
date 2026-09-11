<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { guideVisuals, marketingFamilyVisuals, visualFor } from '@/Support/marketingVisuals';
import { CheckIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ guide: Object });
</script>

<template>
    <HomeLayout>
        <Head :title="guide.title" />
        <article>
            <header class="cd-public-section cd-family-hero" data-tone="amber">
                <PublicContainer>
                    <Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Resources', href: route('marketing.resources') }, { label: guide.title }]" />
                    <div class="cd-family-hero-grid mt-10">
                        <div class="cd-family-hero-copy">
                            <p class="cd-eyebrow">{{ guide.topic }}</p>
                            <h1 class="mt-5 max-w-4xl font-display text-[clamp(2.75rem,7vw,5rem)] font-semibold leading-[1] tracking-[-0.055em] text-balance">{{ guide.title }}</h1>
                            <p class="mt-7 max-w-3xl text-lg leading-8">{{ guide.intro }}</p>
                            <p class="mt-6 text-sm font-semibold text-white/60">Published {{ guide.published_at }} · Reviewed by {{ guide.reviewed_by }}</p>
                        </div>
                        <figure class="cd-family-hero-media">
                            <img class="cd-family-hero-image" :src="visualFor(guideVisuals, guide.slug, marketingFamilyVisuals.resources).src" :alt="visualFor(guideVisuals, guide.slug, marketingFamilyVisuals.resources).alt" width="1536" height="1024" fetchpriority="high" />
                            <figcaption class="cd-family-hero-caption">A practical field guide for the working week.</figcaption>
                        </figure>
                    </div>
                </PublicContainer>
            </header>

            <section class="cd-public-section">
                <PublicContainer class="grid gap-12 lg:grid-cols-[1.2fr_0.8fr]">
                    <div>
                        <ol class="cd-story-list">
                            <li v-for="(section, index) in guide.sections" :key="section.title" class="cd-story-row">
                                <span class="cd-story-number" aria-hidden="true">0{{ index + 1 }}</span>
                                <h2 class="font-display text-2xl font-bold leading-tight text-[var(--text-strong)]">{{ section.title }}</h2>
                                <p class="leading-8 text-[var(--text-muted)]">{{ section.body }}</p>
                            </li>
                        </ol>
                    </div>
                    <aside class="lg:sticky lg:top-28 lg:self-start">
                        <div class="cd-checklist-panel">
                            <p class="cd-eyebrow">Keep beside you</p>
                            <h2 class="mt-3 text-2xl font-extrabold text-[var(--text-strong)]">Working checklist</h2>
                            <ul class="mt-6 space-y-4">
                                <li v-for="item in guide.checklist" :key="item" class="flex gap-3 leading-7 text-[var(--text-muted)]"><span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-indigo-50"><CheckIcon class="size-4 text-[var(--action-primary)]" aria-hidden="true" /></span>{{ item }}</li>
                            </ul>
                        </div>
                        <div class="mt-5 rounded-[var(--radius-lg)] border-l-4 border-[var(--status-warning)] bg-[var(--status-warning-soft)] p-6 leading-7 text-[var(--text-muted)]">{{ guide.limitation }}</div>
                        <Link :href="route('marketing.features.show', guide.feature)" class="cd-button cd-button-secondary mt-6">Explore the supporting feature</Link>
                    </aside>
                </PublicContainer>
            </section>
        </article>
    </HomeLayout>
</template>
