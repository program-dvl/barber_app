<script setup>
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import MarketingCard from '@/Components/Marketing/MarketingCard.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import SectionHeading from '@/Components/Marketing/SectionHeading.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ features: Array });
</script>

<template>
    <HomeLayout>
        <Head title="Good Hours features for the complete salon day" />
        <section class="gh-public-section border-b border-[var(--border-subtle)]">
            <PublicContainer>
                <p class="gh-eyebrow">Product</p>
                <h1 class="mt-5 max-w-4xl font-display text-[clamp(3rem,7vw,5.3rem)] font-semibold leading-[0.98] tracking-[-0.05em] text-[var(--text-strong)] text-balance">One operating system for the work around every appointment.</h1>
                <p class="mt-7 max-w-2xl text-lg leading-8 text-[var(--text-muted)]">Explore the four connected jobs that carry a salon or barbershop from public demand through the day, client history and explainable numbers.</p>
            </PublicContainer>
        </section>

        <section class="gh-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="Four evidence-rich clusters" title="Follow the customer journey, not a software module list." />
                <div class="mt-12 grid gap-5 md:grid-cols-2">
                    <MarketingCard v-for="feature in features" :key="feature.slug" class="flex flex-col">
                        <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-[var(--action-primary)]">{{ feature.label }}</p>
                        <h2 class="mt-4 text-2xl font-extrabold leading-tight text-[var(--text-strong)]">{{ feature.title }}</h2>
                        <p class="mt-4 flex-1 leading-7 text-[var(--text-muted)]">{{ feature.description }}</p>
                        <p class="mt-5 text-xs font-semibold text-[var(--text-muted)]">Evidence: {{ feature.requirements.join(', ') }}</p>
                        <Link :href="route('marketing.features.show', feature.slug)" class="mt-6 inline-flex min-h-11 items-center gap-2 rounded-lg font-extrabold text-[var(--brand-pine)] underline-offset-4 hover:underline">
                            Explore {{ feature.label.toLowerCase() }}
                            <ArrowRightIcon class="size-4" aria-hidden="true" />
                        </Link>
                    </MarketingCard>
                </div>
            </PublicContainer>
        </section>

        <ConversionBand title="See how the connected day fits your business." description="Start with a verified trial, configure the real operating rules, and review the client experience before publishing." context="features_hub_final" />
    </HomeLayout>
</template>
