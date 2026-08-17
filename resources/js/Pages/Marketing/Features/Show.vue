<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import MarketingCard from '@/Components/Marketing/MarketingCard.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import SectionHeading from '@/Components/Marketing/SectionHeading.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ feature: Object, related: Array });
</script>

<template>
    <HomeLayout>
        <Head :title="feature.title" />
        <section class="gh-public-section border-b border-[var(--border-subtle)]">
            <PublicContainer>
                <Breadcrumbs :items="[
                    { label: 'Home', href: route('marketing.home') },
                    { label: 'Features', href: route('marketing.features') },
                    { label: feature.label },
                ]" />
                <p class="gh-eyebrow mt-10">{{ feature.label }}</p>
                <h1 class="mt-5 max-w-4xl font-display text-[clamp(3rem,7vw,5.25rem)] font-semibold leading-[0.98] tracking-[-0.05em] text-[var(--text-strong)] text-balance">{{ feature.title }}</h1>
                <p class="mt-7 max-w-3xl text-lg leading-8 text-[var(--text-muted)] sm:text-xl">{{ feature.description }}</p>
                <p class="mt-8 max-w-3xl rounded-[var(--radius-lg)] border-l-4 border-[var(--action-primary)] bg-[var(--surface-raised)] p-6 leading-7 text-[var(--text-default)]"><strong class="text-[var(--text-strong)]">What it means:</strong> {{ feature.definition }}</p>
            </PublicContainer>
        </section>

        <section class="gh-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="How it works" title="A connected workflow with explicit guardrails" />
                <ol class="mt-12 grid gap-5 md:grid-cols-2">
                    <li v-for="(step, index) in feature.workflow" :key="step.title" class="gh-marketing-card">
                        <span class="font-display text-3xl text-[var(--action-primary)]" aria-hidden="true">0{{ index + 1 }}</span>
                        <h2 class="mt-5 text-xl font-extrabold text-[var(--text-strong)]">{{ step.title }}</h2>
                        <p class="mt-3 leading-7 text-[var(--text-muted)]">{{ step.body }}</p>
                    </li>
                </ol>
            </PublicContainer>
        </section>

        <section class="gh-public-section bg-[var(--brand-pine)] text-white">
            <PublicContainer class="grid gap-12 lg:grid-cols-2">
                <div>
                    <h2 class="font-display text-4xl leading-tight">Verified product evidence</h2>
                    <ul class="mt-7 space-y-4">
                        <li v-for="item in feature.proof" :key="item" class="flex gap-3 leading-7 text-white/82">
                            <CheckCircleIcon class="mt-1 size-5 shrink-0 text-[var(--brand-apricot)]" aria-hidden="true" />
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                    <p class="mt-7 text-sm font-semibold text-white/60">Requirement evidence: {{ feature.requirements.join(', ') }}</p>
                </div>
                <div>
                    <h2 class="font-display text-4xl leading-tight">Important limits</h2>
                    <ul class="mt-7 space-y-4">
                        <li v-for="item in feature.limitations" :key="item" class="flex gap-3 leading-7 text-white/82">
                            <ExclamationTriangleIcon class="mt-1 size-5 shrink-0 text-[var(--brand-apricot)]" aria-hidden="true" />
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </div>
            </PublicContainer>
        </section>

        <section class="gh-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="Continue the workflow" title="Explore what connects next" />
                <div class="mt-10 grid gap-4 md:grid-cols-2">
                    <MarketingCard v-for="item in related" :key="item.slug">
                        <p class="gh-eyebrow">{{ item.label }}</p>
                        <h2 class="mt-4 text-xl font-extrabold text-[var(--text-strong)]">{{ item.title }}</h2>
                        <Link :href="route('marketing.features.show', item.slug)" class="mt-5 inline-flex min-h-11 items-center rounded-lg font-extrabold text-[var(--brand-pine)] underline-offset-4 hover:underline">Read this feature</Link>
                    </MarketingCard>
                </div>
            </PublicContainer>
        </section>

        <ConversionBand :title="`Put ${feature.label.toLowerCase()} in the same operating day.`" description="Start with a verified trial and review the rules before the Business publishes its booking experience." :context="`feature_${feature.slug}_final`" />
    </HomeLayout>
</template>
