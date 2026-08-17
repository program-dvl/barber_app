<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import MarketingCard from '@/Components/Marketing/MarketingCard.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import SectionHeading from '@/Components/Marketing/SectionHeading.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ useCases: Array });
</script>

<template>
    <HomeLayout>
        <Head title="Practical salon operating use cases" />
        <section class="gh-public-section border-b border-[var(--border-subtle)]">
            <PublicContainer>
                <Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Use cases' }]" />
                <p class="gh-eyebrow mt-10">Use cases</p>
                <h1 class="mt-5 max-w-4xl font-display text-[clamp(3rem,7vw,5.25rem)] font-semibold leading-[0.98] tracking-[-0.05em] text-[var(--text-strong)] text-balance">Start with the operating problem, not a feature list.</h1>
                <p class="mt-7 max-w-3xl text-lg leading-8 text-[var(--text-muted)] sm:text-xl">Use these practical guides to diagnose a recurring salon-day problem, improve the process and see where verified Good Hours behavior can help.</p>
            </PublicContainer>
        </section>

        <section class="gh-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="Four distinct jobs" title="Useful guidance, with the product limits beside it" description="Each page answers one problem and links to the exact supporting workflow—without promising a guaranteed business outcome." />
                <div class="mt-12 grid gap-5 md:grid-cols-2">
                    <MarketingCard v-for="item in useCases" :key="item.slug" class="flex flex-col">
                        <p class="gh-eyebrow">{{ item.label }}</p>
                        <h2 class="mt-5 text-2xl font-extrabold leading-tight text-[var(--text-strong)]">{{ item.title }}</h2>
                        <p class="mt-4 grow leading-7 text-[var(--text-muted)]">{{ item.description }}</p>
                        <Link :href="route('marketing.use-cases.show', item.slug)" class="mt-7 inline-flex min-h-11 items-center gap-2 rounded-lg font-extrabold text-[var(--brand-pine)] underline-offset-4 hover:underline">
                            Read the practical guide <ArrowRightIcon class="size-4" aria-hidden="true" />
                        </Link>
                    </MarketingCard>
                </div>
            </PublicContainer>
        </section>

        <ConversionBand title="Ready to connect the workflow?" description="Explore Good Hours with a verified trial, then configure the rules for your Business before publishing booking." context="use_cases_hub_final" />
    </HomeLayout>
</template>
