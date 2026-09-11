<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import SectionHeading from '@/Components/Marketing/SectionHeading.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { featureVisuals, marketingFamilyVisuals, solutionVisuals, useCaseVisuals, visualFor } from '@/Support/marketingVisuals';
import { CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ useCase: Object, feature: Object, solution: Object });
</script>

<template>
    <HomeLayout>
        <Head :title="useCase.title" />
        <section class="cd-public-section cd-family-hero" data-tone="coral">
            <PublicContainer>
                <Breadcrumbs :items="[
                    { label: 'Home', href: route('marketing.home') },
                    { label: 'Use cases', href: route('marketing.use-cases') },
                    { label: useCase.label },
                ]" />
                <div class="cd-family-hero-grid mt-10">
                    <div class="cd-family-hero-copy">
                        <p class="cd-eyebrow">{{ useCase.label }}</p>
                        <h1 class="mt-5 max-w-4xl font-display text-[clamp(3rem,7vw,5.25rem)] font-semibold leading-[0.98] tracking-[-0.055em] text-balance">{{ useCase.title }}</h1>
                        <p class="mt-7 max-w-3xl text-lg leading-8 sm:text-xl">{{ useCase.description }}</p>
                    </div>
                    <figure class="cd-family-hero-media">
                        <img class="cd-family-hero-image" :src="visualFor(useCaseVisuals, useCase.slug, marketingFamilyVisuals.useCases).src" :alt="visualFor(useCaseVisuals, useCase.slug, marketingFamilyVisuals.useCases).alt" width="1536" height="1024" fetchpriority="high" />
                        <figcaption class="cd-family-hero-caption">A better day starts with the real operating problem.</figcaption>
                    </figure>
                </div>
            </PublicContainer>
        </section>

        <section class="-mt-8 px-4 pb-4 sm:-mt-12">
            <div class="cd-answer-panel relative z-10 mx-auto max-w-4xl">
                <p class="cd-eyebrow">The direct answer</p>
                <p class="mt-4 text-lg leading-8 text-[var(--text-default)]">{{ useCase.answer }}</p>
            </div>
        </section>

        <section class="cd-public-section">
            <PublicContainer class="grid gap-12 lg:grid-cols-[0.8fr_1.2fr]">
                <div>
                    <h2 class="font-display text-4xl font-semibold leading-tight text-[var(--text-strong)]">What the problem looks like</h2>
                    <ul class="mt-7 space-y-4">
                        <li v-for="symptom in useCase.symptoms" :key="symptom" class="flex gap-3 leading-7 text-[var(--text-muted)]">
                            <ExclamationTriangleIcon class="mt-1 size-5 shrink-0 text-[var(--status-warning)]" aria-hidden="true" />
                            <span>{{ symptom }}</span>
                        </li>
                    </ul>
                </div>
                <div>
                    <h2 class="font-display text-4xl font-semibold leading-tight text-[var(--text-strong)]">A practical operating approach</h2>
                    <ol class="cd-story-list mt-7">
                        <li v-for="(step, index) in useCase.practice" :key="step.title" class="grid grid-cols-[3rem_1fr] gap-4 border-b border-[var(--border-default)] py-5">
                            <span class="font-display text-2xl font-semibold text-[var(--brand-secondary)]" aria-hidden="true">0{{ index + 1 }}</span>
                            <div><h3 class="font-extrabold text-[var(--text-strong)]">{{ step.title }}</h3><p class="mt-2 leading-7 text-[var(--text-muted)]">{{ step.body }}</p></div>
                        </li>
                    </ol>
                </div>
            </PublicContainer>
        </section>

        <section class="cd-public-section cd-dark-proof">
            <PublicContainer class="grid gap-12 lg:grid-cols-2">
                <div>
                    <h2 class="font-display text-4xl leading-tight">How ClipperDesk participates</h2>
                    <ol class="mt-7 space-y-4">
                        <li v-for="(step, index) in useCase.product_steps" :key="step" class="flex gap-3 leading-7 text-white/85">
                            <CheckCircleIcon class="mt-1 size-5 shrink-0 text-[var(--brand-accent-soft)]" aria-hidden="true" />
                            <span><span class="sr-only">Step {{ index + 1 }}: </span>{{ step }}</span>
                        </li>
                    </ol>
                    <p class="mt-7 text-sm font-semibold text-white/60">Requirement evidence: {{ useCase.requirements.join(', ') }}</p>
                </div>
                <div class="rounded-[var(--radius-xl)] border border-white/20 bg-white/8 p-6 sm:p-8">
                    <h2 class="font-display text-4xl leading-tight">Limits to keep in view</h2>
                    <ul class="mt-7 space-y-4">
                        <li v-for="item in useCase.limitations" :key="item" class="flex gap-3 leading-7 text-white/85">
                            <ExclamationTriangleIcon class="mt-1 size-5 shrink-0 text-[var(--brand-accent-soft)]" aria-hidden="true" />
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </div>
            </PublicContainer>
        </section>

        <section class="cd-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="Continue evaluating" title="See the supporting product workflow and business fit" />
                <div class="cd-visual-card-grid mt-10">
                    <article class="cd-visual-card">
                        <div class="cd-visual-card-media"><img class="cd-visual-card-image" :src="visualFor(featureVisuals, feature.slug, marketingFamilyVisuals.product).src" :alt="visualFor(featureVisuals, feature.slug, marketingFamilyVisuals.product).alt" width="1536" height="1024" loading="lazy" /></div>
                        <div class="cd-visual-card-body min-h-0"><p class="cd-eyebrow">Supporting feature</p><h2 class="mt-4 text-xl font-extrabold text-[var(--text-strong)]">{{ feature.title }}</h2><Link :href="route('marketing.features.show', feature.slug)" class="cd-arrow-link mt-5">Explore {{ feature.label.toLowerCase() }}</Link></div>
                    </article>
                    <article class="cd-visual-card">
                        <div class="cd-visual-card-media"><img class="cd-visual-card-image" :src="visualFor(solutionVisuals, solution.slug, marketingFamilyVisuals.useCases).src" :alt="visualFor(solutionVisuals, solution.slug, marketingFamilyVisuals.useCases).alt" width="1536" height="1024" loading="lazy" /></div>
                        <div class="cd-visual-card-body min-h-0"><p class="cd-eyebrow">Relevant fit</p><h2 class="mt-4 text-xl font-extrabold text-[var(--text-strong)]">{{ solution.title }}</h2><Link :href="route('marketing.solutions.show', solution.slug)" class="cd-arrow-link mt-5">See the {{ solution.label.toLowerCase() }} workflow</Link></div>
                    </article>
                </div>
            </PublicContainer>
        </section>

        <ConversionBand :title="`Put this ${useCase.label.toLowerCase()} workflow into practice.`" description="Start with a verified trial and configure the operating rules before publishing the Business booking experience." :context="`use_case_${useCase.slug}_final`" />
    </HomeLayout>
</template>
