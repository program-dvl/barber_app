<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import SectionHeading from '@/Components/Marketing/SectionHeading.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { featureVisuals, marketingFamilyVisuals, visualFor } from '@/Support/marketingVisuals';
import { CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ feature: Object, related: Array, seo: Object });
</script>

<template>
    <HomeLayout>
        <Head :title="seo.title" />
        <section class="cd-public-section cd-family-hero" data-tone="cyan">
            <PublicContainer>
                <Breadcrumbs class="text-white/70" :items="[
                    { label: 'Home', href: route('marketing.home') },
                    { label: 'Features', href: route('marketing.features') },
                    { label: feature.label },
                ]" />
                <div class="cd-family-hero-grid mt-10">
                    <div class="cd-family-hero-copy">
                        <p class="cd-eyebrow">{{ feature.label }}</p>
                        <h1 class="mt-5 max-w-4xl font-display text-[clamp(3rem,7vw,5.25rem)] font-semibold leading-[0.98] tracking-[-0.055em] text-balance">{{ feature.title }}</h1>
                        <p class="mt-7 max-w-3xl text-lg leading-8 sm:text-xl">{{ feature.description }}</p>
                    </div>
                    <figure class="cd-family-hero-media">
                        <img class="cd-family-hero-image" :src="visualFor(featureVisuals, feature.slug, marketingFamilyVisuals.product).src" :alt="visualFor(featureVisuals, feature.slug, marketingFamilyVisuals.product).alt" width="1536" height="1024" fetchpriority="high" />
                        <figcaption class="cd-family-hero-caption">One part of the day, connected to everything around it.</figcaption>
                    </figure>
                </div>
            </PublicContainer>
        </section>

        <section class="-mt-8 px-4 pb-4 sm:-mt-12">
            <div class="cd-answer-panel relative z-10 mx-auto max-w-4xl">
                <p class="cd-eyebrow">In plain language</p>
                <p class="mt-4 text-lg leading-8 text-[var(--text-default)]"><strong class="text-[var(--text-strong)]">What it means:</strong> {{ feature.definition }}</p>
            </div>
        </section>

        <section class="cd-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="How it works" title="A connected workflow with explicit guardrails" />
                <ol class="cd-story-list mt-12">
                    <li v-for="(step, index) in feature.workflow" :key="step.title" class="cd-story-row">
                        <span class="cd-story-number" aria-hidden="true">0{{ index + 1 }}</span>
                        <h2 class="font-display text-2xl font-bold leading-tight text-[var(--text-strong)]">{{ step.title }}</h2>
                        <p class="leading-8 text-[var(--text-muted)]">{{ step.body }}</p>
                    </li>
                </ol>
            </PublicContainer>
        </section>

        <section class="cd-public-section cd-dark-proof">
            <PublicContainer class="grid gap-12 lg:grid-cols-2">
                <div>
                    <h2 class="font-display text-4xl leading-tight">Verified product evidence</h2>
                    <ul class="mt-7 space-y-4">
                        <li v-for="item in feature.proof" :key="item" class="flex gap-3 leading-7 text-white/82">
                            <CheckCircleIcon class="mt-1 size-5 shrink-0 text-[var(--brand-accent-soft)]" aria-hidden="true" />
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                    <p class="mt-7 text-sm font-semibold text-white/60">Requirement evidence: {{ feature.requirements.join(', ') }}</p>
                </div>
                <div>
                    <h2 class="font-display text-4xl leading-tight">Important limits</h2>
                    <ul class="mt-7 space-y-4">
                        <li v-for="item in feature.limitations" :key="item" class="flex gap-3 leading-7 text-white/82">
                            <ExclamationTriangleIcon class="mt-1 size-5 shrink-0 text-[var(--brand-accent-soft)]" aria-hidden="true" />
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </div>
            </PublicContainer>
        </section>

        <section class="cd-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="Continue the workflow" title="Explore what connects next" />
                <div class="cd-visual-card-grid mt-10">
                    <article v-for="item in related" :key="item.slug" class="cd-visual-card">
                        <div class="cd-visual-card-media"><img class="cd-visual-card-image" :src="visualFor(featureVisuals, item.slug, marketingFamilyVisuals.product).src" :alt="visualFor(featureVisuals, item.slug, marketingFamilyVisuals.product).alt" width="1536" height="1024" loading="lazy" /></div>
                        <div class="cd-visual-card-body min-h-0">
                            <p class="cd-eyebrow">{{ item.label }}</p>
                            <h2 class="mt-4 text-xl font-extrabold text-[var(--text-strong)]">{{ item.title }}</h2>
                            <Link :href="route('marketing.features.show', item.slug)" class="cd-arrow-link mt-5">Read this feature</Link>
                        </div>
                    </article>
                </div>
            </PublicContainer>
        </section>

        <ConversionBand :title="`Put ${feature.label.toLowerCase()} in the same operating day.`" description="Start with a verified trial and review the rules before the Business publishes its booking experience." :context="`feature_${feature.slug}_final`" />
    </HomeLayout>
</template>
