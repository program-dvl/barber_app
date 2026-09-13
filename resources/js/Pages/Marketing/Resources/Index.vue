<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { guideVisuals, marketingFamilyVisuals, visualFor } from '@/Support/marketingVisuals';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ guides: Array, articleCount: Number, seo: Object });
</script>

<template>
    <HomeLayout>
        <Head :title="seo.title" />

        <section class="cd-public-section cd-family-hero" data-tone="amber">
            <PublicContainer>
                <Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Resources' }]" />
                <div class="cd-family-hero-grid mt-10">
                    <div class="cd-family-hero-copy">
                        <p class="cd-eyebrow">Resources</p>
                        <h1 class="mt-5 max-w-4xl font-display text-[clamp(3rem,7vw,5.25rem)] font-semibold leading-[0.98] tracking-[-0.055em] text-balance">Practical guidance for the operating work behind a good day.</h1>
                        <p class="mt-7 max-w-3xl text-lg leading-8 sm:text-xl">Practical booking, scheduling and operations guidance for appointment-led businesses—published only with a named author, review evidence and a real publication date.</p>
                    </div>
                    <figure class="cd-family-hero-media">
                        <img class="cd-family-hero-image" :src="marketingFamilyVisuals.resources.src" :alt="marketingFamilyVisuals.resources.alt" width="1536" height="1024" fetchpriority="high" />
                        <figcaption class="cd-family-hero-caption">Clear thinking before the doors open.</figcaption>
                    </figure>
                </div>
            </PublicContainer>
        </section>

        <section class="cd-public-section">
            <PublicContainer>
                <div class="mb-11 max-w-3xl">
                    <p class="cd-eyebrow">Working guides</p>
                    <h2 class="cd-section-title">Designed to be used, not simply read.</h2>
                </div>
                <div class="cd-visual-card-grid">
                    <article v-for="(guide, index) in guides" :key="guide.slug" class="cd-visual-card">
                        <div class="cd-visual-card-media">
                            <img class="cd-visual-card-image" :src="visualFor(guideVisuals, guide.slug, marketingFamilyVisuals.resources).src" :alt="visualFor(guideVisuals, guide.slug, marketingFamilyVisuals.resources).alt" width="1536" height="1024" loading="lazy" />
                            <span class="cd-visual-card-index" aria-hidden="true">0{{ index + 1 }}</span>
                        </div>
                        <div class="cd-visual-card-body">
                            <p class="cd-eyebrow">{{ guide.topic }}</p>
                            <h2 class="mt-4 text-2xl font-extrabold text-[var(--text-strong)]">{{ guide.title }}</h2>
                            <p class="mt-4 grow leading-7 text-[var(--text-muted)]">{{ guide.description }}</p>
                            <Link :href="route('marketing.guides.show', guide.slug)" class="cd-arrow-link mt-6">Read the guide <ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                        </div>
                    </article>
                </div>

                <div class="cd-resource-band mt-12 flex flex-col gap-6 p-7 sm:flex-row sm:items-center sm:justify-between sm:p-10">
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-[0.15em] text-[#fde047]">ClipperDesk editorial</p>
                        <h2 class="mt-3 font-display text-3xl">Fresh thinking for a better-run business.</h2>
                        <p class="mt-3 text-white/75">{{ articleCount }} reviewed {{ articleCount === 1 ? 'article is' : 'articles are' }} currently published.</p>
                    </div>
                    <Link :href="route('blog.index')" class="cd-button shrink-0 bg-white text-[var(--brand-primary)]">Browse the blog</Link>
                </div>
            </PublicContainer>
        </section>
    </HomeLayout>
</template>
