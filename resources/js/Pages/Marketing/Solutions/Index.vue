<script setup>
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import SectionHeading from '@/Components/Marketing/SectionHeading.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ solutions: Array, seo: Object });
</script>

<template>
    <HomeLayout>
        <Head :title="seo.title" />
        <section class="cd-editorial-hero">
            <PublicContainer class="py-16 sm:py-24">
                <p class="cd-hero-kicker"><span aria-hidden="true">✦</span> Find your fit</p>
                <h1 class="mt-7 max-w-5xl font-display text-[clamp(3.5rem,7.4vw,7.5rem)] font-bold leading-[0.88] tracking-[-0.075em] text-white text-balance">Built around the way <span class="cd-hero-highlight">you</span> work.</h1>
                <p class="mt-8 max-w-3xl text-lg leading-8 text-slate-300 sm:text-xl">From an independent practitioner to a busy multi-provider operation, ClipperDesk connects the customer journey and the working day across beauty, wellness, fitness, recovery, health and pet care—without pretending every business runs the same way.</p>
            </PublicContainer>
        </section>
        <section class="cd-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="13 business types" title="Choose the day that looks most like yours" description="Each page focuses on real scheduling, capacity and client-service pressure—not a keyword-swapped feature list." />
                <div class="cd-industry-grid mt-12">
                    <article v-for="(solution, index) in solutions" :key="solution.slug" class="cd-industry-card" :class="{ 'cd-industry-card-featured': index < 2 }" :data-accent="solution.accent">
                        <Link :href="route('marketing.solutions.show', solution.slug)" class="group flex h-full flex-col" :aria-label="`Explore ClipperDesk for ${solution.label}`">
                            <div class="cd-industry-card-media">
                                <img :src="solution.image" :alt="solution.image_alt" width="1400" height="933" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.035]" :loading="index < 2 ? 'eager' : 'lazy'">
                                <span class="cd-industry-card-index" aria-hidden="true">{{ String(index + 1).padStart(2, '0') }}</span>
                            </div>
                            <div class="flex grow flex-col p-6 sm:p-7">
                                <p class="cd-eyebrow">{{ solution.label }}</p>
                                <h2 class="mt-4 text-2xl font-extrabold leading-tight text-[var(--text-strong)]">{{ solution.title }}</h2>
                                <p class="mt-4 grow leading-7 text-[var(--text-muted)]">{{ solution.description }}</p>
                                <span class="mt-6 inline-flex min-h-11 items-center gap-2 font-extrabold text-[var(--brand-primary)]">See how it fits <ArrowRightIcon class="size-4 transition-transform group-hover:translate-x-1" aria-hidden="true" /></span>
                            </div>
                        </Link>
                    </article>
                </div>
            </PublicContainer>
        </section>
        <ConversionBand title="Your business is personal. Your operating system should feel that way." description="Start a verified trial and shape the services, staff, spaces and policies around the way your team actually works." context="solutions_hub_final" />
    </HomeLayout>
</template>
