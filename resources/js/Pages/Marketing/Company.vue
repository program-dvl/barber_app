<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import PublicCta from '@/Components/Marketing/PublicCta.vue';
import SectionHeading from '@/Components/Marketing/SectionHeading.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { marketingFamilyVisuals } from '@/Support/marketingVisuals';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ seo: Object });
</script>

<template>
    <HomeLayout>
        <Head :title="seo.title" />
        <section class="cd-public-section cd-family-hero" data-tone="coral">
            <PublicContainer>
                <Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Company' }]" />
                <div class="cd-family-hero-grid mt-10">
                    <div class="cd-family-hero-copy">
                        <p class="cd-eyebrow">Company</p>
                        <h1 class="mt-5 max-w-4xl font-display cd-public-title font-semibold leading-[0.98] tracking-[-0.055em] text-balance">About ClipperDesk</h1>
                        <p class="mt-7 max-w-3xl text-lg leading-8 sm:text-xl">Manage appointments, client records, team schedules and checkout for beauty, wellness, fitness, health and pet-care businesses.</p>
                    </div>
                    <figure class="cd-family-hero-media">
                        <img class="cd-family-hero-image" :src="marketingFamilyVisuals.company.src" :alt="marketingFamilyVisuals.company.alt" width="1536" height="1024" fetchpriority="high" />
                        <figcaption class="cd-family-hero-caption">Built around the people who make each working day happen.</figcaption>
                    </figure>
                </div>
            </PublicContainer>
        </section>

        <section class="cd-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="What guides the product" title="Our approach" />
                <div class="cd-value-columns mt-12">
                    <article class="cd-value-column"><p class="cd-eyebrow">01 · Connected</p><h2 class="mt-4 text-2xl font-extrabold text-[var(--text-strong)]">One connected day</h2><p class="mt-4 leading-8 text-[var(--text-muted)]">Availability, appointment, client and Sale records carry the work instead of forcing the front desk to reconcile disconnected tools.</p></article>
                    <article class="cd-value-column"><p class="cd-eyebrow">02 · Considered</p><h2 class="mt-4 text-2xl font-extrabold text-[var(--text-strong)]">Rules before shortcuts</h2><p class="mt-4 leading-8 text-[var(--text-muted)]">Business records stay separate, access follows each role, and times reflect the location. Financial corrections keep their history.</p></article>
                    <article class="cd-value-column"><p class="cd-eyebrow">03 · Candid</p><h2 class="mt-4 text-2xl font-extrabold text-[var(--text-strong)]">Claims need proof</h2><p class="mt-4 leading-8 text-[var(--text-muted)]">The public site names provider, legal and operational limits instead of using customer logos, reviews, awards or certifications that have not been earned.</p></article>
                </div>
            </PublicContainer>
        </section>

        <section class="cd-public-section cd-dark-proof">
            <PublicContainer class="grid gap-10 lg:grid-cols-[1.2fr_0.8fr]">
                <div>
                    <p class="cd-dark-eyebrow">Clarity as a company value</p>
                    <h2 class="mt-4 font-display text-3xl font-semibold text-white">Operator and identity status</h2>
                    <p class="mt-5 max-w-2xl leading-8 text-white/75">ClipperDesk is the selected product identity. Trademark clearance, the production domain and the final legal operator/contact details are not yet approved evidence. Public launch requires approval of these details.</p>
                </div>
                <div class="cd-dark-proof-panel">
                    <h2 class="text-xl font-extrabold text-white">Review the current evidence</h2>
                    <div class="mt-5 flex flex-col gap-3"><Link :href="route('marketing.security')" class="cd-button bg-white text-[var(--brand-primary)]">Security approach</Link><Link :href="route('marketing.features')" class="cd-button cd-button-hero-secondary">Product features</Link><PublicCta context="company_final" /></div>
                </div>
            </PublicContainer>
        </section>
    </HomeLayout>
</template>
