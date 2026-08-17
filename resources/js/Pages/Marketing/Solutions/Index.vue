<script setup>
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import MarketingCard from '@/Components/Marketing/MarketingCard.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import SectionHeading from '@/Components/Marketing/SectionHeading.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ solutions: Array });
</script>

<template>
    <HomeLayout>
        <Head title="Good Hours for salons, barbershops and independent professionals" />
        <section class="gh-public-section border-b border-[var(--border-subtle)]">
            <PublicContainer>
                <p class="gh-eyebrow">Solutions</p>
                <h1 class="mt-5 max-w-4xl font-display text-[clamp(3rem,7vw,5.3rem)] font-semibold leading-[0.98] tracking-[-0.05em] text-[var(--text-strong)] text-balance">Different service businesses. Different pressure points.</h1>
                <p class="mt-7 max-w-3xl text-lg leading-8 text-[var(--text-muted)]">Good Hours uses one connected operating foundation, then explains the workflows that matter to each supported business type—without pretending they all run the same day.</p>
            </PublicContainer>
        </section>
        <section class="gh-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="Supported business types" title="Choose the operating pattern closest to yours" />
                <div class="mt-12 grid gap-5 md:grid-cols-2">
                    <MarketingCard v-for="solution in solutions" :key="solution.slug" class="flex flex-col">
                        <p class="gh-eyebrow">{{ solution.label }}</p>
                        <h2 class="mt-4 text-2xl font-extrabold leading-tight text-[var(--text-strong)]">{{ solution.title }}</h2>
                        <p class="mt-4 flex-1 leading-7 text-[var(--text-muted)]">{{ solution.description }}</p>
                        <Link :href="route('marketing.solutions.show', solution.slug)" class="mt-6 inline-flex min-h-11 items-center gap-2 rounded-lg font-extrabold text-[var(--brand-pine)] underline-offset-4 hover:underline">Explore {{ solution.label.toLowerCase() }} <ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                    </MarketingCard>
                </div>
            </PublicContainer>
        </section>
        <ConversionBand title="Run the work your business actually has." description="Start with a verified trial and configure the services, staff, resources and policies that govern your day." context="solutions_hub_final" />
    </HomeLayout>
</template>
