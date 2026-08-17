<script setup>
import Breadcrumbs from '@/Components/Marketing/Breadcrumbs.vue';
import MarketingCard from '@/Components/Marketing/MarketingCard.vue';
import PublicContainer from '@/Components/Marketing/PublicContainer.vue';
import PublicCta from '@/Components/Marketing/PublicCta.vue';
import SectionHeading from '@/Components/Marketing/SectionHeading.vue';
import HomeLayout from '@/Layouts/HomeLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <HomeLayout>
        <Head title="About Good Hours" />
        <section class="gh-public-section border-b border-[var(--border-subtle)]">
            <PublicContainer>
                <Breadcrumbs :items="[{ label: 'Home', href: route('marketing.home') }, { label: 'Company' }]" />
                <p class="gh-eyebrow mt-10">Company</p>
                <h1 class="mt-5 max-w-4xl font-display text-[clamp(3rem,7vw,5.25rem)] font-semibold leading-[0.98] tracking-[-0.05em] text-[var(--text-strong)] text-balance">Good Hours is being built for the whole working salon day.</h1>
                <p class="mt-7 max-w-3xl text-lg leading-8 text-[var(--text-muted)] sm:text-xl">It is a web-first operating system for independent salons, barbershops, stylists and small non-medical spas—from guided setup and booking through client history, checkout and explainable reporting.</p>
            </PublicContainer>
        </section>

        <section class="gh-public-section">
            <PublicContainer>
                <SectionHeading eyebrow="What guides the product" title="Calm operation, explicit rules and honest evidence" />
                <div class="mt-10 grid gap-5 md:grid-cols-3">
                    <MarketingCard><h2 class="text-xl font-extrabold text-[var(--text-strong)]">One connected day</h2><p class="mt-3 leading-7 text-[var(--text-muted)]">Availability, appointment, client and Sale records carry the work instead of forcing the front desk to reconcile disconnected tools.</p></MarketingCard>
                    <MarketingCard><h2 class="text-xl font-extrabold text-[var(--text-strong)]">Rules before shortcuts</h2><p class="mt-3 leading-7 text-[var(--text-muted)]">Tenant isolation, permission, local time, idempotency and append-only financial history remain product constraints—not optional polish.</p></MarketingCard>
                    <MarketingCard><h2 class="text-xl font-extrabold text-[var(--text-strong)]">Claims need proof</h2><p class="mt-3 leading-7 text-[var(--text-muted)]">The public site names provider, legal and operational limits instead of using customer logos, reviews, awards or certifications that have not been earned.</p></MarketingCard>
                </div>
            </PublicContainer>
        </section>

        <section class="gh-public-section bg-[var(--surface-subtle)]">
            <PublicContainer class="grid gap-10 lg:grid-cols-[1.2fr_0.8fr]">
                <div>
                    <h2 class="font-display text-4xl font-semibold text-[var(--text-strong)]">Operator and identity status</h2>
                    <p class="mt-5 max-w-2xl leading-8 text-[var(--text-muted)]">Good Hours is the selected product identity. Trademark clearance, the `getgoodhours.com` domain and the final legal operator/contact details are not yet approved evidence. They will not be invented here; OPEN-11 blocks public launch until named Product, Operations and counsel owners resolve them.</p>
                </div>
                <div class="rounded-[var(--radius-xl)] border border-[var(--border-strong)] bg-white p-7">
                    <h2 class="text-xl font-extrabold text-[var(--text-strong)]">Review the current evidence</h2>
                    <div class="mt-5 flex flex-col gap-3"><Link :href="route('marketing.security')" class="gh-button gh-button-secondary">Security approach</Link><Link :href="route('marketing.features')" class="gh-button gh-button-secondary">Product features</Link><PublicCta context="company_final" /></div>
                </div>
            </PublicContainer>
        </section>
    </HomeLayout>
</template>
