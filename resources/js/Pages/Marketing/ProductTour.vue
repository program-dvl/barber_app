<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import ConversionBand from '@/Components/Marketing/ConversionBand.vue';
import HelpScreenshot from '@/Components/Help/HelpScreenshot.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import PublicCta from '@/Components/Marketing/PublicCta.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import '../../../css/help-center.css';

defineProps({ steps: Array, seo: Object });
</script>

<template>
    <HomeLayout>
        <Head :title="seo.title" />
        <section class="cd-public-section cd-family-hero" data-tone="cyan">
            <PublicContainer>
                <Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Product walkthrough' }]" />
                <p class="cd-eyebrow mt-9">Product walkthrough</p>
                <h1 class="mt-5 max-w-4xl font-display cd-public-title font-semibold leading-tight tracking-tight">Follow a visit from booking to checkout.</h1>
                <p class="mt-7 max-w-3xl text-lg leading-8">Explore the real screens behind online booking, the working calendar, walk-ins, client history and recorded payments. Built for salons and barbershops, and shared appointment workflows across beauty, wellness, fitness, health and pet services.</p>
                <div class="mt-8 flex flex-wrap gap-3"><PublicCta context="product_tour_hero" /><Link :href="route('marketing.pricing')" class="cd-button cd-button-hero-secondary">Check pricing</Link></div>
            </PublicContainer>
        </section>
        <nav aria-label="Walkthrough steps" class="border-b border-[var(--border-subtle)] bg-white py-5">
            <PublicContainer class="flex flex-wrap gap-x-6 gap-y-3"><a v-for="(step, index) in steps" :key="step.id" :href="`#${step.id}`" class="inline-flex min-h-11 items-center font-semibold text-[var(--brand-primary)] underline underline-offset-4">{{ index + 1 }}. {{ step.id === 'clients' ? 'Client history' : step.id.charAt(0).toUpperCase() + step.id.slice(1) }}</a></PublicContainer>
        </nav>
        <section v-for="(step, index) in steps" :id="step.id" :key="step.id" class="cd-public-section scroll-mt-28 border-b border-[var(--border-subtle)]">
            <PublicContainer class="grid items-start gap-10 lg:grid-cols-[0.8fr_1.2fr]">
                <div>
                    <p class="cd-eyebrow">Step {{ index + 1 }}</p>
                    <h2 class="mt-4 font-display text-3xl font-semibold text-[var(--text-strong)]">{{ step.title }}</h2>
                    <p class="mt-5 leading-8 text-[var(--text-muted)]">{{ step.body }}</p>
                    <h3 class="mt-7 text-lg font-bold text-[var(--text-strong)]">What to check in your trial</h3>
                    <p class="mt-3 leading-8 text-[var(--text-muted)]">{{ step.check }}</p>
                    <div class="mt-6 flex flex-wrap gap-3"><Link :href="route('marketing.features.show', step.feature)" class="cd-button cd-button-secondary">Explore this feature</Link><Link :href="route('help.article', step.help)" class="cd-button cd-button-quiet">Read the step-by-step help</Link></div>
                </div>
                <div class="min-w-0"><HelpScreenshot v-for="media in step.screenshots" :key="media.key" :media="media" /></div>
            </PublicContainer>
        </section>
        <section class="cd-public-section"><PublicContainer><h2 class="font-display text-3xl font-semibold text-[var(--text-strong)]">Try the workflow for your business.</h2><p class="mt-5 max-w-3xl leading-8 text-[var(--text-muted)]">The screenshots use example businesses and sample data. Your services, hours, permissions and booking rules determine the choices your team and clients see. Specialist clinical records, payroll, memberships and automated marketing campaigns are not included.</p><div class="mt-6 flex flex-wrap gap-3"><Link :href="route('marketing.solutions')" class="cd-button cd-button-secondary">Find your business type</Link><Link :href="route('marketing.guides.show', 'daily-operations-checklist')" class="cd-button cd-button-quiet">Get the daily checklist</Link></div></PublicContainer></section>
        <ConversionBand title="Make the next day easier to run." description="Add your services and staff, preview booking and try a complete visit before publishing your page." context="product_tour_final" />
    </HomeLayout>
</template>
