<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import MarketingCard from '@/Components/Marketing/MarketingCard.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import SectionHeading from '@/Components/Marketing/SectionHeading.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ useCase: Object, feature: Object, solution: Object });
</script>

<template>
    <HomeLayout>
        <Head :title="useCase.title" />
        <section class="gh-public-section border-b border-[var(--border-subtle)]">
            <PublicContainer>
                <Breadcrumbs :items="[
                    { label: 'Home', href: route('marketing.home') },
                    { label: 'Use cases', href: route('marketing.use-cases') },
                    { label: useCase.label },
                ]" />
                <p class="gh-eyebrow mt-10">{{ useCase.label }}</p>
                <h1 class="mt-5 max-w-4xl font-display text-[clamp(3rem,7vw,5.25rem)] font-semibold leading-[0.98] tracking-[-0.05em] text-[var(--text-strong)] text-balance">{{ useCase.title }}</h1>
                <p class="mt-7 max-w-3xl text-lg leading-8 text-[var(--text-muted)] sm:text-xl">{{ useCase.description }}</p>
                <div class="mt-10 max-w-4xl rounded-[var(--radius-xl)] border border-[var(--border-strong)] bg-[var(--brand-cream)] p-6 sm:p-8">
                    <h2 class="text-lg font-extrabold text-[var(--text-strong)]">The direct answer</h2>
                    <p class="mt-3 leading-8 text-[var(--text-default)]">{{ useCase.answer }}</p>
                </div>
            </PublicContainer>
        </section>

        <section class="gh-public-section">
            <PublicContainer class="grid gap-12 lg:grid-cols-[0.8fr_1.2fr]">
                <div>
                    <h2 class="font-display text-4xl font-semibold leading-tight text-[var(--text-strong)]">What the problem looks like</h2>
                    <ul class="mt-7 space-y-4">
                        <li v-for="symptom in useCase.symptoms" :key="symptom" class="flex gap-3 leading-7 text-[var(--text-muted)]">
                            <ExclamationTriangleIcon class="mt-1 size-5 shrink-0 text-[var(--brand-rust)]" aria-hidden="true" />
                            <span>{{ symptom }}</span>
                        </li>
                    </ul>
                </div>
                <div>
                    <h2 class="font-display text-4xl font-semibold leading-tight text-[var(--text-strong)]">A practical operating approach</h2>
                    <ol class="mt-7 space-y-5">
                        <li v-for="(step, index) in useCase.practice" :key="step.title" class="grid grid-cols-[2.5rem_1fr] gap-4">
                            <span class="flex size-10 items-center justify-center rounded-full bg-[var(--brand-pine)] font-extrabold text-white" aria-hidden="true">{{ index + 1 }}</span>
                            <div><h3 class="font-extrabold text-[var(--text-strong)]">{{ step.title }}</h3><p class="mt-2 leading-7 text-[var(--text-muted)]">{{ step.body }}</p></div>
                        </li>
                    </ol>
                </div>
            </PublicContainer>
        </section>

        <section class="gh-public-section bg-[var(--brand-pine)] text-white">
            <PublicContainer class="grid gap-12 lg:grid-cols-2">
                <div>
                    <h2 class="font-display text-4xl leading-tight">How Good Hours participates</h2>
                    <ol class="mt-7 space-y-4">
                        <li v-for="(step, index) in useCase.product_steps" :key="step" class="flex gap-3 leading-7 text-white/85">
                            <CheckCircleIcon class="mt-1 size-5 shrink-0 text-[var(--brand-apricot)]" aria-hidden="true" />
                            <span><span class="sr-only">Step {{ index + 1 }}: </span>{{ step }}</span>
                        </li>
                    </ol>
                    <p class="mt-7 text-sm font-semibold text-white/60">Requirement evidence: {{ useCase.requirements.join(', ') }}</p>
                </div>
                <div class="rounded-[var(--radius-xl)] border border-white/20 bg-white/8 p-6 sm:p-8">
                    <h2 class="font-display text-4xl leading-tight">Limits to keep in view</h2>
                    <ul class="mt-7 space-y-4">
                        <li v-for="item in useCase.limitations" :key="item" class="flex gap-3 leading-7 text-white/85">
                            <ExclamationTriangleIcon class="mt-1 size-5 shrink-0 text-[var(--brand-apricot)]" aria-hidden="true" />
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </div>
            </PublicContainer>
        </section>

        <section class="gh-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="Continue evaluating" title="See the supporting product workflow and business fit" />
                <div class="mt-10 grid gap-5 md:grid-cols-2">
                    <MarketingCard>
                        <p class="gh-eyebrow">Supporting feature</p>
                        <h2 class="mt-4 text-xl font-extrabold text-[var(--text-strong)]">{{ feature.title }}</h2>
                        <Link :href="route('marketing.features.show', feature.slug)" class="mt-5 inline-flex min-h-11 items-center font-extrabold text-[var(--brand-pine)] underline-offset-4 hover:underline">Explore {{ feature.label.toLowerCase() }}</Link>
                    </MarketingCard>
                    <MarketingCard>
                        <p class="gh-eyebrow">Relevant fit</p>
                        <h2 class="mt-4 text-xl font-extrabold text-[var(--text-strong)]">{{ solution.title }}</h2>
                        <Link :href="route('marketing.solutions.show', solution.slug)" class="mt-5 inline-flex min-h-11 items-center font-extrabold text-[var(--brand-pine)] underline-offset-4 hover:underline">See the {{ solution.label.toLowerCase() }} workflow</Link>
                    </MarketingCard>
                </div>
            </PublicContainer>
        </section>

        <ConversionBand :title="`Put this ${useCase.label.toLowerCase()} workflow into practice.`" description="Start with a verified trial and configure the operating rules before publishing the Business booking experience." :context="`use_case_${useCase.slug}_final`" />
    </HomeLayout>
</template>
