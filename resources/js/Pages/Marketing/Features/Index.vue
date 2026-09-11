<script setup>
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import SectionHeading from '@/Components/Marketing/SectionHeading.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { featureVisuals, marketingFamilyVisuals, visualFor } from '@/Support/marketingVisuals';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ features: Array });
</script>

<template>
    <HomeLayout>
        <Head title="ClipperDesk features for the complete salon day" />
        <section class="cd-public-section cd-family-hero">
            <PublicContainer class="cd-family-hero-grid">
                <div class="cd-family-hero-copy">
                    <p class="cd-eyebrow">Product</p>
                    <h1 class="mt-5 max-w-4xl font-display text-[clamp(3rem,7vw,5.3rem)] font-semibold leading-[0.98] tracking-[-0.055em] text-balance">One operating system for the work around every appointment.</h1>
                    <p class="mt-7 max-w-2xl text-lg leading-8">Explore the four connected jobs that carry a salon or barbershop from public demand through the day, client history and explainable numbers.</p>
                </div>
                <figure class="cd-family-hero-media">
                    <img class="cd-family-hero-image" :src="marketingFamilyVisuals.product.src" :alt="marketingFamilyVisuals.product.alt" width="1536" height="1024" fetchpriority="high" />
                    <figcaption class="cd-family-hero-caption">Built around a real working day, not a disconnected feature list.</figcaption>
                </figure>
            </PublicContainer>
        </section>

        <section class="cd-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="Four evidence-rich clusters" title="Follow the customer journey, not a software module list." />
                <div class="cd-visual-card-grid mt-12">
                    <article v-for="(feature, index) in features" :key="feature.slug" class="cd-visual-card">
                        <div class="cd-visual-card-media">
                            <img class="cd-visual-card-image" :src="visualFor(featureVisuals, feature.slug, marketingFamilyVisuals.product).src" :alt="visualFor(featureVisuals, feature.slug, marketingFamilyVisuals.product).alt" width="1536" height="1024" loading="lazy" />
                            <span class="cd-visual-card-index" aria-hidden="true">0{{ index + 1 }}</span>
                        </div>
                        <div class="cd-visual-card-body">
                            <p class="cd-eyebrow">{{ feature.label }}</p>
                            <h2 class="mt-4 text-2xl font-extrabold leading-tight text-[var(--text-strong)]">{{ feature.title }}</h2>
                            <p class="mt-4 flex-1 leading-7 text-[var(--text-muted)]">{{ feature.description }}</p>
                            <p class="mt-5 text-xs font-semibold text-[var(--text-muted)]">Evidence: {{ feature.requirements.join(', ') }}</p>
                            <Link :href="route('marketing.features.show', feature.slug)" class="cd-arrow-link mt-5">
                                Explore {{ feature.label.toLowerCase() }}
                                <ArrowRightIcon class="size-4" aria-hidden="true" />
                            </Link>
                        </div>
                    </article>
                </div>
            </PublicContainer>
        </section>

        <ConversionBand title="See how the connected day fits your business." description="Start with a verified trial, configure the real operating rules, and review the client experience before publishing." context="features_hub_final" />
    </HomeLayout>
</template>
