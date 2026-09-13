<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import MarketingCard from '@/Components/Marketing/MarketingCard.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import SectionHeading from '@/Components/Marketing/SectionHeading.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ solution: Object, features: Array, seo: Object });
</script>

<template>
    <HomeLayout>
        <Head :title="seo.title" />
        <section class="cd-solution-hero" :data-accent="solution.accent">
            <PublicContainer class="grid gap-10 py-12 sm:py-16 lg:grid-cols-[0.86fr_1.14fr] lg:items-center lg:gap-14 lg:py-20">
                <div>
                    <Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Solutions', href: route('marketing.solutions') }, { label: solution.label }]" />
                    <p class="cd-hero-kicker mt-9"><span aria-hidden="true">✦</span> ClipperDesk for {{ solution.label }}</p>
                    <h1 class="mt-6 font-display text-[clamp(3.25rem,6vw,6.2rem)] font-bold leading-[0.9] tracking-[-0.07em] text-white text-balance">{{ solution.title }}</h1>
                    <p class="mt-7 max-w-2xl text-lg leading-8 text-slate-300 sm:text-xl">{{ solution.description }}</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link :href="route('register')" class="cd-button cd-button-primary">Start your trial</Link>
                        <Link :href="route('marketing.pricing')" class="cd-button cd-button-hero-secondary">View pricing</Link>
                    </div>
                </div>
                <figure class="cd-solution-photo">
                    <img :src="solution.image" :alt="solution.image_alt" width="1400" height="933" class="h-full w-full object-cover" fetchpriority="high">
                </figure>
            </PublicContainer>
        </section>
        <section class="border-b border-[var(--border-subtle)] bg-[#f7f8fc] py-8">
            <PublicContainer><p class="mx-auto max-w-4xl text-center text-lg leading-8 text-[var(--text-default)]"><strong class="text-[var(--text-strong)]">Where it fits:</strong> {{ solution.fit }}</p></PublicContainer>
        </section>
        <section class="cd-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="Your working day" title="The pressure points this business knows well" />
                <div class="mt-12 grid gap-5 lg:grid-cols-3">
                    <MarketingCard v-for="(challenge, index) in solution.challenges" :key="challenge.title" class="cd-numbered-card">
                        <span class="cd-card-number" aria-hidden="true">{{ String(index + 1).padStart(2, '0') }}</span>
                        <h2 class="text-xl font-extrabold text-[var(--text-strong)]">{{ challenge.title }}</h2>
                        <p class="mt-4 leading-7 text-[var(--text-muted)]">{{ challenge.body }}</p>
                    </MarketingCard>
                </div>
            </PublicContainer>
        </section>
        <section class="cd-public-section cd-dark-section text-white">
            <PublicContainer class="grid gap-12 lg:grid-cols-[1.1fr_0.9fr]">
                <div>
                    <p class="cd-dark-eyebrow">A connected shift</p>
                    <h2 class="mt-4 font-display text-4xl font-semibold leading-tight sm:text-5xl">From the first booking to the final close.</h2>
                    <ol class="mt-8 space-y-4">
                        <li v-for="(step, index) in solution.day" :key="step" class="cd-dark-step"><span>{{ String(index + 1).padStart(2, '0') }}</span><p>{{ step }}</p></li>
                    </ol>
                </div>
                <div class="cd-boundary-card">
                    <p class="cd-dark-eyebrow">Good software is honest</p>
                    <h2 class="mt-4 font-display text-4xl font-semibold leading-tight">Clear boundaries.</h2>
                    <ul class="mt-8 space-y-4">
                        <li v-for="item in solution.limits" :key="item" class="flex gap-3 leading-7 text-white/80"><ExclamationTriangleIcon class="mt-1 size-5 shrink-0 text-yellow-300" aria-hidden="true" /><span>{{ item }}</span></li>
                    </ul>
                    <p class="mt-8 text-sm font-semibold text-white/60">Requirement evidence: {{ solution.requirements.join(', ') }}</p>
                </div>
            </PublicContainer>
        </section>
        <section class="cd-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="Relevant product depth" title="Explore the capabilities behind this workflow" />
                <div class="mt-10 grid gap-4 md:grid-cols-3">
                    <MarketingCard v-for="feature in features" :key="feature.slug">
                        <CheckCircleIcon class="size-6 text-[var(--status-success)]" aria-hidden="true" />
                        <h2 class="mt-4 text-lg font-extrabold text-[var(--text-strong)]">{{ feature.title }}</h2>
                        <Link :href="route('marketing.features.show', feature.slug)" class="mt-5 inline-flex min-h-11 items-center rounded-lg text-sm font-extrabold text-[var(--brand-primary)] underline-offset-4 hover:underline">Explore {{ feature.label.toLowerCase() }}</Link>
                    </MarketingCard>
                </div>
            </PublicContainer>
        </section>
        <ConversionBand title="Make the whole working day feel beautifully under control." description="Start with a verified trial and shape the booking and operating rules before publishing." :context="`solution_${solution.slug}_final`" />
    </HomeLayout>
</template>
