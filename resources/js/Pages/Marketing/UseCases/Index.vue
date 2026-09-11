<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import SectionHeading from '@/Components/Marketing/SectionHeading.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { marketingFamilyVisuals, useCaseVisuals, visualFor } from '@/Support/marketingVisuals';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ useCases: Array });
</script>

<template>
    <HomeLayout>
        <Head title="Practical salon operating use cases" />
        <section class="cd-public-section cd-family-hero" data-tone="coral">
            <PublicContainer>
                <Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Use cases' }]" />
                <div class="cd-family-hero-grid mt-10">
                    <div class="cd-family-hero-copy">
                        <p class="cd-eyebrow">Use cases</p>
                        <h1 class="mt-5 max-w-4xl font-display text-[clamp(3rem,7vw,5.25rem)] font-semibold leading-[0.98] tracking-[-0.055em] text-balance">Start with the operating problem, not a feature list.</h1>
                        <p class="mt-7 max-w-3xl text-lg leading-8 sm:text-xl">Use these practical guides to diagnose a recurring salon-day problem, improve the process and see where verified ClipperDesk behavior can help.</p>
                    </div>
                    <figure class="cd-family-hero-media">
                        <img class="cd-family-hero-image" :src="marketingFamilyVisuals.useCases.src" :alt="marketingFamilyVisuals.useCases.alt" width="1536" height="1024" fetchpriority="high" />
                        <figcaption class="cd-family-hero-caption">Real pressure points. Practical operating responses.</figcaption>
                    </figure>
                </div>
            </PublicContainer>
        </section>

        <section class="cd-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="Four distinct jobs" title="Useful guidance, with the product limits beside it" description="Each page answers one problem and links to the exact supporting workflow—without promising a guaranteed business outcome." />
                <div class="cd-visual-card-grid mt-12">
                    <article v-for="(item, index) in useCases" :key="item.slug" class="cd-visual-card">
                        <div class="cd-visual-card-media">
                            <img class="cd-visual-card-image" :src="visualFor(useCaseVisuals, item.slug, marketingFamilyVisuals.useCases).src" :alt="visualFor(useCaseVisuals, item.slug, marketingFamilyVisuals.useCases).alt" width="1536" height="1024" loading="lazy" />
                            <span class="cd-visual-card-index" aria-hidden="true">0{{ index + 1 }}</span>
                        </div>
                        <div class="cd-visual-card-body">
                            <p class="cd-eyebrow">{{ item.label }}</p>
                            <h2 class="mt-5 text-2xl font-extrabold leading-tight text-[var(--text-strong)]">{{ item.title }}</h2>
                            <p class="mt-4 grow leading-7 text-[var(--text-muted)]">{{ item.description }}</p>
                            <Link :href="route('marketing.use-cases.show', item.slug)" class="cd-arrow-link mt-6">Read the practical guide <ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                        </div>
                    </article>
                </div>
            </PublicContainer>
        </section>

        <ConversionBand title="Ready to connect the workflow?" description="Explore ClipperDesk with a verified trial, then configure the rules for your Business before publishing booking." context="use_cases_hub_final" />
    </HomeLayout>
</template>
