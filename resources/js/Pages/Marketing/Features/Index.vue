<script setup>
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import SectionHeading from '@/Components/Marketing/SectionHeading.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { featureVisuals, marketingFamilyVisuals, visualFor } from '@/Support/marketingVisuals';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ features: Array, seo: Object });
</script>

<template>
    <HomeLayout>
        <Head :title="seo.title" />
        <section class="cd-public-section cd-family-hero">
            <PublicContainer class="cd-family-hero-grid">
                <div class="cd-family-hero-copy">
                    <p class="cd-eyebrow">Product</p>
                    <h1 class="mt-5 max-w-4xl font-display cd-public-title font-semibold leading-[0.98] tracking-[-0.055em] text-balance">Manage the work around every appointment.</h1>
                    <p class="mt-7 max-w-2xl text-lg leading-8">Connect online booking, your daily calendar, client records and checkout, so your team can follow each visit from arrival to receipt.</p>
                </div>
                <figure class="cd-family-hero-media">
                    <img class="cd-family-hero-image" :src="marketingFamilyVisuals.product.src" :alt="marketingFamilyVisuals.product.alt" width="1536" height="1024" fetchpriority="high" />
                    <figcaption class="cd-family-hero-caption">Booking, client records and checkout in one workspace.</figcaption>
                </figure>
            </PublicContainer>
        </section>

        <section class="cd-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="Connected features" title="From the first booking to the next visit." />
                <div class="cd-visual-card-grid mt-12">
                    <article v-for="(feature, index) in features" :key="feature.slug" class="cd-visual-card">
                        <div class="cd-visual-card-media">
                            <img class="cd-visual-card-image" :src="visualFor(featureVisuals, feature.slug, marketingFamilyVisuals.product).src" :alt="visualFor(featureVisuals, feature.slug, marketingFamilyVisuals.product).alt" width="1536" height="1024" loading="lazy" />
                            <span class="cd-visual-card-index" aria-hidden="true">0{{ index + 1 }}</span>
                        </div>
                        <div class="cd-visual-card-body">
                            <p class="cd-eyebrow">{{ feature.label }}</p>
                            <h3 class="mt-4 text-2xl font-extrabold leading-tight text-[var(--text-strong)]">{{ feature.title }}</h3>
                            <p class="mt-4 flex-1 leading-7 text-[var(--text-muted)]">{{ feature.description }}</p>
                            <Link :href="route('marketing.features.show', feature.slug)" class="cd-arrow-link mt-5">
                                Explore {{ feature.label.toLowerCase() }}
                                <ArrowRightIcon class="size-4" aria-hidden="true" />
                            </Link>
                        </div>
                    </article>
                </div>
            </PublicContainer>
        </section>

        <ConversionBand title="See how the connected day fits your business." description="Add your services and team, set working hours and preview your booking page." context="features_hub_final" />
    </HomeLayout>
</template>
