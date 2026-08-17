<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import MarketingCard from '@/Components/Marketing/MarketingCard.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import SectionHeading from '@/Components/Marketing/SectionHeading.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ solution: Object, features: Array });
</script>

<template>
    <HomeLayout>
        <Head :title="solution.title" />
        <section class="gh-public-section border-b border-[var(--border-subtle)]">
            <PublicContainer>
                <Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Solutions', href: route('marketing.solutions') }, { label: solution.label }]" />
                <p class="gh-eyebrow mt-10">{{ solution.label }}</p>
                <h1 class="mt-5 max-w-4xl font-display text-[clamp(3rem,7vw,5.3rem)] font-semibold leading-[0.98] tracking-[-0.05em] text-[var(--text-strong)] text-balance">{{ solution.title }}</h1>
                <p class="mt-7 max-w-3xl text-lg leading-8 text-[var(--text-muted)] sm:text-xl">{{ solution.description }}</p>
                <p class="mt-8 max-w-3xl rounded-[var(--radius-lg)] border-l-4 border-[var(--action-primary)] bg-[var(--surface-raised)] p-6 leading-7"><strong class="text-[var(--text-strong)]">Where it fits:</strong> {{ solution.fit }}</p>
            </PublicContainer>
        </section>
        <section class="gh-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="What makes this day different" title="Specific operational pressure, not a swapped industry label" />
                <div class="mt-12 grid gap-5 lg:grid-cols-3">
                    <MarketingCard v-for="challenge in solution.challenges" :key="challenge.title">
                        <h2 class="text-xl font-extrabold text-[var(--text-strong)]">{{ challenge.title }}</h2>
                        <p class="mt-4 leading-7 text-[var(--text-muted)]">{{ challenge.body }}</p>
                    </MarketingCard>
                </div>
            </PublicContainer>
        </section>
        <section class="gh-public-section bg-[var(--brand-pine)] text-white">
            <PublicContainer class="grid gap-12 lg:grid-cols-[1fr_0.75fr]">
                <div>
                    <h2 class="font-display text-4xl leading-tight">A representative working loop</h2>
                    <ol class="mt-8 space-y-4">
                        <li v-for="(step, index) in solution.day" :key="step" class="flex gap-4 rounded-xl border border-white/15 bg-white/7 p-5 leading-7 text-white/82"><span class="font-display text-2xl text-[var(--brand-apricot)]">0{{ index + 1 }}</span><span>{{ step }}</span></li>
                    </ol>
                </div>
                <div>
                    <h2 class="font-display text-4xl leading-tight">Honest boundaries</h2>
                    <ul class="mt-8 space-y-4">
                        <li v-for="item in solution.limits" :key="item" class="flex gap-3 leading-7 text-white/82"><ExclamationTriangleIcon class="mt-1 size-5 shrink-0 text-[var(--brand-apricot)]" aria-hidden="true" /><span>{{ item }}</span></li>
                    </ul>
                    <p class="mt-8 text-sm font-semibold text-white/60">Requirement evidence: {{ solution.requirements.join(', ') }}</p>
                </div>
            </PublicContainer>
        </section>
        <section class="gh-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="Relevant product depth" title="Explore the capabilities behind this workflow" />
                <div class="mt-10 grid gap-4 md:grid-cols-3">
                    <MarketingCard v-for="feature in features" :key="feature.slug">
                        <CheckCircleIcon class="size-6 text-[var(--status-success)]" aria-hidden="true" />
                        <h2 class="mt-4 text-lg font-extrabold text-[var(--text-strong)]">{{ feature.title }}</h2>
                        <Link :href="route('marketing.features.show', feature.slug)" class="mt-5 inline-flex min-h-11 items-center rounded-lg text-sm font-extrabold text-[var(--brand-pine)] underline-offset-4 hover:underline">Explore {{ feature.label.toLowerCase() }}</Link>
                    </MarketingCard>
                </div>
            </PublicContainer>
        </section>
        <ConversionBand :title="`Give your ${solution.label.toLowerCase()} one connected operating day.`" description="Start with a verified trial and review the booking and operational rules before publishing." :context="`solution_${solution.slug}_final`" />
    </HomeLayout>
</template>
